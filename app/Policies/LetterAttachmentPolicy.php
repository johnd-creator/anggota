<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class LetterAttachmentPolicy
{
    /**
     * Determine if user can view/download the attachment.
     * User must be able to view the parent letter.
     */
    public function view(User $user, LetterAttachment $attachment): bool
    {
        $letter = $attachment->letter;
        if (!$letter) {
            return false;
        }

        return $user->can('view', $letter);
    }

    /**
     * Determine if user can delete the attachment.
     * Only the letter creator can delete, and only for draft/revision.
     */
    public function delete(User $user, LetterAttachment $attachment): bool
    {
        $letter = $attachment->letter;
        if (!$letter) {
            return false;
        }

        // Only creator can delete attachments
        if ($letter->creator_user_id !== $user->id) {
            return false;
        }

        // Only for draft/revision letters
        return in_array($letter->status, ['draft', 'revision']);
    }
}
