<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonnelController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Personnel::query();

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $personnel = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Personnel/Index', [
            'personnel' => $personnel,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        $personnel = Personnel::create($data);

        AuditLogger::log('CREATE_PERSONNEL', 'admin', $personnel, null, $data);

        return back()->with('success', 'Personel ditambahkan.');
    }

    public function update(Request $request, Personnel $personnel)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        $old = $personnel->only(array_keys($data));
        $personnel->update($data);

        AuditLogger::log('UPDATE_PERSONNEL', 'admin', $personnel, $old, $data);

        return back()->with('success', 'Personel diperbarui.');
    }

    public function destroy(Personnel $personnel)
    {
        AuditLogger::log('DELETE_PERSONNEL', 'admin', $personnel, ['name' => $personnel->name]);
        $personnel->delete();

        return back()->with('success', 'Personel dihapus.');
    }
}
