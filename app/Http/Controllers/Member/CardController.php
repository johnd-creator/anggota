<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class CardController extends Controller
{
    public function verify(string $token)
    {
        $member = Member::with('unit')->where('qr_token', $token)->firstOrFail();
        ActivityLog::create([
            'actor_id' => null,
            'action' => 'card_verification_scan',
            'subject_type' => Member::class,
            'subject_id' => $member->id,
            'payload' => ['token' => $token],
        ]);
        return Inertia::render('Member/CardVerify', [
            'member' => [
                'full_name' => $member->full_name,
                'unit' => $member->unit?->name,
                'status' => $member->status,
                'valid_until' => $member->card_valid_until,
            ],
            'scanned_at' => now()->toDateTimeString(),
        ]);
    }

    public function qr()
    {
        $member = Auth::user()?->member;
        if (!$member || !$member->qr_token) abort(404);
        $qrData = app(QrCodeService::class)->generate(route('member.card.verify', $member->qr_token), 180, 1);
        if ($qrData) {
            return response($qrData['data'])->header('Content-Type', $qrData['mime']);
        }
        abort(404);
    }
}
