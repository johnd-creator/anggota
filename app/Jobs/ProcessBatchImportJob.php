<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\ImportBatchError;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProcessBatchImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 600; // 10 minutes for large batches

    public $maxExceptions = 2;

    /**
     * The number of seconds to wait before retrying.
     */
    public $backoff = [60, 120]; // 1m, 2m

    /**
     * Delete the job if models are missing.
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $batchId,
        public int $chunkOffset = 0,
        public int $chunkSize = 100
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing batch import chunk', [
                'batch_id' => $this->batchId,
                'offset' => $this->chunkOffset,
                'size' => $this->chunkSize,
            ]);

            $batch = ImportBatch::find($this->batchId);

            if (! $batch || $batch->status === 'completed') {
                Log::info('Batch not found or already completed', ['batch_id' => $this->batchId]);

                return;
            }

            // Update batch status to processing
            $batch->update(['status' => 'processing']);

            // Get data for this chunk
            $data = collect($batch->data ?? []);
            $chunk = $data->slice($this->chunkOffset, $this->chunkSize);

            $processed = 0;
            $success = 0;
            $failed = 0;

            DB::beginTransaction();
            try {
                foreach ($chunk as $index => $row) {
                    $actualIndex = $this->chunkOffset + $index + 1;

                    // Validate data
                    $validator = Validator::make($row, [
                        'full_name' => 'required|string|max:255',
                        'email' => 'required|email|unique:members,email',
                        'phone' => 'nullable|string|max:20',
                        'nip' => 'nullable|string|max:50',
                        'organization_unit_id' => 'nullable|exists:organization_units,id',
                        'status' => 'nullable|in:aktif,cuti,suspended,resign,pensiun',
                    ]);

                    if ($validator->fails()) {
                        $failed++;

                        // Log error
                        ImportBatchError::create([
                            'import_batch_id' => $this->batchId,
                            'row_number' => $actualIndex,
                            'data' => $row,
                            'errors' => json_encode($validator->errors()->toArray()),
                            'error_type' => 'validation',
                        ]);

                        continue;
                    }

                    // Create member
                    try {
                        $member = Member::create(array_merge($validator->validated(), [
                            'created_by' => $batch->user_id,
                            'join_date' => now()->format('Y-m-d'),
                        ]));

                        $success++;
                        $processed++;

                        Log::info('Member created during batch import', [
                            'batch_id' => $this->batchId,
                            'row' => $actualIndex,
                            'member_id' => $member->id,
                        ]);

                    } catch (\Exception $e) {
                        $failed++;

                        // Log error
                        ImportBatchError::create([
                            'import_batch_id' => $this->batchId,
                            'row_number' => $actualIndex,
                            'data' => $row,
                            'errors' => $e->getMessage(),
                            'error_type' => 'database',
                        ]);

                        Log::error('Failed to create member during batch import', [
                            'batch_id' => $this->batchId,
                            'row' => $actualIndex,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Update batch progress
                $totalRecords = $data->count();
                $batch->update([
                    'total_records' => $totalRecords,
                    'processed_records' => $this->chunkOffset + $processed,
                    'success_count' => ($batch->success_count ?? 0) + $success,
                    'failed_count' => ($batch->failed_count ?? 0) + $failed,
                ]);

                DB::commit();

                // Check if there are more chunks to process
                if ($this->chunkOffset + $this->chunkSize < $totalRecords) {
                    // Dispatch next chunk
                    self::dispatch(
                        $this->batchId,
                        $this->chunkOffset + $this->chunkSize,
                        $this->chunkSize
                    );
                } else {
                    // Mark batch as completed
                    $batch->update(['status' => 'completed', 'completed_at' => now()]);

                    Log::info('Batch import completed', [
                        'batch_id' => $this->batchId,
                        'total' => $totalRecords,
                        'success' => $batch->success_count,
                        'failed' => $batch->failed_count,
                    ]);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Batch import chunk processing failed', [
                'batch_id' => $this->batchId,
                'offset' => $this->chunkOffset,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update batch status to failed
            if ($batch = ImportBatch::find($this->batchId)) {
                $batch->update(['status' => 'failed']);
            }

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Batch import job failed permanently', [
            'batch_id' => $this->batchId,
            'offset' => $this->chunkOffset,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update batch status
        if ($batch = ImportBatch::find($this->batchId)) {
            $batch->update(['status' => 'failed']);
        }
    }
}
