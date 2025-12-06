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
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
