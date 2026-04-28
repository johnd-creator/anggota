<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\AnnouncementResource;
use App\Http\Resources\Mobile\AspirationResource;
use App\Models\Announcement;
use App\Models\Aspiration;
use App\Models\DuesPayment;
use App\Models\Letter;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesMobileMember;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $this->mobileMember($user);
        $unitId = $user->memberContextUnitId();

        $currentPeriod = now()->format('Y-m');
        $duesPayment = $member
            ? DuesPayment::where('member_id', $member->id)->where('period', $currentPeriod)->first()
            : null;

        $aspirations = Aspiration::with(['category', 'member:id,full_name', 'tags', 'user:id,name'])
            ->when($unitId, fn ($q) => $q->byUnit($unitId), fn ($q) => $user->canViewGlobalScope() ? $q : $q->whereRaw('1=0'))
            ->notMerged()
            ->latest()
            ->limit(5)
            ->get()
            ->each(function (Aspiration $aspiration) use ($member, $user) {
                $aspiration->setAttribute('is_supported', $member ? $aspiration->isSupporter($member) : false);
                $aspiration->setAttribute('is_own', $member ? $aspiration->member_id === $member->id : false);
                $aspiration->setAttribute('can_view_creator', $user->can('viewCreatorInfo', $aspiration));
            });

        $announcements = config('features.announcements', true)
            ? Announcement::visibleTo($user)
                ->where('pin_to_dashboard', true)
                ->whereDoesntHave('dismissals', fn ($q) => $q->where('user_id', $user->id))
                ->with(['organizationUnit:id,name,code', 'attachments'])
                ->latest()
                ->limit(5)
                ->get()
            : collect();

        $letters = Letter::query()
            ->visibleTo($user)
            ->whereIn('status', ['submitted', 'approved', 'sent', 'archived'])
            ->latest()
            ->limit(5)
            ->get(['id', 'subject', 'status', 'urgency', 'confidentiality', 'created_at']);

        $unreadNotifications = Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'profile' => [
                'has_member' => (bool) $member,
                'member_id' => $member?->id,
                'full_name' => $member?->full_name ?? $user->name,
                'kta_number' => $member?->kta_number,
                'unit_name' => $member?->unit?->name,
            ],
            'dues' => [
                'period' => $currentPeriod,
                'status' => $duesPayment?->status ?? 'unpaid',
                'amount' => (float) ($duesPayment?->amount ?? config('dues.default_amount', 30000)),
                'paid_at' => $duesPayment?->paid_at?->toDateString(),
            ],
            'notifications' => [
                'unread_count' => $unreadNotifications,
            ],
            'aspirations' => AspirationResource::collection($aspirations),
            'letters' => $letters,
            'announcements' => AnnouncementResource::collection($announcements),
        ]);
    }
}
