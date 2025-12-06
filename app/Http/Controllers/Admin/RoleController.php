<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::withCount('users')->orderBy('name')->paginate(10)->withQueryString();
        return Inertia::render('Admin/Roles/Index', [ 'roles' => $roles ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Roles/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:50','unique:roles,name'],
            'label' => ['required','string','max:100'],
            'description' => ['nullable','string','max:255'],
            'domain_whitelist' => ['nullable','array'],
            'default_permissions' => ['nullable','array'],
        ]);
        Role::create($data);
        return redirect()->route('admin.roles.index')->with('success','Role dibuat');
    }

    public function show(Role $role)
    {
        $role->loadCount('users');
        $users = User::where('role_id', $role->id)->select('id','name','email')->paginate(10)->withQueryString();
        $units = \App\Models\OrganizationUnit::select('id','name','code')->orderBy('name')->get();
        return Inertia::render('Admin/Roles/Show', [ 'role' => $role, 'users' => $users, 'units' => $units ]);
    }

    public function edit(Role $role)
    {
        return Inertia::render('Admin/Roles/Edit', [ 'role' => $role ]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'label' => ['required','string','max:100'],
            'description' => ['nullable','string','max:255'],
            'domain_whitelist' => ['nullable','array'],
            'default_permissions' => ['nullable','array'],
        ]);
        $role->update($data);
        return redirect()->route('admin.roles.edit', $role)->with('success','Role diperbarui');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->with('error','Tidak dapat menghapus role yang masih dipakai');
        }
        $role->delete();
        return redirect()->route('admin.roles.index')->with('success','Role dihapus');
    }

    public function assign(Request $request, Role $role)
    {
        $rules = ['email' => ['required','email']];
        if ($role->name === 'admin_unit') {
            $rules['organization_unit_id'] = ['required','exists:organization_units,id'];
        }
        $data = $request->validate($rules);
        $user = User::where('email', $data['email'])->first();
        if (!$user) return back()->with('error','User tidak ditemukan');
        $user->role_id = $role->id;
        if ($role->name === 'admin_unit') {
            $user->organization_unit_id = (int) $data['organization_unit_id'];
        }
        $user->save();
        return back()->with('success','Role ditetapkan ke user');
    }
}
