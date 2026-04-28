<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\MobileApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\LetterResource;
use App\Models\Letter;
use App\Models\LetterApprover;
use App\Models\LetterAttachment;
use App\Models\LetterCategory;
use App\Models\LetterRead;
use App\Models\LetterRevision;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Services\HtmlSanitizerService;
use App\Services\LetterNumberService;
use App\Services\LetterPdfService;
use App\Services\LetterQrService;
use App\Services\LetterTemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LetterController extends Controller
{
    use MobileApiHelpers;

    public function __construct(
        private readonly LetterNumberService $numberService,
        private readonly HtmlSanitizerService $sanitizer,
        private readonly LetterTemplateRenderer $templateRenderer,
        private readonly LetterQrService $qrService,
        private readonly LetterPdfService $pdfService,
    ) {}

    public function inbox(Request $request): JsonResponse
    {
        $paginator = Letter::with($this->relations())
            ->visibleTo($request->user())
            ->whereIn('status', ['submitted', 'approved', 'sent', 'archived'])
            ->filterByRequest($request)
            ->latest()
            ->paginate($this->perPage($request));

        $paginator->getCollection()->transform(fn (Letter $letter) => new LetterResource($letter));

        return $this->paginated($paginator, 'letters');
    }

    public function outbox(Request $request): JsonResponse
    {
        Gate::authorize('create', Letter::class);

        $paginator = Letter::with($this->relations())
            ->where('creator_user_id', $request->user()->id)
            ->filterByRequest($request)
            ->latest()
            ->paginate($this->perPage($request));

        $paginator->getCollection()->transform(fn (Letter $letter) => new LetterResource($letter));

        return $this->paginated($paginator, 'letters');
    }

    public function approvals(Request $request): JsonResponse
    {
        Gate::authorize('create', Letter::class);

        $user = $request->user();
        $query = Letter::with($this->relations())->needsApproval()->filterByRequest($request);

        if (! $user->canViewGlobalScope()) {
            $unitId = $user->currentUnitId();
            $query->where('from_unit_id', $unitId)->whereIn('signer_type', ['ketua', 'sekretaris']);
        }

        $paginator = $query->latest('submitted_at')->paginate($this->perPage($request));
        $paginator->getCollection()->transform(fn (Letter $letter) => new LetterResource($letter));

        return $this->paginated($paginator, 'letters');
    }

    public function show(Request $request, Letter $letter): JsonResponse
    {
        Gate::authorize('view', $letter);
        $letter->load($this->relations(['revisions.actor']));
        $this->markAsRead($letter, $request->user());

        return response()->json(['letter' => new LetterResource($letter)]);
    }

    public function preview(Request $request, Letter $letter): JsonResponse
    {
        Gate::authorize('view', $letter);
        $letter->load($this->relations());

        $isFinal = in_array($letter->status, ['approved', 'sent', 'archived'], true);

        return response()->json([
            'letter' => new LetterResource($letter),
            'body_html' => $letter->body,
            'verification_url' => $isFinal ? route('letters.verify', $letter->verification_token) : null,
            'qr_base64' => $isFinal ? ($this->qrData($letter, 150)['base64'] ?? null) : null,
            'qr_mime' => $isFinal ? ($this->qrData($letter, 150)['mime'] ?? null) : null,
            'is_final' => $isFinal,
        ]);
    }

    public function pdf(Letter $letter)
    {
        Gate::authorize('view', $letter);
        abort_unless(in_array($letter->status, ['approved', 'sent', 'archived'], true), 403);

        $letter->load(['category', 'creator', 'fromUnit', 'toUnit', 'toMember', 'approvedBy', 'approvedSecondaryBy']);
        $this->ensureVerificationToken($letter);
        $qrData = $this->qrData($letter, 80);
        $html = view('letters.pdf', [
            'letter' => $letter,
            'bodyHtml' => $this->sanitizer->sanitize($letter->body),
            'verifyUrl' => route('letters.verify', $letter->verification_token),
            'qrBase64' => $qrData['base64'] ?? null,
            'qrMime' => $qrData['mime'] ?? null,
        ])->render();

        return response($this->pdfService->generate($html))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="Surat-'.($letter->letter_number ?: $letter->id).'.pdf"');
    }

    public function qr(Letter $letter)
    {
        Gate::authorize('view', $letter);
        abort_unless(in_array($letter->status, ['approved', 'sent', 'archived'], true), 403);

        $qrData = $this->qrData($letter, 150);

        return response($qrData['raw'] ?? $this->qrService->generateFallbackImage(), 200)
            ->header('Content-Type', $qrData['mime'] ?? 'image/png');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Letter::class);

        $data = $this->validatedLetter($request);
        $data = $this->normalizeForCreator($data, $request->user());

        $letter = DB::transaction(function () use ($data, $request) {
            $letter = Letter::create($data + [
                'creator_user_id' => $request->user()->id,
                'status' => 'draft',
            ]);

            if ($request->boolean('submit_after_save')) {
                $this->submitLetter($letter);
            }

            return $letter;
        });

        return response()->json(['status' => 'ok', 'letter' => new LetterResource($letter->load($this->relations()))], 201);
    }

    public function update(Request $request, Letter $letter): JsonResponse
    {
        Gate::authorize('update', $letter);

        $data = $this->normalizeForCreator($this->validatedLetter($request, $letter), $request->user(), $letter);
        $letter->update($data);

        return $this->ok(['letter' => new LetterResource($letter->fresh()->load($this->relations()))]);
    }

    public function destroy(Letter $letter): JsonResponse
    {
        Gate::authorize('delete', $letter);
        $letter->delete();

        return $this->ok();
    }

    public function submit(Letter $letter): JsonResponse
    {
        Gate::authorize('submit', $letter);
        $this->submitLetter($letter);

        return $this->ok(['letter' => new LetterResource($letter->fresh()->load($this->relations()))]);
    }

    public function approve(Request $request, Letter $letter): JsonResponse
    {
        Gate::authorize('approve', $letter);

        DB::transaction(function () use ($letter, $request) {
            $letter->forceFill([
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
                'approved_primary_at' => now(),
                'status' => 'approved',
                'verification_token' => $letter->verification_token ?: (string) str()->uuid(),
            ])->save();

            $this->numberService->assignNumber($letter);
        });

        return $this->ok(['letter' => new LetterResource($letter->fresh()->load($this->relations()))]);
    }

    public function revise(Request $request, Letter $letter): JsonResponse
    {
        Gate::authorize('revise', $letter);
        $validated = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($letter, $request, $validated) {
            $letter->update(['status' => 'revision', 'revision_note' => $validated['note']]);
            LetterRevision::create([
                'letter_id' => $letter->id,
                'actor_user_id' => $request->user()->id,
                'note' => $validated['note'],
            ]);
        });

        return $this->ok(['letter' => new LetterResource($letter->fresh()->load($this->relations()))]);
    }

    public function reject(Request $request, Letter $letter): JsonResponse
    {
        Gate::authorize('reject', $letter);
        $validated = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        $letter->update([
            'status' => 'rejected',
            'rejected_by_user_id' => $request->user()->id,
            'rejected_at' => now(),
            'revision_note' => $validated['note'],
        ]);

        return $this->ok(['letter' => new LetterResource($letter->fresh()->load($this->relations()))]);
    }

    public function send(Letter $letter): JsonResponse
    {
        Gate::authorize('send', $letter);
        $letter->update(['status' => 'sent']);

        return $this->ok(['letter' => new LetterResource($letter->fresh()->load($this->relations()))]);
    }

    public function archive(Letter $letter): JsonResponse
    {
        Gate::authorize('archive', $letter);
        $letter->update(['status' => 'archived']);

        return $this->ok(['letter' => new LetterResource($letter->fresh()->load($this->relations()))]);
    }

    public function storeAttachment(Request $request, Letter $letter): JsonResponse
    {
        Gate::authorize('update', $letter);

        $validated = $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf', 'max:5120'],
        ]);

        $items = [];
        foreach ($validated['attachments'] as $file) {
            $path = $file->store('letters/'.$letter->id, 'public');
            $items[] = LetterAttachment::create([
                'letter_id' => $letter->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        }

        return response()->json(['status' => 'ok', 'attachments' => $items], 201);
    }

    public function downloadAttachment(Letter $letter, LetterAttachment $attachment)
    {
        Gate::authorize('view', $letter);
        abort_unless($attachment->letter_id === $letter->id, 404);

        return Storage::disk('public')->download($attachment->path, $attachment->original_name);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'items' => LetterCategory::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'description']),
        ]);
    }

    public function approvers(Request $request): JsonResponse
    {
        $query = LetterApprover::with(['user:id,name,email', 'unit:id,name,code'])->where('is_active', true);

        if (! $request->user()->canViewGlobalScope()) {
            $query->where('organization_unit_id', $request->user()->currentUnitId());
        }

        return response()->json(['items' => $query->orderBy('signer_type')->get()]);
    }

    public function searchMembers(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $query = Member::query()->with('unit:id,name,code')->where('status', 'active');
        $this->scopeUnitQuery($query, $request->user());

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('kta_number', 'like', "%{$search}%")
                ->orWhere('nra', 'like', "%{$search}%"));
        }

        return response()->json([
            'items' => $query->orderBy('full_name')->limit(20)->get()->map(fn (Member $member) => [
                'id' => $member->id,
                'label' => trim($member->full_name.' - '.($member->kta_number ?: $member->nra)),
                'unit' => $member->unit?->name,
            ])->values(),
        ]);
    }

    public function templateRender(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template' => ['nullable', 'string'],
            'letter_category_id' => ['nullable', 'exists:letter_categories,id'],
            'to_type' => ['nullable', 'string'],
            'to_unit_id' => ['nullable', 'exists:organization_units,id'],
            'to_member_id' => ['nullable', 'exists:members,id'],
        ]);

        $unit = $request->user()->currentUnitId()
            ? OrganizationUnit::find($request->user()->currentUnitId())
            : null;
        $member = ! empty($data['to_member_id']) ? Member::find($data['to_member_id']) : null;
        $toUnit = ! empty($data['to_unit_id']) ? OrganizationUnit::find($data['to_unit_id']) : null;
        $context = $this->templateRenderer->buildContext([
            'from_unit' => ['name' => $unit?->name, 'code' => $unit?->code],
            'to_member' => ['full_name' => $member?->full_name],
            'to_unit' => ['name' => $toUnit?->name],
            'creator' => ['name' => $request->user()->name],
        ]);

        return response()->json([
            'subject' => ! empty($data['template']) ? $this->templateRenderer->render($data['template'], $context) : null,
            'body' => ! empty($data['template']) ? $this->templateRenderer->render($data['template'], $context) : null,
            'placeholders' => $this->templateRenderer->getAvailablePlaceholders(),
        ]);
    }

    private function validatedLetter(Request $request, ?Letter $letter = null): array
    {
        return $request->validate([
            'letter_category_id' => ['required', 'exists:letter_categories,id'],
            'signer_type' => ['required', Rule::in(['ketua', 'sekretaris', 'bendahara'])],
            'signer_type_secondary' => ['nullable', 'different:signer_type', Rule::in(['ketua', 'sekretaris', 'bendahara'])],
            'to_type' => ['required', Rule::in(['unit', 'member', 'external', 'admin_pusat'])],
            'to_unit_id' => ['nullable', 'required_if:to_type,unit', 'exists:organization_units,id'],
            'to_member_id' => ['nullable', 'required_if:to_type,member', 'exists:members,id'],
            'to_external_name' => ['nullable', 'required_if:to_type,external', 'string', 'max:255'],
            'to_external_org' => ['nullable', 'string', 'max:255'],
            'to_external_address' => ['nullable', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cc_text' => ['nullable', 'string', 'max:1000'],
            'confidentiality' => ['required', Rule::in(['normal', 'confidential'])],
            'urgency' => ['required', Rule::in(['normal', 'urgent', 'very_urgent'])],
        ]);
    }

    private function normalizeForCreator(array $data, $user, ?Letter $letter = null): array
    {
        if ($user->hasRole('admin_pusat')) {
            abort_if($data['to_type'] === 'admin_pusat', 422, 'Admin pusat tidak dapat membuat surat tujuan admin pusat.');
            $data['signer_type'] = 'ketua';
        }

        $data['body'] = $this->sanitizer->sanitize($data['body']);
        $data['from_unit_id'] = $letter?->from_unit_id ?? $user->currentUnitId();

        if (! $user->canViewGlobalScope() && ! $data['from_unit_id']) {
            abort(422, 'Unit organisasi tidak ditemukan.');
        }

        return $data;
    }

    private function submitLetter(Letter $letter): void
    {
        $letter->forceFill([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $letter->calculateSlaDueAt();
        $letter->save();
    }

    private function markAsRead(Letter $letter, $user): void
    {
        LetterRead::firstOrCreate(['letter_id' => $letter->id, 'user_id' => $user->id], ['read_at' => now()]);
    }

    private function ensureVerificationToken(Letter $letter): void
    {
        if (! $letter->verification_token) {
            $letter->update(['verification_token' => (string) Str::uuid()]);
        }
    }

    private function qrData(Letter $letter, int $size): ?array
    {
        $this->ensureVerificationToken($letter);

        return $this->qrService->generate(route('letters.verify', $letter->verification_token), $size, 1);
    }

    private function relations(array $extra = []): array
    {
        return array_merge(['creator:id,name', 'fromUnit:id,name,code', 'category:id,name,code', 'toUnit:id,name,code', 'toMember:id,full_name,kta_number', 'attachments'], $extra);
    }
}
