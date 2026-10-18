<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()->with(['roles:id,slug,name', 'activeRole:id,slug,name']);

        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        if ($roleId = $request->integer('role_id')) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId));
        }

        $users = $query->latest()->paginate(15)->withQueryString()->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'active_role_id' => $user->active_role_id,
            'role_ids' => $user->roles->pluck('id'),
            'roles' => $user->roles->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name]),
            'active_role' => $user->activeRole?->name,
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'slug', 'name']),
            'filters' => $request->only('search', 'role_id'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => Role::orderBy('name')->get(['id', 'slug', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
            'active_role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'active_role_id' => $data['active_role_id'] ?? ($data['roles'][0] ?? null),
        ]);

        $user->roles()->sync($data['roles']);

        AuditLogger::log('CREATE_USER', 'admin', $user, null, ['email' => $user->email]);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna dibuat.');
    }

    public function edit(User $user): Response
    {
        $user->load('roles:id');

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'active_role_id' => $user->active_role_id,
                'roles' => $user->roles->pluck('id'),
            ],
            'roles' => Role::orderBy('name')->get(['id', 'slug', 'name']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
            'active_role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'active_role_id' => $data['active_role_id'] ?? ($data['roles'][0] ?? null),
        ];

        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $old = $user->only(['name', 'email', 'is_active', 'active_role_id']);
        $user->update($update);
        $user->roles()->sync($data['roles']);

        AuditLogger::log('UPDATE_USER', 'admin', $user, $old, ['name' => $user->name, 'roles' => $data['roles']]);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Tidak dapat menghapus akun sendiri.']);
        }

        AuditLogger::log('DELETE_USER', 'admin', $user, ['email' => $user->email]);
        $user->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }
}
