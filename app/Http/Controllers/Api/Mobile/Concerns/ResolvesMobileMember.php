<?php

namespace App\Http\Controllers\Api\Mobile\Concerns;

use App\Models\Member;
use App\Models\User;

trait ResolvesMobileMember
{
    protected function mobileMember(User $user): ?Member
    {
        if ($user->relationLoaded('linkedMember') && $user->linkedMember) {
            return $user->linkedMember;
        }

        if ($user->member_id) {
            return $user->linkedMember()->with(['unit', 'documents', 'statusLogs', 'unionPosition'])->first();
        }

        return $user->member()->with(['unit', 'documents', 'statusLogs', 'unionPosition'])->first();
    }
}
