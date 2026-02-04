<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterCategory;
use App\Models\LetterRead;
use App\Models\LetterRevision;
use App\Models\Member;
use App\Models\NotificationPreference;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\HtmlSanitizerService;
use App\Services\LetterNumberService;
use App\Services\LetterTemplateRenderer;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LetterController extends Controller
{
    protected LetterNumberService $numberService;

    protected LetterTemplateRenderer $templateRenderer;

    protected HtmlSanitizerService $sanitizer;

    protected \App\Services\LetterQrService $qrService;

    protected \App\Services\LetterPdfService $pdfService;

    public function __construct(
        LetterNumberService $numberService,
        LetterTemplateRenderer $templateRenderer,
        HtmlSanitizerService $sanitizer,
        \App\Services\LetterQrService $qrService,
        \App\Services\LetterPdfService $pdfService
    ) {
        $this->numberService = $numberService;
        $this->templateRenderer = $templateRenderer;
        $this->sanitizer = $sanitizer;
        $this->qrService = $qrService;
        $this->pdfService = $pdfService;
    }

    /**
     * Display inbox - letters where user is recipient.
     */
    public function inbox(Request $request)
    {
        $user = $request->user();

        $query = Letter::with([
            'category:id,name,code',
            'creator:id,name',
            'fromUnit:id,name,code',
            'toUnit:id,name,code',
            'toMember:id,full_name',
        ])->whereIn('status', ['submitted', 'approved', 'sent', 'archived'])
            ->visibleTo($user)
            ->filterByRequest($request);

        $letters = $query->latest()->paginate(15)->withQueryString();
        $categories = LetterCategory::active()->ordered()->get(['id', 'name', 'code']);

        return Inertia::render('Letters/Inbox', [
            'letters' => $letters,
            'categories' => $categories,
            'filters' => $request->only(['search', 'status', 'category_id']),
            'stats' => [
                'total' => (clone $query)->count(),
                'unread' => (clone $query)->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))->count(),
                'this_week' => (clone $query)->where('created_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }

    /**
     * Display outbox - letters created by user.
     */
    public function outbox(Request $request)
    {
        $user = $request->user();
        $query = Letter::with([
            'category:id,name,code',
            'toUnit:id,name,code',
            'toMember:id,full_name',
        ])->where('creator_user_id', $user->id);

        // Apply filters
        if ($request->filled('search')) {
            $query->where('subject', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category_id')) {
            $query->where('letter_category_id', $request->category_id);
        }

        $letters = $query->latest()->paginate(15)->withQueryString();
        $categories = LetterCategory::active()->ordered()->get(['id', 'name', 'code']);

        return Inertia::render('Letters/Outbox', [
            'letters' => $letters,
            'categories' => $categories,
            'filters' => $request->only(['search', 'status', 'category_id']),
        ]);
    }

    /**
     * Display approval queue - letters awaiting approval by current user.
     */
    public function approvals(Request $request)
    {
        $user = $request->user();
        $unitId = $user->currentUnitId();

        $query = Letter::with([
            'category:id,name,code',
            'creator:id,name',
            'fromUnit:id,name,code',
        ])->needsApproval();

        if (! $user->hasGlobalAccess()) {
            // Get user's signer type capabilities
            $positionName = $user->getUnionPositionName();
            $signerType = $positionName ? strtolower($positionName) : null;

            // Check if user is in letter_approvers for any type
            $delegatedTypes = \App\Models\LetterApprover::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('signer_type')
                ->toArray();

            $allowedTypes = $delegatedTypes;
            if ($signerType && in_array($signerType, ['ketua', 'sekretaris', 'bendahara'], true)) {
                $allowedTypes[] = $signerType;
            }
            $allowedTypes = array_unique($allowedTypes);

            if (empty($allowedTypes)) {
                abort(403);
            }

            // Approver scope is their own unit
            if (! $unitId) {
                abort(403);
            }
            $query->where('from_unit_id', $unitId);

            // Show letters where:
            // 1. Primary pending (signer_type matches user) AND primary not yet approved
            // 2. Secondary pending (signer_type_secondary matches user) AND primary already approved AND secondary not yet approved
            $query->where(function ($q) use ($allowedTypes) {
                // Primary pending: signer_type matches and not yet approved
                $q->where(function ($sub) use ($allowedTypes) {
                    $sub->whereIn('signer_type', $allowedTypes)
                        ->whereNull('approved_by_user_id');
                });
                // Secondary pending: signer_type_secondary matches, primary done, secondary not done
                $q->orWhere(function ($sub) use ($allowedTypes) {
                    $sub->whereIn('signer_type_secondary', $allowedTypes)
                        ->whereNotNull('approved_by_user_id')
                        ->whereNull('approved_secondary_by_user_id');
                });
            });
        }

        // Apply filters
        $query->filterByRequest($request);
        if ($request->filled('sla_status')) {
            if ($request->sla_status === 'overdue') {
                $query->where('sla_due_at', '<', now());
            } elseif ($request->sla_status === 'ok') {
                $query->where('sla_due_at', '>=', now());
            }
        }

        $letters = $query->latest('submitted_at')->paginate(15)->withQueryString();
        $categories = LetterCategory::active()->ordered()->get(['id', 'name', 'code']);

        // SLA stats
        $baseQuery = Letter::needsApproval();
        if (! $user->hasGlobalAccess() && $unitId) {
            $baseQuery->where('from_unit_id', $unitId);
        }
        $overdueCount = (clone $baseQuery)->where('sla_due_at', '<', now())->count();

        return Inertia::render('Letters/Approvals', [
            'letters' => $letters,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id', 'sla_status']),
            'stats' => [
                'pending' => (clone $baseQuery)->count(),
                'overdue' => $overdueCount,
                'approved' => Letter::where('status', 'approved')
                    ->where('approved_at', '>=', now()->startOfMonth())
                    ->when(! $user->hasGlobalAccess() && $unitId, fn ($q) => $q->where('from_unit_id', $unitId))
                    ->count(),
                'rejected' => Letter::whereIn('status', ['rejected', 'revision'])
                    ->when(! $user->hasGlobalAccess() && $unitId, fn ($q) => $q->where('from_unit_id', $unitId))
                    ->count(),
            ],
        ]);
    }

    /**
     * Render template for a category with context.
     * Returns rendered subject, body, cc_text and defaults.
     */
    public function templateRender(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:letter_categories,id',
            'to_type' => 'nullable|in:unit,member,admin_pusat,eksternal',
            'to_unit_id' => 'nullable|exists:organization_units,id',
            'to_member_id' => 'nullable|exists:members,id',
        ]);

        $category = LetterCategory::find($request->category_id);

        if (! $category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        // Build context with safe data
        $user = $request->user();
        $contextData = [
            'creator' => ['name' => $user->name],
        ];

        // Add from_unit context (user's unit)
        $unitId = $user->currentUnitId();
        if ($unitId) {
            $unit = OrganizationUnit::find($unitId);
            if ($unit) {
                $contextData['from_unit'] = [
                    'name' => $unit->name,
                    'code' => $unit->code,
                ];
            }
        }

        // Add recipient context (to_unit or to_member)
        if ($request->to_unit_id) {
            $toUnit = OrganizationUnit::find($request->to_unit_id);
            if ($toUnit) {
                $contextData['to_unit'] = ['name' => $toUnit->name];
            }
        }

        if ($request->to_member_id) {
            $toMember = Member::find($request->to_member_id);
            if ($toMember) {
                // Only include full_name, no PII like email/phone
                $contextData['to_member'] = ['full_name' => $toMember->full_name];
            }
        }

        $context = $this->templateRenderer->buildContext($contextData);

        // Render templates
        $subject = $category->template_subject
            ? $this->templateRenderer->render($category->template_subject, $context)
            : '';
        $body = $category->template_body
            ? $this->templateRenderer->render($category->template_body, $context)
            : '';
        $ccText = $category->template_cc_text
            ? $this->templateRenderer->render($category->template_cc_text, $context)
            : '';

        return response()->json([
            'subject' => $subject,
            'body' => $body,
            'cc_text' => $ccText,
            'defaults' => [
                'confidentiality' => $category->default_confidentiality,
                'urgency' => $category->default_urgency,
                'signer_type' => $category->default_signer_type,
            ],
            'has_template' => $category->hasTemplate(),
        ]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        // Include template data for categories
        $categories = LetterCategory::active()
            ->ordered()
            ->get(['id', 'name', 'code', 'template_subject', 'template_body', 'template_cc_text', 'default_confidentiality', 'default_urgency', 'default_signer_type']);

        $units = OrganizationUnit::orderBy('code')->get(['id', 'name', 'code']);

        return Inertia::render('Letters/Form', [
            'letter' => null,
            'categories' => $categories,
            'units' => $units,
        ]);
    }

    /**
     * Store a new letter draft.
     */
    public function store(Request $request)
    {
        $validated = $this->validateLetter($request);
        $user = $request->user();
        $submitAfterSave = $request->boolean('submit_after_save');

        // Determine from_unit_id based on role
        $fromUnitId = null;
        if ($user->hasRole('admin_unit')) {
            $fromUnitId = $user->currentUnitId();
            if (! $fromUnitId) {
                return back()->withErrors(['from_unit_id' => 'Admin unit harus memiliki unit terkait.']);
            }
        } elseif ($user->hasRole(['admin_pusat', 'bendahara_pusat'])) {
            // admin_pusat & bendahara_pusat: auto-set to DPP
            $fromUnitId = $user->managedOrganization?->id;
            if (! $fromUnitId) {
                return back()->withErrors(['from_unit_id' => 'Organisasi DPP belum disetup.']);
            }
        } else {
            // super_admin - use their unit if available, otherwise null (Pusat)
            $fromUnitId = $user->currentUnitId();
        }

        $letter = Letter::create([
            'creator_user_id' => $user->id,
            'from_unit_id' => $fromUnitId,
            'letter_category_id' => $validated['letter_category_id'],
            'signer_type' => $validated['signer_type'],
            'signer_type_secondary' => $validated['signer_type_secondary'] ?? null,
            'to_type' => $validated['to_type'],
            'to_unit_id' => $validated['to_unit_id'] ?? null,
            'to_member_id' => $validated['to_member_id'] ?? null,
            'to_external_name' => $validated['to_external_name'] ?? null,
            'to_external_org' => $validated['to_external_org'] ?? null,
            'to_external_address' => $validated['to_external_address'] ?? null,
            'subject' => $validated['subject'],
            'body' => $this->sanitizer->sanitize($validated['body']),
            'cc_text' => $validated['cc_text'] ?? null,
            'confidentiality' => $validated['confidentiality'],
            'urgency' => $validated['urgency'],
            'status' => $submitAfterSave ? 'submitted' : 'draft',
            'submitted_at' => $submitAfterSave ? now() : null,
            'verification_token' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        // Notify approver if submitted
        if ($submitAfterSave) {
            $this->notifyApprover($letter);
        }

        return redirect()->route('letters.outbox')
            ->with('success', $submitAfterSave ? 'Surat berhasil diajukan untuk persetujuan' : 'Draft surat berhasil disimpan');
    }

    /**
     * Show letter detail.
     */
    public function show(Letter $letter)
    {
        $this->authorize('view', $letter);

        $letter->load(['category', 'creator', 'fromUnit', 'toUnit', 'toMember', 'approvedBy', 'rejectedBy', 'revisions.actor']);

        $user = request()->user();
        $canApprove = $user->can('approve', $letter);

        // Mark as read if user is a recipient
        $this->markAsReadIfRecipient($letter, $user);

        // Load read receipts only for authorized users (creator, approver, global)
        $reads = [];
        if ($this->canViewReadReceipts($letter, $user)) {
            $reads = $letter->reads()
                ->with('user:id,name')
                ->orderByDesc('read_at')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'user_name' => $r->user?->name ?? 'Unknown',
                    'read_at' => $r->read_at?->format('d M Y H:i'),
                ]);
        }

        // Sanitize body HTML for safe rendering
        $bodyHtml = $this->sanitizer->sanitize($letter->body);

        return Inertia::render('Letters/Show', [
            'letter' => $letter,
            'bodyHtml' => $bodyHtml,
            'canApprove' => $canApprove,
            'reads' => $reads,
            'canViewReads' => $this->canViewReadReceipts($letter, $user),
        ]);
    }

    /**
     * Show letter preview (A4 format with letterhead).
     */
    public function preview(Letter $letter)
    {
        $this->authorize('view', $letter);

        $letter->load(['category', 'creator', 'fromUnit', 'toUnit', 'toMember', 'approvedBy', 'approvedSecondaryBy', 'rejectedBy', 'revisions.actor', 'attachments']);

        // Mark as read for recipients
        $user = request()->user();
        $this->markAsReadIfRecipient($letter, $user);

        // Ensure verification token exists
        if (! $letter->verification_token) {
            $letter->update(['verification_token' => (string) \Illuminate\Support\Str::uuid()]);
        }

        $verifyUrl = route('letters.verify', $letter->verification_token);

        // Only generate QR for final (approved/sent/archived) letters
        $finalStatuses = ['approved', 'sent', 'archived'];
        $isFinal = in_array($letter->status, $finalStatuses);

        $qrBase64 = null;
        $qrMime = null;
        if ($isFinal) {
            $qrData = $this->qrService->generate($verifyUrl, 150, 1);
            if ($qrData) {
                $qrBase64 = $qrData['base64'];
                $qrMime = $qrData['mime'];
            }
        }

        // Sanitize body HTML for safe rendering
        $bodyHtml = $this->sanitizer->sanitize($letter->body);

        return Inertia::render('Letters/Preview', [
            'letter' => $letter,
            'bodyHtml' => $bodyHtml,
            'verifyUrl' => $verifyUrl,
            'qrBase64' => $qrBase64,
            'qrMime' => $qrMime,
            'isFinal' => $isFinal,
        ]);
    }

    /**
     * Public verification page (QR code scan result).
     */
    public function verify(string $token)
    {
        $letter = Letter::with(['category', 'fromUnit'])->where('verification_token', $token)->first();

        if (! $letter) {
            return Inertia::render('Letters/Verify', [
                'valid' => false,
                'notFinal' => false,
                'letter' => null,
            ]);
        }

        // Only final statuses are considered valid for verification
        $finalStatuses = ['approved', 'sent', 'archived'];
        $isFinal = in_array($letter->status, $finalStatuses);

        if (! $isFinal) {
            return Inertia::render('Letters/Verify', [
                'valid' => false,
                'notFinal' => true,
                'letter' => [
                    'status' => $letter->status,
                ],
            ]);
        }

        // Safe by default: hide sensitive data for rahasia/terbatas
        $isConfidential = in_array($letter->confidentiality, ['rahasia', 'terbatas']);

        return Inertia::render('Letters/Verify', [
            'valid' => true,
            'notFinal' => false,
            'letter' => [
                'letter_number' => $letter->letter_number,
                'category' => $letter->category?->name,
                'category_code' => $letter->category?->code,
                'from_unit' => $letter->fromUnit?->name ?? 'Pusat',
                'created_at' => $letter->created_at?->format('d M Y'),
                'approved_at' => $letter->approved_at?->format('d M Y'),
                'status' => $letter->status,
                // Only show subject for non-confidential
                'subject' => $isConfidential ? null : $letter->subject,
                'confidentiality' => $letter->confidentiality,
            ],
            'isConfidential' => $isConfidential,
        ]);
    }

    /**
     * Generate QR code image for letter verification.
     */
    public function qrCode(Letter $letter)
    {
        $this->authorize('view', $letter);

        // Only generate QR for final letters
        $finalStatuses = ['approved', 'sent', 'archived'];
        if (! in_array($letter->status, $finalStatuses)) {
            abort(403, 'QR hanya tersedia untuk surat yang sudah disetujui.');
        }

        // Ensure verification token exists
        if (! $letter->verification_token) {
            $letter->update(['verification_token' => (string) \Illuminate\Support\Str::uuid()]);
        }

        $verifyUrl = route('letters.verify', $letter->verification_token);

        $qrData = $this->qrService->generate($verifyUrl, 150, 1);
        if ($qrData) {
            return response($qrData['raw'])->header('Content-Type', $qrData['mime']);
        }

        try {
            return response($this->qrService->generateFallbackImage())->header('Content-Type', 'image/png');
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    /**
     * Store attachment(s) for a letter.
     */
    public function storeAttachment(Request $request, Letter $letter)
    {
        // Only creator can add attachments, and only for draft/revision
        if ($letter->creator_user_id !== $request->user()->id) {
            abort(403, 'Hanya pembuat surat yang dapat menambah lampiran.');
        }

        if (! in_array($letter->status, ['draft', 'revision'])) {
            abort(403, 'Lampiran hanya dapat ditambahkan ke surat draft atau revisi.');
        }

        $request->validate([
            'attachments' => 'required|array|max:10',
            'attachments.*' => 'file|mimes:pdf|max:5120', // 5MB
        ]);

        $uploaded = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->store("letters/{$letter->id}", 'local');
            $uploaded[] = LetterAttachment::create([
                'letter_id' => $letter->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        }

        return back()->with('success', count($uploaded).' lampiran berhasil diunggah.');
    }

    /**
     * Download an attachment.
     */
    public function downloadAttachment(Letter $letter, LetterAttachment $attachment)
    {
        $this->authorize('view', $letter);

        // Verify attachment belongs to letter
        if ($attachment->letter_id !== $letter->id) {
            abort(404);
        }

        // Mark as read when downloading attachment
        $this->markAsReadIfRecipient($letter, request()->user());

        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->path), 404, 'File tidak ditemukan.');

        return response()->download(storage_path('app/'.$attachment->path), $attachment->original_name);
    }

    /**
     * Generate PDF for final letter.
     */
    public function pdf(Letter $letter)
    {
        $this->authorize('view', $letter);

        // Only allow PDF for final letters
        $finalStatuses = ['approved', 'sent', 'archived'];
        if (! in_array($letter->status, $finalStatuses)) {
            abort(403, 'PDF hanya tersedia untuk surat yang sudah disetujui/terkirim.');
        }

        // Mark as read when downloading PDF
        $this->markAsReadIfRecipient($letter, request()->user());

        $letter->load(['category', 'creator', 'fromUnit', 'toUnit', 'toMember', 'approvedBy', 'approvedSecondaryBy']);

        // Ensure verification token exists
        if (! $letter->verification_token) {
            $letter->update(['verification_token' => (string) \Illuminate\Support\Str::uuid()]);
        }

        $verifyUrl = route('letters.verify', $letter->verification_token);

        // Generate QR code offline
        $qrBase64 = null;
        $qrMime = null;
        $qrData = $this->qrService->generate($verifyUrl, 80, 1);
        if ($qrData) {
            $qrBase64 = $qrData['base64'];
            $qrMime = $qrData['mime'];
        }

        // Sanitize body HTML for safe PDF rendering
        $bodyHtml = $this->sanitizer->sanitize($letter->body);

        $html = view('letters.pdf', [
            'letter' => $letter,
            'bodyHtml' => $bodyHtml,
            'verifyUrl' => $verifyUrl,
            'qrBase64' => $qrBase64,
            'qrMime' => $qrMime,
        ])->render();

        $pdfOutput = $this->pdfService->generate($html);
        $filename = 'Surat-'.($letter->letter_number ?: $letter->id).'.pdf';

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    protected function isRecipientUser(Letter $letter, User $user): bool
    {
        if ($letter->to_type === 'member') {
            return (bool) ($user->member_id && $letter->to_member_id && $user->member_id === $letter->to_member_id);
        }

        if ($letter->to_type === 'unit') {
            $unitId = $user->currentUnitId();

            return (bool) ($unitId && $letter->to_unit_id && $unitId === $letter->to_unit_id);
        }

        if ($letter->to_type === 'admin_pusat') {
            return in_array($user->role?->name, ['admin_pusat', 'super_admin'], true);
        }

        return false;
    }

    /**
     * Mark letter as read if user is a recipient.
     * Used for show, preview, pdf, downloadAttachment.
     */
    protected function markAsReadIfRecipient(Letter $letter, User $user): void
    {
        if (! Schema::hasTable('letter_reads')) {
            return;
        }

        // Only mark if user is a recipient (not just creator or approver viewing)
        if (! $this->isRecipientUser($letter, $user)) {
            return;
        }

        LetterRead::updateOrCreate(
            ['letter_id' => $letter->id, 'user_id' => $user->id],
            ['read_at' => now()]
        );
    }

    /**
     * Check if user can see read receipts.
     * Only creator, approver, or global users.
     */
    protected function canViewReadReceipts(Letter $letter, User $user): bool
    {
        // Global access can view
        if ($user->hasGlobalAccess()) {
            return true;
        }

        // Creator can view
        if ($letter->creator_user_id === $user->id) {
            return true;
        }

        // Approver for this letter can view
        if ($user->can('approve', $letter)) {
            return true;
        }

        return false;
    }

    /**
     * Show edit form.
     */
    public function edit(Letter $letter)
    {
        $this->authorize('update', $letter);

        $letter->load(['toMember', 'attachments']);
        $categories = LetterCategory::active()->ordered()->get(['id', 'name', 'code']);
        $units = OrganizationUnit::orderBy('code')->get(['id', 'name', 'code']);

        return Inertia::render('Letters/Form', [
            'letter' => $letter,
            'categories' => $categories,
            'units' => $units,
        ]);
    }

    /**
     * Update letter draft.
     */
    public function update(Request $request, Letter $letter)
    {
        $this->authorize('update', $letter);

        $validated = $this->validateLetter($request, $letter);

        $letter->update([
            'letter_category_id' => $validated['letter_category_id'],
            'signer_type' => $validated['signer_type'],
            'signer_type_secondary' => $validated['signer_type_secondary'] ?? null,
            'to_type' => $validated['to_type'],
            'to_unit_id' => $validated['to_unit_id'] ?? null,
            'to_member_id' => $validated['to_member_id'] ?? null,
            'to_external_name' => $validated['to_external_name'] ?? null,
            'to_external_org' => $validated['to_external_org'] ?? null,
            'to_external_address' => $validated['to_external_address'] ?? null,
            'subject' => $validated['subject'],
            'body' => $this->sanitizer->sanitize($validated['body']),
            'cc_text' => $validated['cc_text'] ?? null,
            'confidentiality' => $validated['confidentiality'],
            'urgency' => $validated['urgency'],
        ]);

        return redirect()->route('letters.outbox')
            ->with('success', 'Surat berhasil diperbarui');
    }

    /**
     * Delete letter draft.
     */
    public function destroy(Letter $letter)
    {
        $this->authorize('delete', $letter);

        $letter->delete();

        return redirect()->route('letters.outbox')
            ->with('success', 'Surat berhasil dihapus');
    }

    /**
     * Submit letter for approval.
     */
    public function submit(Letter $letter)
    {
        $this->authorize('submit', $letter);

        $submittedAt = now();
        $slaHours = Letter::getSlaHours($letter->urgency);

        $letter->update([
            'status' => 'submitted',
            'submitted_at' => $submittedAt,
            'sla_due_at' => $submittedAt->copy()->addHours($slaHours),
            'sla_status' => 'ok',
            // Reset approval fields for re-submission
            'approved_by_user_id' => null,
            'approved_at' => null,
            'approved_primary_at' => null,
            'approved_secondary_by_user_id' => null,
            'approved_secondary_at' => null,
        ]);

        $this->notifyApprover($letter);

        return redirect()->route('letters.outbox')
            ->with('success', 'Surat berhasil diajukan untuk persetujuan');
    }

    /**
     * Approve letter and generate number.
     */
    public function approve(Letter $letter)
    {
        $this->authorize('approve', $letter);

        $message = '';
        $isFinalApproval = false;

        DB::transaction(function () use ($letter, &$message, &$isFinalApproval) {
            if (! $letter->requiresSecondaryApproval()) {
                // Single approval flow (existing behavior)
                $this->numberService->assignNumber($letter);

                $letter->update([
                    'status' => 'approved',
                    'approved_by_user_id' => auth()->id(),
                    'approved_at' => now(),
                ]);

                $message = 'Surat disetujui dan nomor surat dibuat: '.$letter->letter_number;
                $isFinalApproval = true;
            } else {
                // Dual approval flow
                if (! $letter->isPrimaryApproved()) {
                    // Stage 1: Primary approval
                    $letter->update([
                        'approved_by_user_id' => auth()->id(),
                        'approved_primary_at' => now(),
                        // status remains 'submitted' until secondary approves
                    ]);

                    $message = 'Persetujuan pertama berhasil. Menunggu persetujuan bendahara.';

                    // Notify secondary approver (bendahara)
                    $this->notifySecondaryApprover($letter);
                    // Stage 1: Do NOT notify creator or recipients yet
                } else {
                    // Stage 2: Secondary approval - finalize
                    $this->numberService->assignNumber($letter);

                    $letter->update([
                        'status' => 'approved',
                        'approved_secondary_by_user_id' => auth()->id(),
                        'approved_secondary_at' => now(),
                        'approved_at' => now(),
                    ]);

                    $message = 'Surat disetujui dan nomor surat dibuat: '.$letter->letter_number;
                    $isFinalApproval = true;
                }
            }
        });

        // Only notify creator and recipients on FINAL approval
        if ($isFinalApproval) {
            $this->notifyCreator($letter, 'approved');
            // Notifikasi ke penerima agar "surat masuk" juga memicu notifikasi.
            $this->notifyRecipients($letter, 'sent');
        }

        return redirect()->route('letters.approvals')
            ->with('success', $message);
    }

    /**
     * Request revision on letter.
     */
    public function revise(Request $request, Letter $letter)
    {
        $this->authorize('revise', $letter);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ], [
            'note.required' => 'Catatan revisi wajib diisi.',
        ]);

        DB::transaction(function () use ($letter, $validated) {
            LetterRevision::create([
                'letter_id' => $letter->id,
                'actor_user_id' => auth()->id(),
                'note' => $validated['note'],
            ]);

            $letter->update([
                'status' => 'revision',
                'revision_note' => $validated['note'],
            ]);
        });

        $this->notifyCreator($letter, 'revision');

        return redirect()->route('letters.approvals')
            ->with('success', 'Surat dikembalikan untuk revisi');
    }

    /**
     * Reject letter.
     */
    public function reject(Request $request, Letter $letter)
    {
        $this->authorize('reject', $letter);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ], [
            'note.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $letter->update([
            'status' => 'rejected',
            'rejected_by_user_id' => auth()->id(),
            'rejected_at' => now(),
            'revision_note' => $validated['note'],
        ]);

        $this->notifyCreator($letter, 'rejected');

        return redirect()->route('letters.approvals')
            ->with('success', 'Surat telah ditolak');
    }

    public function send(Letter $letter)
    {
        $this->authorize('send', $letter);
        $letter->update(['status' => 'sent']);
        $this->notifyRecipients($letter, 'sent');

        return redirect()->route('letters.outbox')->with('success', 'Surat berhasil dikirim');
    }

    public function archive(Letter $letter)
    {
        $this->authorize('archive', $letter);
        $letter->update(['status' => 'archived']);
        $this->notifyRecipients($letter, 'archived');

        return redirect()->route('letters.outbox')->with('success', 'Surat diarsipkan');
    }

    /**
     * Search members for autocomplete.
     * Scoped by unit for non-global users; email excluded from results for admin_unit.
     */
    public function searchMembers(Request $request)
    {
        $query = $request->get('q', '');
        $user = $request->user();

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $isGlobal = $user?->hasGlobalAccess() ?? false;
        $unitId = $user?->currentUnitId();

        // Non-global users must have a unit
        if (! $isGlobal && ! $unitId) {
            return response()->json([]);
        }

        $membersQuery = Member::query()
            ->where('status', 'aktif');

        // Scope to user's unit if not global
        if (! $isGlobal) {
            $membersQuery->where('organization_unit_id', $unitId);
        }

        // Global users can search by email, non-global only by name/nra
        if ($isGlobal) {
            $membersQuery->where(function ($q) use ($query) {
                $q->where('full_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('nra', 'like', "%{$query}%");
            });
        } else {
            $membersQuery->where(function ($q) use ($query) {
                $q->where('full_name', 'like', "%{$query}%")
                    ->orWhere('nra', 'like', "%{$query}%");
            });
        }

        // Select only needed fields - no email for non-global
        $selectFields = $isGlobal
            ? ['id', 'full_name', 'email', 'nra']
            : ['id', 'full_name', 'nra'];

        $members = $membersQuery->limit(20)->get($selectFields);

        return response()->json($members->map(function ($m) use ($isGlobal) {
            $label = $isGlobal
                ? "{$m->full_name} ({$m->nra}) - {$m->email}"
                : "{$m->full_name} ({$m->nra})";

            return [
                'id' => $m->id,
                'label' => $label,
            ];
        }));
    }

    /**
     * Notify the approver when letter is submitted.
     */
    protected function notifyApprover(Letter $letter): void
    {
        try {
            $letter->load('fromUnit');

            $positionName = ucfirst($letter->signer_type); // 'Ketua' or 'Sekretaris'

            $approvers = User::whereHas('linkedMember.unionPosition', function ($q) use ($positionName) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($positionName)]);
            })
                ->where('organization_unit_id', $letter->from_unit_id)
                ->get();

            foreach ($approvers as $approver) {
                // Check letter notification preference
                if (! NotificationPreference::isChannelEnabled($approver->id, 'letters')) {
                    continue;
                }

                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $approver->id)
                    ->where('type', \App\Notifications\LetterSubmittedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->exists();
                if (! $exists) {
                    $approver->notify(new \App\Notifications\LetterSubmittedNotification($letter));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify approver: '.$e->getMessage());
        }
    }

    /**
     * Notify the secondary approver (bendahara) when primary approval is done.
     */
    protected function notifySecondaryApprover(Letter $letter): void
    {
        try {
            $letter->load('fromUnit');

            $secondaryType = $letter->signer_type_secondary;
            if (! $secondaryType) {
                return;
            }

            $positionName = ucfirst($secondaryType); // 'Bendahara'

            // Find users matching the secondary signer type in the letter's unit
            $approvers = User::whereHas('linkedMember.unionPosition', function ($q) use ($positionName) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($positionName)]);
            })
                ->where('organization_unit_id', $letter->from_unit_id)
                ->get();

            // Also check letter_approvers table
            $delegatedApprovers = \App\Models\LetterApprover::where('organization_unit_id', $letter->from_unit_id)
                ->where('signer_type', $secondaryType)
                ->where('is_active', true)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            $allApprovers = $approvers->merge($delegatedApprovers)->unique('id');

            foreach ($allApprovers as $approver) {
                if (! $approver) {
                    continue;
                }

                // Check letter notification preference
                if (! NotificationPreference::isChannelEnabled($approver->id, 'letters')) {
                    continue;
                }

                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $approver->id)
                    ->where('type', \App\Notifications\LetterSubmittedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->exists();
                if (! $exists) {
                    $approver->notify(new \App\Notifications\LetterSubmittedNotification($letter));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify secondary approver: '.$e->getMessage());
        }
    }

    /**
     * Notify the creator when letter status changes.
     */
    protected function notifyCreator(Letter $letter, string $action): void
    {
        try {
            $creator = User::find($letter->creator_user_id);
            if ($creator) {
                // Check letter notification preference
                if (! NotificationPreference::isChannelEnabled($creator->id, 'letters')) {
                    return;
                }

                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $creator->id)
                    ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->where('data->action', $action)
                    ->exists();
                if (! $exists) {
                    $creator->notify(new \App\Notifications\LetterStatusUpdatedNotification($letter, $action));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify creator: '.$e->getMessage());
        }
    }

    protected function notifyRecipients(Letter $letter, string $action): void
    {
        try {
            if ($letter->to_type === 'member' && $letter->to_member_id) {
                $users = User::where('member_id', $letter->to_member_id)->get();
            } elseif ($letter->to_type === 'unit' && $letter->to_unit_id) {
                $users = User::where('organization_unit_id', $letter->to_unit_id)
                    ->whereHas('role', function ($q) {
                        $q->whereIn('name', ['admin_unit', 'bendahara']);
                    })->get();
            } elseif ($letter->to_type === 'admin_pusat') {
                $users = User::whereHas('role', function ($q) {
                    $q->whereIn('name', ['admin_pusat', 'super_admin']);
                })->get();
            } else {
                $users = collect();
            }
            foreach ($users as $u) {
                // Check letter notification preference
                if (! NotificationPreference::isChannelEnabled($u->id, 'letters')) {
                    continue;
                }

                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $u->id)
                    ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->where('data->action', $action)
                    ->exists();
                if (! $exists) {
                    $u->notify(new \App\Notifications\LetterStatusUpdatedNotification($letter, $action));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify recipients: '.$e->getMessage());
        }
    }

    /**
     * Validate letter request.
     */
    protected function validateLetter(Request $request, ?Letter $letter = null): array
    {
        return $request->validate([
            'letter_category_id' => [
                'required',
                Rule::exists('letter_categories', 'id')->where('is_active', true),
            ],
            'signer_type' => 'required|in:ketua,sekretaris',
            'signer_type_secondary' => [
                'nullable',
                'in:bendahara',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $value === $request->signer_type) {
                        $fail('Penandatangan sekunder tidak boleh sama dengan penandatangan utama.');
                    }
                },
            ],
            'to_type' => 'required|in:unit,member,admin_pusat,eksternal',
            'to_unit_id' => 'required_if:to_type,unit|nullable|exists:organization_units,id',
            'to_member_id' => 'required_if:to_type,member|nullable|exists:members,id',
            'to_external_name' => 'required_if:to_type,eksternal|nullable|string|max:500',
            'to_external_org' => 'nullable|string|max:500',
            'to_external_address' => 'nullable|string|max:2000',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:50000',
            'cc_text' => 'nullable|string|max:5000',
            'confidentiality' => 'required|in:biasa,terbatas,rahasia',
            'urgency' => 'required|in:biasa,segera,kilat',
        ], [
            'letter_category_id.required' => 'Kategori surat wajib dipilih.',
            'letter_category_id.exists' => 'Kategori surat tidak valid atau tidak aktif.',
            'signer_type.required' => 'Penandatangan wajib dipilih.',
            'to_type.required' => 'Tipe tujuan wajib dipilih.',
            'to_unit_id.required_if' => 'Unit tujuan wajib dipilih.',
            'to_member_id.required_if' => 'Anggota tujuan wajib dipilih.',
            'to_external_name.required_if' => 'Nama/jabatan penerima eksternal wajib diisi.',
            'subject.required' => 'Perihal surat wajib diisi.',
            'body.required' => 'Isi surat wajib diisi.',
            'confidentiality.required' => 'Sifat surat wajib dipilih.',
            'urgency.required' => 'Urgensi wajib dipilih.',
        ]);
    }
}
