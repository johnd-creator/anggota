<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SendWebhookNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId, public string $category, public string $message, public array $data = []) {}

    public function handle(): void
    {
        $url = config('services.notifications.webhook_url') ?? env('WEBHOOK_URL');
        $token = config('services.notifications.webhook_token') ?? env('WEBHOOK_TOKEN');
        $payload = ['user' => $this->userId, 'category' => $this->category, 'message' => $this->message, 'data' => $this->data];
        try {
            if (!$url) throw new \RuntimeException('Webhook URL not configured');
            $resp = Http::withToken((string) $token)->post($url, $payload);
            if (!$resp->successful()) {
                throw new \RuntimeException('Webhook failed: HTTP ' . $resp->status());
            }
            Log::info('webhook_success', ['status' => $resp->status()]);
        } catch (\Throwable $e) {
            Log::error('webhook_error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
