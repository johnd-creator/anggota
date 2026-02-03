<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportMembersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 300; // 5 minutes

    public $maxExceptions = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [30, 60, 120]; // 30s, 1m, 2m

    /**
     * Delete the job if models are missing.
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public array $filters = [],
        public ?string $fileName = null
    ) {
        $this->fileName = $fileName ?? 'members-export-'.now()->format('Y-m-d-His').'.xlsx';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting member export', ['user_id' => $this->userId, 'filters' => $this->filters]);

            $user = User::find($this->userId);

            if (! $user) {
                Log::warning('User not found for export job', ['user_id' => $this->userId]);

                return;
            }

            // Query members based on filters
            $query = \App\Models\Member::query();

            if (! empty($this->filters['unit_id'])) {
                $query->where('organization_unit_id', $this->filters['unit_id']);
            }

            if (! empty($this->filters['status'])) {
                $query->where('status', $this->filters['status']);
            }

            if (! empty($this->filters['search'])) {
                $search = $this->filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            }

            $members = $query->get();

            // Generate CSV file (simpler, no dependency on complex export)
            $csvData = $this->formatMembersAsCsv($members);
            $filePath = 'exports/members/'.$this->fileName;

            Storage::disk('public')->put($filePath, $csvData);

            Log::info('Member export completed', [
                'user_id' => $this->userId,
                'file' => $filePath,
                'size' => Storage::disk('public')->size($filePath),
            ]);

            // Optionally: Notify user that export is ready
            // Notification::send($user, new ExportReadyNotification($filePath));

        } catch (\Exception $e) {
            Log::error('Member export job failed', [
                'user_id' => $this->userId,
                'filters' => $this->filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Format members data as CSV
     */
    protected function formatMembersAsCsv($members): string
    {
        $csv = fopen('php://temp', 'r+');

        // Header
        fputcsv($csv, [
            'ID',
            'Nama Lengkap',
            'NIP',
            'Email',
            'No. KTA',
            'Unit',
            'Jabatan Serikat',
            'Status',
            'Tanggal Gabung',
            'Jenis Pekerja',
            'Nomor Telepon',
            'Tanggal Lahir',
            'Tempat Lahir',
        ]);

        // Data rows
        foreach ($members as $member) {
            fputcsv($csv, [
                $member->id,
                $member->full_name,
                $member->nip ?: '',
                $member->email ?: '',
                $member->kta_number ?: '',
                $member->unit->name ?? '',
                $member->unionPosition->name ?? '',
                $member->status,
                $member->join_date?->format('Y-m-d') ?? '',
                $member->employment_type ?? '',
                $member->phone ?? '',
                $member->birth_date?->format('Y-m-d') ?? '',
                $member->birth_place ?? '',
            ]);
        }

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        return $csvContent;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Member export job failed permanently', [
            'user_id' => $this->userId,
            'filters' => $this->filters,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Notify user of failure
        try {
            $user = User::find($this->userId);
            if ($user) {
                // Send notification or email about failure
                // Notification::send($user, new ExportFailedNotification($exception->getMessage()));
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify user of export failure', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
