<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;

class MemberDataController extends Controller
{
    public function exportRequest(Request $request): JsonResponse
    {
        return $this->record($request, 'gdpr_export_request', 'Permintaan export data tercatat');
    }

    public function deleteRequest(Request $request): JsonResponse
    {
        return $this->record($request, 'gdpr_delete_request', 'Permintaan penghapusan data tercatat');
    }

    private function record(Request $request, string $action, string $message): JsonResponse
    {
        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $request->user()->id,
            'payload' => ['channel' => 'mobile'],
            'event_category' => 'security',
        ]);

        try {
            $request->user()->notify(new class($message) extends Notification {
                public function __construct(private string $message) {}

                public function via($notifiable): array
                {
                    return ['database'];
                }

                public function toDatabase($notifiable): array
                {
                    return [
                        'message' => $this->message,
                        'category' => 'security',
                    ];
                }
            });
        } catch (\Throwable) {
            // The request itself is still recorded in activity_logs.
        }

        return response()->json(['status' => 'ok', 'message' => $message]);
    }
}
