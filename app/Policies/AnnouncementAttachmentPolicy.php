<?php

namespace App\Policies;

use App\Models\AnnouncementAttachment;
use App\Models\User;
use App\Models\Announcement;

class AnnouncementAttachmentPolicy
{
    /**
     * Determine whether the user can download the attachment.
     */
    public function download(User $user, AnnouncementAttachment $attachment): bool
    {
        // Delegate to parent announcement policy
        $announcement = $attachment->announcement;
        if (!$announcement)
            return false;

        // Use the 'view' policy of the announcement
        // We instantiate AnnouncementPolicy manually or use Gate if registered, 
        // but simplest logic is replicating visibility check or delegating.
        // Best practice: check if user can view the parent announcement.

        return $user->can('view', $announcement);
    }

    /**
     * Determine whether the user can delete the attachment.
     */
    public function delete(User $user, AnnouncementAttachment $attachment): bool
    {
        $announcement = $attachment->announcement;
        if (!$announcement)
            return false;

        return $user->can('update', $announcement);
    }
}
