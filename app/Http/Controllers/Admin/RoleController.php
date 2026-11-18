<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->with('permissions:id,slug')
            ->withCount(['permissions', 'users'])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'permissions' => $role->permissions->pluck('slug')->values(),
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissionGroups' => Permissions::grouped(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRole($request);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($this->permissionIds($data['permissions']));

        AuditLogger::log('CREATE_ROLE', 'admin', $role, null, [
            'slug' => $role->slug,
            'permissions' => $data['permissions'],
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role dibuat.');
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah.');

        $data = $this->validateRole($request, $role);

        $old = [
            'name' => $role->name,
            'slug' => $role->slug,
            'permissions' => $role->permissions()->pluck('slug')->all(),
        ];

        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($this->permissionIds($data['permissions']));

        AuditLogger::log('UPDATE_ROLE', 'admin', $role, $old, [
            'name' => $role->name,
            'slug' => $role->slug,
            'permissions' => $data['permissions'],
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role diperbarui.');
    }

    public function destroy(Request $request, Role $role)
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat dihapus.');

        if ($role->id === $request->user()->active_role_id) {
            return back()->withErrors(['role' => 'Tidak dapat menghapus role yang sedang aktif.']);
        }

        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            return back()->withErrors([
                'role' => "Role masih digunakan oleh {$usersCount} pengguna. Pindahkan pengguna terlebih dahulu.",
            ]);
        }

        AuditLogger::log('DELETE_ROLE', 'admin', $role, ['slug' => $role->slug, 'name' => $role->name]);

        $role->permissions()->detach();
        $role->delete();

        return back()->with('success', 'Role dihapus.');
    }

    /**
     * @return array{name: string, slug: string, description?: string|null, permissions: array<int, string>}
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:100',
                'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/',
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ], [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, tanda hubung, dan garis bawah.',
        ]);
    }

    /**
     * @param  array<int, string>  $slugs
     * @return array<int, int>
     */
    private function permissionIds(array $slugs): array
    {
        return Permission::whereIn('slug', $slugs)->pluck('id')->all();
    }
}
