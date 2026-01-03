<?php

namespace App\Providers;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Policies\MemberPolicy;
use App\Models\PendingMember;
use App\Policies\PendingMemberPolicy;
use App\Policies\OrganizationUnitPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        OrganizationUnit::class => OrganizationUnitPolicy::class,
        Member::class => MemberPolicy::class,
        PendingMember::class => PendingMemberPolicy::class,
        \App\Models\FinanceCategory::class => \App\Policies\FinanceCategoryPolicy::class,
        \App\Models\FinanceLedger::class => \App\Policies\FinanceLedgerPolicy::class,
        \App\Models\UserSession::class => \App\Policies\UserSessionPolicy::class,
        \App\Models\Aspiration::class => \App\Policies\AspirationPolicy::class,
        \App\Models\Letter::class => \App\Policies\LetterPolicy::class,
        \App\Models\AuditLog::class => \App\Policies\AuditLogPolicy::class,
        \App\Models\MutationRequest::class => \App\Policies\MutationRequestPolicy::class,
        \App\Models\MemberUpdateRequest::class => \App\Policies\MemberUpdateRequestPolicy::class,
        \App\Models\DuesPayment::class => \App\Policies\DuesPaymentPolicy::class,
        \App\Models\LetterAttachment::class => \App\Policies\LetterAttachmentPolicy::class,
        \App\Models\Announcement::class => \App\Policies\AnnouncementPolicy::class,
        \App\Models\AnnouncementAttachment::class => \App\Policies\AnnouncementAttachmentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
