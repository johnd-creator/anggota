<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;

class LetterPolicy
{
    /**
     * Determine if the user can view the letter.
     * Allowed: creator OR recipient OR approver based on to_type
     * For confidential letters, access is more restricted.
     */
    public function view(User $user, Letter $letter): bool
    {
        // Super admin can view all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Creator can always view
        if ($letter->creator_user_id === $user->id) {
            return true;
        }

        // Approver can view submitted letters
        if ($this->canApprove($user, $letter)) {
            return true;
        }

        // For rahasia letters, only allow specific recipients
        if ($letter->confidentiality === 'rahasia') {
            return $this->isSpecificRecipient($user, $letter);
        }

        // For terbatas letters, allow normal recipients + approvers
        if ($letter->confidentiality === 'terbatas') {
            return $this->isRecipient($user, $letter);
        }

        // For biasa letters, allow normal recipient check
        return $this->isRecipient($user, $letter);
    }

    /**
     * Determine if the user can create letters.
     * Only admin_unit, admin_pusat, super_admin
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin_unit', 'admin_pusat', 'super_admin']);
    }

    /**
     * Determine if the user can update the letter.
     * Only creator + status in [draft, revision]
     */
    public function update(User $user, Letter $letter): bool
    {
        if ($letter->creator_user_id !== $user->id) {
            return false;
        }

        return in_array($letter->status, ['draft', 'revision']);
    }

    /**
     * Determine if the user can delete the letter.
     * Only creator + status in [draft, revision]
     */
    public function delete(User $user, Letter $letter): bool
    {
        return $this->update($user, $letter);
    }

    /**
     * Determine if the user can submit the letter.
     * Only creator + status in [draft, revision]
     */
    public function submit(User $user, Letter $letter): bool
    {
        return $this->update($user, $letter);
    }

    /**
     * Determine if the user can approve the letter.
     * Must be Ketua/Sekretaris matching signer_type + same unit + status submitted
     */
    public function approve(User $user, Letter $letter): bool
    {
        if ($letter->status !== 'submitted') {
            return false;
        }

        return $this->canApprove($user, $letter);
    }

    /**
     * Determine if the user can request revision.
     */
    public function revise(User $user, Letter $letter): bool
    {
        return $this->approve($user, $letter);
    }

    /**
     * Determine if the user can reject the letter.
     */
    public function reject(User $user, Letter $letter): bool
    {
        return $this->approve($user, $letter);
    }

    /**
     * Determine if the user can send the letter.
     */
    public function send(User $user, Letter $letter): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        if ($letter->creator_user_id !== $user->id) {
            return false;
        }
        return in_array($letter->status, ['approved']);
    }

    /**
     * Determine if the user can archive the letter.
     */
    public function archive(User $user, Letter $letter): bool
    {
        if ($user->hasRole(['super_admin', 'admin_pusat'])) {
            return true;
        }
        if ($letter->creator_user_id !== $user->id) {
            return false;
        }
        return in_array($letter->status, ['approved', 'sent']);
    }

    /**
     * Check if user can approve this letter based on signer_type and unit.
     */
    protected function canApprove(User $user, Letter $letter): bool
    {
        // Super admin can approve all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // User must have matching union position (Ketua/Sekretaris)
        if (!$user->canApproveSignerType($letter->signer_type)) {
            return false;
        }

        // User must be in the same unit as the letter's from_unit
        // For Pusat letters (from_unit_id = null), allow admin_pusat
        if ($letter->from_unit_id === null) {
            return $user->hasRole(['admin_pusat', 'super_admin']);
        }

        return $user->organization_unit_id === $letter->from_unit_id;
    }

    /**
     * Check if user is a recipient of the letter.
     */
    protected function isRecipient(User $user, Letter $letter): bool
    {
        $roleName = $user->role?->name;

        switch ($letter->to_type) {
            case 'member':
                // User's member_id matches
                return $user->member_id && $letter->to_member_id === $user->member_id;

            case 'unit':
                // User belongs to the destination unit
                return $user->organization_unit_id && $letter->to_unit_id === $user->organization_unit_id;

            case 'admin_pusat':
                // Only admin_pusat or super_admin can see these
                return $user->hasRole(['admin_pusat', 'super_admin']);

            default:
                return false;
        }
    }

    /**
     * Check if user is a specific recipient of rahasia letter.
     * More restrictive: only direct member recipient or admin of target unit.
     */
    protected function isSpecificRecipient(User $user, Letter $letter): bool
    {
        switch ($letter->to_type) {
            case 'member':
                // Only the specific member can view
                return $user->member_id && $letter->to_member_id === $user->member_id;

            case 'unit':
                // Only admin roles of that unit can view
                if (!$user->hasRole(['admin_unit', 'admin_pusat'])) {
                    return false;
                }
                return $user->organization_unit_id && $letter->to_unit_id === $user->organization_unit_id;

            case 'admin_pusat':
                return $user->hasRole(['admin_pusat', 'super_admin']);

            default:
                return false;
        }
    }
}
