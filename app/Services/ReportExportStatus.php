<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ReportExportStatus
{
    protected const CACHE_TTL = 600; // 10 minutes

    protected function getCacheKey(int $userId): string
    {
        return "export:reports:{$userId}";
    }

    public function start(User $user, string $type, array $meta = []): void
    {
        Cache::put($this->getCacheKey($user->id), [
            'status' => 'started',
            'type' => $type,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'row_count' => null,
            'filename' => null,
            'meta' => $meta,
        ], self::CACHE_TTL);
    }

    public function complete(User $user, string $type, int $rowCount, array $meta = [], ?string $filename = null): void
    {
        $key = $this->getCacheKey($user->id);
        // Retain existing started_at if possible
        $existing = Cache::get($key, []);

        Cache::put($key, [
            'status' => 'completed',
            'type' => $type,
            'started_at' => $existing['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'row_count' => $rowCount,
            'filename' => $filename,
            'meta' => array_merge($existing['meta'] ?? [], $meta),
        ], self::CACHE_TTL);
    }

    public function fail(User $user, string $type, string $reasonSafe, array $meta = []): void
    {
        $key = $this->getCacheKey($user->id);
        $existing = Cache::get($key, []);

        Cache::put($key, [
            'status' => 'failed',
            'type' => $type,
            'started_at' => $existing['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'reason' => $reasonSafe,
            'meta' => array_merge($existing['meta'] ?? [], $meta),
        ], self::CACHE_TTL);
    }

    public function get(User $user): array
    {
        return Cache::get($this->getCacheKey($user->id), [
            'status' => 'idle',
            'type' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
