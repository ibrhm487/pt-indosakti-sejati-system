<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonnelController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Personnel::query()->with('user:id,name,email');

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $personnel = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Personnel/Index', [
            'personnel' => $personnel,
            'teamUsers' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('slug', 'team'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPersonnel($request);

        $personnel = Personnel::create($data);

        AuditLogger::log('CREATE_PERSONNEL', 'admin', $personnel, null, $data);

        return back()->with('success', 'Personel ditambahkan.');
    }

    public function update(Request $request, Personnel $personnel): RedirectResponse
    {
        $data = $this->validatedPersonnel($request, $personnel);

        $old = $personnel->only(array_keys($data));
        $personnel->update($data);

        AuditLogger::log('UPDATE_PERSONNEL', 'admin', $personnel, $old, $data);

        return back()->with('success', 'Personel diperbarui.');
    }

    public function destroy(Personnel $personnel): RedirectResponse
    {
        AuditLogger::log('DELETE_PERSONNEL', 'admin', $personnel, ['name' => $personnel->name]);
        $personnel->delete();

        return back()->with('success', 'Personel dihapus.');
    }

    /**
     * @return array{name: string, role: string, phone?: string|null, is_active?: bool, user_id?: int|null}
     */
    private function validatedPersonnel(Request $request, ?Personnel $personnel = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('personnel', 'user_id')->ignore($personnel?->id),
            ],
        ]);

        if (isset($data['user_id']) && ! User::query()
            ->whereKey($data['user_id'])
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'team'))
            ->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'Pilih akun role Tim yang aktif.',
            ]);
        }

        return $data;
    }
}
