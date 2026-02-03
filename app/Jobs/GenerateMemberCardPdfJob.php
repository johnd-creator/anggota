<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class GenerateMemberCardPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 120; // 2 minutes

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
        public int $memberId,
        public int $userId,
        public bool $saveToFile = true
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting member card PDF generation', [
                'member_id' => $this->memberId,
                'user_id' => $this->userId,
            ]);

            $member = Member::find($this->memberId);
            $user = User::find($this->userId);

            if (! $member) {
                Log::warning('Member not found for PDF generation', ['member_id' => $this->memberId]);

                return;
            }

            // Generate PDF
            $html = View::make('pdf.card', ['member' => $member])->render();
            $dompdf = new Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A6', 'portrait');
            $dompdf->render();

            $pdfContent = $dompdf->output();

            if ($this->saveToFile) {
                // Save to storage
                $fileName = "member-cards/{$member->id}/card-{$member->id}-".now()->format('Y-m-d-His').'.pdf';
                Storage::disk('public')->put($fileName, $pdfContent);

                Log::info('Member card PDF generated successfully', [
                    'member_id' => $this->memberId,
                    'file' => $fileName,
                    'size' => Storage::disk('public')->size($fileName),
                ]);

                // Optional: Notify user that PDF is ready
                // Notification::send($user, new CardPdfReadyNotification($member, $fileName));
            } else {
                // Just generate without saving
                Log::info('Member card PDF generated (not saved)', [
                    'member_id' => $this->memberId,
                    'size' => strlen($pdfContent),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Member card PDF generation failed', [
                'member_id' => $this->memberId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Member card PDF generation failed permanently', [
            'member_id' => $this->memberId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Notify user of failure
        try {
            $user = User::find($this->userId);
            if ($user) {
                // Send notification or email about failure
                // Notification::send($user, new CardPdfFailedNotification($exception->getMessage()));
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify user of PDF generation failure', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
