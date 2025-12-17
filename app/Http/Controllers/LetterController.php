<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterCategory;
use App\Models\LetterRead;
use App\Models\LetterRevision;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\LetterNumberService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LetterController extends Controller
{
    protected LetterNumberService $numberService;

    public function __construct(LetterNumberService $numberService)
    {
        $this->numberService = $numberService;
    }

    /**
     * Display inbox - letters where user is recipient.
     */
    public function inbox(Request $request)
    {
        $user = $request->user();
        $query = Letter::with(['category', 'creator', 'fromUnit', 'toUnit', 'toMember'])
            ->whereIn('status', ['submitted', 'approved', 'sent', 'archived']);

        // Filter based on user role
        $roleName = $user->role?->name;

        if (in_array($roleName, ['anggota', 'bendahara'])) {
            $query->where(function ($q) use ($user) {
                // Letters sent to this member
                if ($user->member_id) {
                    $q->orWhere(function ($sub) use ($user) {
                        $sub->where('to_type', 'member')
                            ->where('to_member_id', $user->member_id);
                    });
                }
                // Letters sent to user's unit
                if ($user->organization_unit_id) {
                    $q->orWhere(function ($sub) use ($user) {
                        $sub->where('to_type', 'unit')
                            ->where('to_unit_id', $user->organization_unit_id);
                    });
                }
            });
        } elseif ($roleName === 'admin_unit') {
            $query->where(function ($q) use ($user) {
                // Letters sent to user's unit
                if ($user->organization_unit_id) {
                    $q->where('to_type', 'unit')
                        ->where('to_unit_id', $user->organization_unit_id);
                }
            });
        } else {
            // admin_pusat, super_admin - see letters to admin_pusat
            $query->where('to_type', 'admin_pusat');
        }

        // Apply filters
        if ($request->filled('search')) {
            $query->where('subject', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category_id')) {
            $query->where('letter_category_id', $request->category_id);
        }

        $letters = $query->latest()->paginate(15)->withQueryString();
        $categories = LetterCategory::active()->ordered()->get(['id', 'name', 'code']);

        return Inertia::render('Letters/Inbox', [
            'letters' => $letters,
            'categories' => $categories,
            'filters' => $request->only(['search', 'status', 'category_id']),
        ]);
    }

    /**
     * Display outbox - letters created by user.
     */
    public function outbox(Request $request)
    {
        $user = $request->user();
        $query = Letter::with(['category', 'toUnit', 'toMember'])
            ->where('creator_user_id', $user->id);

        // Apply filters
        if ($request->filled('search')) {
            $query->where('subject', 'like', '%' . $request->search . '%');
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

        $query = Letter::with(['category', 'creator', 'fromUnit'])
            ->needsApproval();

        if (!$user->hasRole('super_admin')) {
            // Only Ketua/Sekretaris can access approvals
            $positionName = $user->getUnionPositionName();
            $signerType = $positionName ? strtolower($positionName) : null;

            if (!in_array($signerType, ['ketua', 'sekretaris'], true)) {
                abort(403);
            }

            $query->where('signer_type', $signerType);

            // For sprint 3: approver scope is their own unit
            if (!$user->organization_unit_id) {
                abort(403);
            }
            $query->where('from_unit_id', $user->organization_unit_id);
        }

        // Apply filters
        if ($request->filled('search')) {
            $query->where('subject', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('category_id')) {
            $query->where('letter_category_id', $request->category_id);
        }

        $letters = $query->latest('submitted_at')->paginate(15)->withQueryString();
        $categories = LetterCategory::active()->ordered()->get(['id', 'name', 'code']);

        return Inertia::render('Letters/Approvals', [
            'letters' => $letters,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id']),
        ]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $categories = LetterCategory::active()->ordered()->get(['id', 'name', 'code']);
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
            $fromUnitId = $user->organization_unit_id;
            if (!$fromUnitId) {
                return back()->withErrors(['from_unit_id' => 'Admin unit harus memiliki unit terkait.']);
            }
        } else {
            // admin_pusat/super_admin - use their unit if available, otherwise null (Pusat)
            $fromUnitId = $user->organization_unit_id;
        }

        $letter = Letter::create([
            'creator_user_id' => $user->id,
            'from_unit_id' => $fromUnitId,
            'letter_category_id' => $validated['letter_category_id'],
            'signer_type' => $validated['signer_type'],
            'to_type' => $validated['to_type'],
            'to_unit_id' => $validated['to_unit_id'] ?? null,
            'to_member_id' => $validated['to_member_id'] ?? null,
            'to_external_name' => $validated['to_external_name'] ?? null,
            'to_external_org' => $validated['to_external_org'] ?? null,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
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

        // Mark as read for recipients (best-effort, safe if migration not yet run)
        if ($user && Schema::hasTable('letter_reads') && $this->isRecipientUser($letter, $user)) {
            LetterRead::updateOrCreate(
                ['letter_id' => $letter->id, 'user_id' => $user->id],
                ['read_at' => now()]
            );
        }

        return Inertia::render('Letters/Show', [
            'letter' => $letter,
            'canApprove' => $canApprove,
        ]);
    }

    /**
     * Show letter preview (A4 format with letterhead).
     */
    public function preview(Letter $letter)
    {
        $this->authorize('view', $letter);

        $letter->load(['category', 'creator', 'fromUnit', 'toUnit', 'toMember', 'approvedBy', 'rejectedBy', 'revisions.actor', 'attachments']);

        // Mark as read for recipients (same as show)
        $user = request()->user();
        if ($user && Schema::hasTable('letter_reads') && $this->isRecipientUser($letter, $user)) {
            LetterRead::updateOrCreate(
                ['letter_id' => $letter->id, 'user_id' => $user->id],
                ['read_at' => now()]
            );
        }

        // Ensure verification token exists
        if (!$letter->verification_token) {
            $letter->update(['verification_token' => (string) \Illuminate\Support\Str::uuid()]);
        }

        $verifyUrl = route('letters.verify', $letter->verification_token);

        // Embed QR as base64 to avoid separate auth-dependent image request
        $qrBase64 = null;
        try {
            $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                ->size(150)
                ->margin(1)
                ->generate($verifyUrl);
            $qrBase64 = base64_encode($qrPng);
        } catch (\Throwable $e) {
            // Fallback: qrBase64 remains null, UI will show verify link
        }

        return Inertia::render('Letters/Preview', [
            'letter' => $letter,
            'verifyUrl' => $verifyUrl,
            'qrBase64' => $qrBase64,
        ]);
    }

    /**
     * Public verification page (QR code scan result).
     */
    public function verify(string $token)
    {
        $letter = Letter::with(['category', 'fromUnit'])->where('verification_token', $token)->first();

        if (!$letter) {
            return Inertia::render('Letters/Verify', [
                'valid' => false,
                'notFinal' => false,
                'letter' => null,
            ]);
        }

        // Only final statuses are considered valid for verification
        $finalStatuses = ['approved', 'sent', 'archived'];
        $isFinal = in_array($letter->status, $finalStatuses);

        if (!$isFinal) {
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

        // Ensure verification token exists
        if (!$letter->verification_token) {
            $letter->update(['verification_token' => (string) \Illuminate\Support\Str::uuid()]);
        }

        $verifyUrl = route('letters.verify', $letter->verification_token);

        try {
            // Use simplesoftwareio/simple-qrcode
            $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                ->size(150)
                ->margin(1)
                ->generate($verifyUrl);

            return response($qrCode)->header('Content-Type', 'image/png');
        } catch (\Exception $e) {
            // Fallback: return a simple 1x1 transparent PNG
            $img = imagecreatetruecolor(150, 150);
            $white = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $white);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagestring($img, 3, 10, 65, 'QR unavailable', $black);
            ob_start();
            imagepng($img);
            $data = ob_get_clean();
            imagedestroy($img);
            return response($data)->header('Content-Type', 'image/png');
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

        if (!in_array($letter->status, ['draft', 'revision'])) {
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

        return back()->with('success', count($uploaded) . ' lampiran berhasil diunggah.');
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

        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->path), 404, 'File tidak ditemukan.');

        return \Illuminate\Support\Facades\Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    /**
     * Generate PDF for final letter.
     */
    public function pdf(Letter $letter)
    {
        $this->authorize('view', $letter);

        // Only allow PDF for final letters
        $finalStatuses = ['approved', 'sent', 'archived'];
        if (!in_array($letter->status, $finalStatuses)) {
            abort(403, 'PDF hanya tersedia untuk surat yang sudah disetujui/terkirim.');
        }

        $letter->load(['category', 'creator', 'fromUnit', 'toUnit', 'toMember', 'approvedBy']);

        // Ensure verification token exists
        if (!$letter->verification_token) {
            $letter->update(['verification_token' => (string) \Illuminate\Support\Str::uuid()]);
        }

        $verifyUrl = route('letters.verify', $letter->verification_token);

        // Generate QR code offline
        $qrBase64 = null;
        try {
            $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                ->size(80)
                ->margin(1)
                ->generate($verifyUrl);
            $qrBase64 = base64_encode($qrPng);
        } catch (\Exception $e) {
            // Fallback: qrBase64 remains null
        }

        $html = view('letters.pdf', [
            'letter' => $letter,
            'verifyUrl' => $verifyUrl,
            'qrBase64' => $qrBase64,
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Surat-' . ($letter->letter_number ?: $letter->id) . '.pdf';

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    protected function isRecipientUser(Letter $letter, User $user): bool
    {
        if ($letter->to_type === 'member') {
            return (bool) ($user->member_id && $letter->to_member_id && $user->member_id === $letter->to_member_id);
        }

        if ($letter->to_type === 'unit') {
            return (bool) ($user->organization_unit_id && $letter->to_unit_id && $user->organization_unit_id === $letter->to_unit_id);
        }

        if ($letter->to_type === 'admin_pusat') {
            return in_array($user->role?->name, ['admin_pusat', 'super_admin'], true);
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
            'to_type' => $validated['to_type'],
            'to_unit_id' => $validated['to_unit_id'] ?? null,
            'to_member_id' => $validated['to_member_id'] ?? null,
            'to_external_name' => $validated['to_external_name'] ?? null,
            'to_external_org' => $validated['to_external_org'] ?? null,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
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

        $letter->update([
            'status' => 'submitted',
            'submitted_at' => now(),
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

        DB::transaction(function () use ($letter) {
            $this->numberService->assignNumber($letter);

            $letter->update([
                'status' => 'approved',
                'approved_by_user_id' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        $this->notifyCreator($letter, 'approved');
        // Notifikasi ke penerima agar "surat masuk" juga memicu notifikasi.
        // Status tetap 'approved' (bisa dikirim/manual), tetapi penerima sudah tahu ada surat baru.
        $this->notifyRecipients($letter, 'sent');

        return redirect()->route('letters.approvals')
            ->with('success', 'Surat disetujui dan nomor surat dibuat: ' . $letter->letter_number);
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
     */
    public function searchMembers(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $members = Member::where(function ($q) use ($query) {
            $q->where('full_name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('nra', 'like', "%{$query}%");
        })
            ->where('status', 'aktif')
            ->limit(20)
            ->get(['id', 'full_name', 'email', 'nra']);

        return response()->json($members->map(fn($m) => [
            'id' => $m->id,
            'label' => "{$m->full_name} ({$m->nra}) - {$m->email}",
        ]));
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
                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $approver->id)
                    ->where('type', \App\Notifications\LetterSubmittedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->exists();
                if (!$exists) {
                    $approver->notify(new \App\Notifications\LetterSubmittedNotification($letter));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify approver: ' . $e->getMessage());
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
                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $creator->id)
                    ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->where('data->action', $action)
                    ->exists();
                if (!$exists) {
                    $creator->notify(new \App\Notifications\LetterStatusUpdatedNotification($letter, $action));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify creator: ' . $e->getMessage());
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
                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $u->id)
                    ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->where('data->action', $action)
                    ->exists();
                if (!$exists) {
                    $u->notify(new \App\Notifications\LetterStatusUpdatedNotification($letter, $action));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify recipients: ' . $e->getMessage());
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
            'to_type' => 'required|in:unit,member,admin_pusat,eksternal',
            'to_unit_id' => 'required_if:to_type,unit|nullable|exists:organization_units,id',
            'to_member_id' => 'required_if:to_type,member|nullable|exists:members,id',
            'to_external_name' => 'required_if:to_type,eksternal|nullable|string|max:500',
            'to_external_org' => 'nullable|string|max:500',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
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
