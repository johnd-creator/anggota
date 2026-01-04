<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class UserController extends Controller
{
    public function show(User $user)
    {
        Gate::authorize('view', $user);

        $user->load(['role', 'linkedMember.unit', 'organizationUnit']);

        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ? [
                'name' => $user->role->name,
                'label' => $user->role->label,
            ] : null,
            'organization_unit' => $user->organizationUnit ? [
                'id' => $user->organizationUnit->id,
                'name' => $user->organizationUnit->name,
            ] : null,
            'member' => $user->linkedMember ? [
                'id' => $user->linkedMember->id,
                'full_name' => $user->linkedMember->full_name,
                'kta_number' => $user->linkedMember->kta_number,
                'unit' => $user->linkedMember->unit ? [
                    'id' => $user->linkedMember->unit->id,
                    'name' => $user->linkedMember->unit->name,
                ] : null,
            ] : null,
            'created_at' => $user->created_at?->toIsoString(),
        ];

        return Inertia::render('Admin/Users/Show', [
            'user' => $payload,
        ]);
    }
}

