<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'feedback_submitted',
            'subject_type' => User::class,
            'subject_id' => $request->user()->id,
            'payload' => [
                'rating' => (int) $data['rating'],
                'message' => (string) ($data['message'] ?? ''),
                'channel' => 'mobile',
            ],
        ]);

        return response()->json(['status' => 'ok']);
    }
}
