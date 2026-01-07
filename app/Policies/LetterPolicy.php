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
        if ($user->hasGlobalAccess()) {
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
     * Status must be 'approved' for ALL users (including superadmin).
     */
    public function send(User $user, Letter $letter): bool
    {
        // Status must be 'approved' for anyone to send
        if ($letter->status !== 'approved') {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        return $letter->creator_user_id === $user->id;
    }

    /**
     * Determine if the user can archive the letter.
     * Status must be 'approved' or 'sent' for ALL users (including superadmin).
     */
    public function archive(User $user, Letter $letter): bool
    {
        // Status must be 'approved' or 'sent' for anyone to archive
        if (!in_array($letter->status, ['approved', 'sent'])) {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        return $letter->creator_user_id === $user->id;
    }

    /**
     * Check if user can approve this letter based on signer_type and unit.
     * Supports dual approval flow: checks which slot (primary/secondary) is pending.
     * Uses letter_approvers delegation table with union position fallback.
     * Uses currentUnitId() for consistent unit scoping.
     */
    protected function canApprove(User $user, Letter $letter): bool
    {
        // Global access can approve all
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $letterUnitId = $letter->from_unit_id;

        // For Pusat letters (from_unit_id = null), only admin_pusat/super_admin
        if ($letterUnitId === null) {
            return $user->hasRole(['admin_pusat', 'super_admin']);
        }

        // User must be in the same unit as the letter's from_unit
        $userUnitId = $user->currentUnitId();
        if ($userUnitId === null || $userUnitId !== $letterUnitId) {
            return false;
        }

        // Determine which approval slot is pending
        if (!$letter->requiresSecondaryApproval()) {
            // Single approval flow - check primary signer_type
            return $this->userCanApproveSignerType($user, $letter->signer_type, $letterUnitId);
        }

        // Dual approval flow
        if (!$letter->isPrimaryApproved()) {
            // Primary slot pending - check primary signer_type
            return $this->userCanApproveSignerType($user, $letter->signer_type, $letterUnitId);
        }

        if (!$letter->isSecondaryApproved()) {
            // Secondary slot pending - check secondary signer_type
            return $this->userCanApproveSignerType($user, $letter->signer_type_secondary, $letterUnitId);
        }

        // Both slots already approved
        return false;
    }

    /**
     * Check if user can approve a specific signer type.
     */
    protected function userCanApproveSignerType(User $user, ?string $signerType, int $unitId): bool
    {
        if (!$signerType) {
            return false;
        }

        // Check 1: User is in letter_approvers for this unit + signer_type
        if (\App\Models\LetterApprover::isApprover($unitId, $signerType, $user->id)) {
            return true;
        }

        // Check 2: Fallback to union position matching signer_type
        if ($user->canApproveSignerType($signerType)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is a recipient of the letter.
     * Uses currentUnitId() for consistent unit scoping.
     */
    protected function isRecipient(User $user, Letter $letter): bool
    {
        switch ($letter->to_type) {
            case 'member':
                // User's member_id matches
                return $user->member_id && $letter->to_member_id === $user->member_id;

            case 'unit':
                // User belongs to the destination unit
                $userUnitId = $user->currentUnitId();
                return $userUnitId !== null && $letter->to_unit_id === $userUnitId;

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
     * Uses currentUnitId() for consistent unit scoping.
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
                $userUnitId = $user->currentUnitId();
                return $userUnitId !== null && $letter->to_unit_id === $userUnitId;

            case 'admin_pusat':
                return $user->hasRole(['admin_pusat', 'super_admin']);

            default:
                return false;
        }
    }
}
