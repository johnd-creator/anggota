<?php

namespace App\Http\Controllers\Letter;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterRead;
use App\Models\User;
use App\Services\HtmlSanitizerService;
use App\Services\LetterQrService;
use App\Services\LetterPdfService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ViewController extends Controller
{
    protected LetterQrService $qrService;
    protected LetterPdfService $pdfService;
    protected HtmlSanitizerService $sanitizer;

    public function __construct(
        LetterQrService $qrService,
        LetterPdfService $pdfService,
        HtmlSanitizerService $sanitizer
    ) {
        $this->qrService = $qrService;
        $this->pdfService = $pdfService;
        $this->sanitizer = $sanitizer;
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
        if (!$letter->verification_token) {
            $letter->update(['verification_token' => (string) Str::uuid()]);
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
        if (!in_array($letter->status, $finalStatuses)) {
            abort(403, 'QR hanya tersedia untuk surat yang sudah disetujui.');
        }

        // Ensure verification token exists
        if (!$letter->verification_token) {
            $letter->update(['verification_token' => (string) Str::uuid()]);
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

        // Mark as read when downloading PDF
        $this->markAsReadIfRecipient($letter, request()->user());

        $letter->load(['category', 'creator', 'fromUnit', 'toUnit', 'toMember', 'approvedBy', 'approvedSecondaryBy']);

        // Ensure verification token exists
        if (!$letter->verification_token) {
            $letter->update(['verification_token' => (string) Str::uuid()]);
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
        $filename = 'Surat-' . ($letter->letter_number ?: $letter->id) . '.pdf';

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
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
     */
    protected function markAsReadIfRecipient(Letter $letter, User $user): void
    {
        if (!Schema::hasTable('letter_reads')) {
            return;
        }

        if (!$this->isRecipientUser($letter, $user)) {
            return;
        }

        LetterRead::updateOrCreate(
            ['letter_id' => $letter->id, 'user_id' => $user->id],
            ['read_at' => now()]
        );
    }
}
