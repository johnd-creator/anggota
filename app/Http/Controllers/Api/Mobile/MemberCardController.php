<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileMember;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Services\QrCodeService;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class MemberCardController extends Controller
{
    use ResolvesMobileMember;

    public function show(Request $request): JsonResponse
    {
        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        $issueError = $this->ensureCardIssued($member);
        if ($issueError) {
            return $issueError;
        }

        return response()->json([
            'card' => [
                'member_id' => $member->id,
                'full_name' => $member->full_name,
                'kta_number' => $member->kta_number,
                'nra' => $member->nra,
                'status' => $member->status,
                'unit' => [
                    'id' => $member->unit?->id,
                    'name' => $member->unit?->name,
                    'code' => $member->unit?->code,
                ],
                'qr_token' => $member->qr_token,
                'verify_url' => $member->qr_token ? route('member.card.verify', $member->qr_token) : null,
                'verify_api_url' => $member->qr_token ? route('api.mobile.member.card.verify', $member->qr_token) : null,
                'download_url' => route('api.mobile.member.card.pdf'),
                'valid_until' => $member->card_valid_until,
                'has_qr' => filled($member->qr_token),
                'can_download_pdf' => filled($member->kta_number) && filled($member->qr_token),
            ],
        ]);
    }

    public function qr(Request $request)
    {
        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json(['message' => 'QR kartu anggota tidak tersedia.'], 404);
        }

        $issueError = $this->ensureCardIssued($member);
        if ($issueError) {
            return $issueError;
        }

        if (! $member->qr_token) {
            return response()->json(['message' => 'QR kartu anggota tidak tersedia.'], 404);
        }

        $qrData = app(QrCodeService::class)->generate(route('member.card.verify', $member->qr_token), 180, 1);
        if (! $qrData) {
            return response()->json(['message' => 'QR kartu anggota gagal dibuat.'], 500);
        }

        return response($qrData['data'])->header('Content-Type', $qrData['mime']);
    }

    public function pdf(Request $request)
    {
        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        $issueError = $this->ensureCardIssued($member);
        if ($issueError) {
            return $issueError;
        }

        try {
            $html = View::make('pdf.card', ['member' => $member->loadMissing('unit')])->render();
            $dompdf = new Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A6', 'portrait');
            $dompdf->render();

            ActivityLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'card_pdf_download',
                'subject_type' => Member::class,
                'subject_id' => $member->id,
                'payload' => ['format' => 'pdf', 'source' => 'mobile'],
            ]);

            return response($dompdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="'.$this->filename($member).'"')
                ->header('Content-Transfer-Encoding', 'binary')
                ->header('Accept-Ranges', 'bytes')
                ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal membuat PDF kartu anggota.'], 500);
        }
    }

    public function verify(string $token): JsonResponse
    {
        $member = Member::with('unit')->where('qr_token', $token)->first();

        if (! $member) {
            return response()->json(['message' => 'KTA digital tidak ditemukan.'], 404);
        }

        ActivityLog::create([
            'actor_id' => null,
            'action' => 'card_verification_scan',
            'subject_type' => Member::class,
            'subject_id' => $member->id,
            'payload' => ['token' => $token, 'source' => 'mobile_api'],
        ]);

        return response()->json([
            'card' => [
                'full_name' => $member->full_name,
                'unit' => $member->unit?->name,
                'status' => $member->status,
                'valid_until' => $member->card_valid_until,
            ],
            'scanned_at' => now()->toDateTimeString(),
        ]);
    }

    private function ensureCardIssued(Member $member): ?JsonResponse
    {
        $member->loadMissing('unit');

        if (! $member->kta_number) {
            return response()->json(['message' => 'Nomor KTA belum tersedia.'], 422);
        }

        if (! $member->unit?->canIssueKta()) {
            return response()->json(['message' => 'Unit anggota tidak dapat menerbitkan KTA digital.'], 422);
        }

        if (! $member->qr_token || ! $member->card_valid_until) {
            $member->forceFill([
                'qr_token' => $member->qr_token ?: $this->uniqueQrToken(),
                'card_valid_until' => $member->card_valid_until ?: now()->addYear()->toDateString(),
            ])->save();
            $member->refresh()->loadMissing('unit');
        }

        return null;
    }

    private function uniqueQrToken(): string
    {
        do {
            $token = Str::random(32);
        } while (Member::where('qr_token', $token)->exists());

        return $token;
    }

    private function filename(Member $member): string
    {
        $sanitize = fn (?string $value, string $fallback) => str_replace(
            ['/', '\\', ':', '*', '?', '"', '<', '>', '|'],
            '-',
            $value ?: $fallback
        );

        return 'KTA-'.$sanitize($member->kta_number, 'NRA').'-'.$sanitize($member->full_name, 'Anggota').'.pdf';
    }
}
