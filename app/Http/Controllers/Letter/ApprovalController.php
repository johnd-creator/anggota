<?php

namespace App\Http\Controllers\Letter;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\LetterNumberService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    protected LetterNumberService $numberService;

    public function __construct(LetterNumberService $numberService)
    {
        $this->numberService = $numberService;
    }

    /**
     * Submit letter for approval.
     */
    public function submit(Letter $letter)
    {
        $this->authorize('submit', $letter);

        $submittedAt = now();
        $slaHours = Letter::getSlaHours($letter->urgency);

        $letter->update([
            'status' => 'submitted',
            'submitted_at' => $submittedAt,
            'sla_due_at' => $submittedAt->copy()->addHours($slaHours),
            'sla_status' => 'ok',
            'approved_by_user_id' => null,
            'approved_at' => null,
            'approved_primary_at' => null,
            'approved_secondary_by_user_id' => null,
            'approved_secondary_at' => null,
        ]);

        $this->notifyApprover($letter);

        return redirect()->route('letters.outbox')
            ->with('success', 'Surat berhasil diajukan untuk persetujuan');
    }

    /**
     * Approve letter and generate number.
     */
    public function approve(Letter $letter)
    {
        $this->authorize('approve', $letter);

        $message = '';
        $isFinalApproval = false;

        DB::transaction(function () use ($letter, &$message, &$isFinalApproval) {
            if (!$letter->requiresSecondaryApproval()) {
                $this->numberService->assignNumber($letter);

                $letter->update([
                    'status' => 'approved',
                    'approved_by_user_id' => auth()->id(),
                    'approved_at' => now(),
                ]);

                $message = 'Surat disetujui dan nomor surat dibuat: ' . $letter->letter_number;
                $isFinalApproval = true;
            } else {
                if (!$letter->isPrimaryApproved()) {
                    $letter->update([
                        'approved_by_user_id' => auth()->id(),
                        'approved_primary_at' => now(),
                    ]);

                    $message = 'Persetujuan pertama berhasil. Menunggu persetujuan bendahara.';
                    $this->notifySecondaryApprover($letter);
                } else {
                    $this->numberService->assignNumber($letter);

                    $letter->update([
                        'status' => 'approved',
                        'approved_secondary_by_user_id' => auth()->id(),
                        'approved_secondary_at' => now(),
                        'approved_at' => now(),
                    ]);

                    $message = 'Surat disetujui dan nomor surat dibuat: ' . $letter->letter_number;
                    $isFinalApproval = true;
                }
            }
        });

        if ($isFinalApproval) {
            $this->notifyCreator($letter, 'approved');
            $this->notifyRecipients($letter, 'sent');
        }

        return redirect()->route('letters.approvals')
            ->with('success', $message);
    }

    /**
     * Request revision on letter.
     */
    public function revise(Request $request, Letter $letter)
    {
        $this->authorize('revise', $letter);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ], [
            'note.required' => 'Catatan revisi wajib diisi.',
        ]);

        DB::transaction(function () use ($letter, $validated) {
            LetterRevision::create([
                'letter_id' => $letter->id,
                'actor_user_id' => auth()->id(),
                'note' => $validated['note'],
            ]);

            $letter->update([
                'status' => 'revision',
                'revision_note' => $validated['note'],
            ]);
        });

        $this->notifyCreator($letter, 'revision');

        return redirect()->route('letters.approvals')
            ->with('success', 'Surat dikembalikan untuk revisi');
    }

    /**
     * Reject letter.
     */
    public function reject(Request $request, Letter $letter)
    {
        $this->authorize('reject', $letter);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ], [
            'note.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $letter->update([
            'status' => 'rejected',
            'rejected_by_user_id' => auth()->id(),
            'rejected_at' => now(),
            'revision_note' => $validated['note'],
        ]);

        $this->notifyCreator($letter, 'rejected');

        return redirect()->route('letters.approvals')
            ->with('success', 'Surat telah ditolak');
    }

    public function send(Letter $letter)
    {
        $this->authorize('send', $letter);
        $letter->update(['status' => 'sent']);
        $this->notifyRecipients($letter, 'sent');
        return redirect()->route('letters.outbox')->with('success', 'Surat berhasil dikirim');
    }

    public function archive(Letter $letter)
    {
        $this->authorize('archive', $letter);
        $letter->update(['status' => 'archived']);
        $this->notifyRecipients($letter, 'archived');
        return redirect()->route('letters.outbox')->with('success', 'Surat diarsipkan');
    }

    /**
     * Notify the approver when letter is submitted.
     */
    protected function notifyApprover(Letter $letter): void
    {
        try {
            $letter->load('fromUnit');
            $positionName = ucfirst($letter->signer_type);

            $approvers = User::whereHas('linkedMember.unionPosition', function ($q) use ($positionName) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($positionName)]);
            })
                ->where('organization_unit_id', $letter->from_unit_id)
                ->get();

            foreach ($approvers as $approver) {
                if (!NotificationPreference::isChannelEnabled($approver->id, 'letters')) {
                    continue;
                }

                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $approver->id)
                    ->where('type', \App\Notifications\LetterSubmittedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->exists();
                if (!$exists) {
                    $approver->notify(new \App\Notifications\LetterSubmittedNotification($letter));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify approver: ' . $e->getMessage());
        }
    }

    /**
     * Notify the secondary approver (bendahara) when primary approval is done.
     */
    protected function notifySecondaryApprover(Letter $letter): void
    {
        try {
            $letter->load('fromUnit');
            $secondaryType = $letter->signer_type_secondary;
            if (!$secondaryType) {
                return;
            }

            $positionName = ucfirst($secondaryType);

            $approvers = User::whereHas('linkedMember.unionPosition', function ($q) use ($positionName) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($positionName)]);
            })
                ->where('organization_unit_id', $letter->from_unit_id)
                ->get();

            $delegatedApprovers = \App\Models\LetterApprover::where('organization_unit_id', $letter->from_unit_id)
                ->where('signer_type', $secondaryType)
                ->where('is_active', true)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            $allApprovers = $approvers->merge($delegatedApprovers)->unique('id');

            foreach ($allApprovers as $approver) {
                if (!$approver)
                    continue;

                if (!NotificationPreference::isChannelEnabled($approver->id, 'letters')) {
                    continue;
                }

                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $approver->id)
                    ->where('type', \App\Notifications\LetterSubmittedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->exists();
                if (!$exists) {
                    $approver->notify(new \App\Notifications\LetterSubmittedNotification($letter));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify secondary approver: ' . $e->getMessage());
        }
    }

    /**
     * Notify the creator when letter status changes.
     */
    protected function notifyCreator(Letter $letter, string $action): void
    {
        try {
            $creator = User::find($letter->creator_user_id);
            if ($creator) {
                if (!NotificationPreference::isChannelEnabled($creator->id, 'letters')) {
                    return;
                }

                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $creator->id)
                    ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->where('data->action', $action)
                    ->exists();
                if (!$exists) {
                    $creator->notify(new \App\Notifications\LetterStatusUpdatedNotification($letter, $action));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify creator: ' . $e->getMessage());
        }
    }

    protected function notifyRecipients(Letter $letter, string $action): void
    {
        try {
            if ($letter->to_type === 'member' && $letter->to_member_id) {
                $users = User::where('member_id', $letter->to_member_id)->get();
            } elseif ($letter->to_type === 'unit' && $letter->to_unit_id) {
                $users = User::where('organization_unit_id', $letter->to_unit_id)
                    ->whereHas('role', function ($q) {
                        $q->whereIn('name', ['admin_unit', 'bendahara']);
                    })->get();
            } elseif ($letter->to_type === 'admin_pusat') {
                $users = User::whereHas('role', function ($q) {
                    $q->whereIn('name', ['admin_pusat', 'super_admin']);
                })->get();
            } else {
                $users = collect();
            }

            foreach ($users as $u) {
                if (!NotificationPreference::isChannelEnabled($u->id, 'letters')) {
                    continue;
                }

                $exists = DatabaseNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $u->id)
                    ->where('type', \App\Notifications\LetterStatusUpdatedNotification::class)
                    ->where('data->letter_id', $letter->id)
                    ->where('data->action', $action)
                    ->exists();
                if (!$exists) {
                    $u->notify(new \App\Notifications\LetterStatusUpdatedNotification($letter, $action));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify recipients: ' . $e->getMessage());
        }
    }
}
