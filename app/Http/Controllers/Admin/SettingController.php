<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ShiftRate;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /** Known settings keys with labels and defaults. */
    private const KEYS = [
        'company_name' => 'Nama Perusahaan',
        'company_address' => 'Alamat',
        'report_deadline_days' => 'Tenggat laporan (hari)',
        'low_stock_threshold' => 'Ambang peringatan stok',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => collect(self::KEYS)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'value' => Setting::get($key, ''),
            ])->values(),
            'shiftRates' => ShiftRate::orderByDesc('is_active')->latest()->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'in:'.implode(',', array_keys(self::KEYS))],
            'settings.*.value' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data['settings'] as $setting) {
            Setting::set($setting['key'], $setting['value'] ?? '');
        }

        AuditLogger::log('UPDATE_SETTINGS', 'admin', null, null, ['keys' => array_column($data['settings'], 'key')]);

        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function storeShiftRate(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'integer', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        if ($data['is_active'] ?? false) {
            ShiftRate::query()->update(['is_active' => false]);
        }

        $rate = ShiftRate::create($data);

        AuditLogger::log('CREATE_SHIFT_RATE', 'admin', $rate, null, $data);

        return back()->with('success', 'Tarif shift ditambahkan.');
    }

    public function updateShiftRate(Request $request, ShiftRate $shiftRate)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'integer', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        if ($data['is_active'] ?? false) {
            ShiftRate::query()->where('id', '!=', $shiftRate->id)->update(['is_active' => false]);
        }

        $old = $shiftRate->only(array_keys($data));
        $shiftRate->update($data);

        AuditLogger::log('UPDATE_SHIFT_RATE', 'admin', $shiftRate, $old, $data);

        return back()->with('success', 'Tarif shift diperbarui.');
    }

    public function destroyShiftRate(ShiftRate $shiftRate)
    {
        AuditLogger::log('DELETE_SHIFT_RATE', 'admin', $shiftRate, ['label' => $shiftRate->label]);
        $shiftRate->delete();

        return back()->with('success', 'Tarif shift dihapus.');
    }
}
