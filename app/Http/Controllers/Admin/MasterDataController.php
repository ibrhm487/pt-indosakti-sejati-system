<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VenueStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\EventType;
use App\Models\ProductCategory;
use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    /** @return array{model: class-string<Model>, slug: bool, rules: array<string, mixed>} */
    private function config(string $type): array
    {
        return match ($type) {
            'brands' => [
                'model' => Brand::class,
                'slug' => true,
                'rules' => [
                    'name' => ['required', 'string', 'max:255'],
                    'quarterly_target_cups' => ['nullable', 'integer', 'min:0'],
                    'is_active' => ['boolean'],
                ],
            ],
            'event-types' => [
                'model' => EventType::class,
                'slug' => true,
                'rules' => [
                    'brand_id' => ['nullable', 'exists:brands,id'],
                    'name' => ['required', 'string', 'max:255'],
                    'requires_school' => ['boolean'],
                    'kitchen_default' => ['boolean'],
                    'is_active' => ['boolean'],
                ],
            ],
            'venues' => [
                'model' => Venue::class,
                'slug' => false,
                'rules' => [
                    'name' => ['required', 'string', 'max:255'],
                    'npsn' => ['nullable', 'string', 'max:20'],
                    'is_school' => ['boolean'],
                    'address' => ['nullable', 'string', 'max:255'],
                    'city' => ['nullable', 'string', 'max:255'],
                    'province' => ['nullable', 'string', 'max:255'],
                    'contact_name' => ['nullable', 'string', 'max:255'],
                    'contact_phone' => ['nullable', 'string', 'max:30'],
                ],
            ],
            'categories' => [
                'model' => ProductCategory::class,
                'slug' => true,
                'rules' => ['name' => ['required', 'string', 'max:255']],
            ],
            default => abort(404),
        };
    }

    public function index(): Response
    {
        return Inertia::render('Admin/MasterData/Index', [
            'brands' => Brand::orderBy('name')->get(),
            'eventTypes' => EventType::with('brand:id,name')->orderBy('name')->get(),
            'venues' => Venue::orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $config = $this->config($type);
        $data = $request->validate($config['rules']);

        if ($config['slug']) {
            $data['slug'] = $this->uniqueSlug($config['model'], $data['name']);
        }

        if ($config['model'] === Venue::class) {
            $data['status'] = VenueStatus::APPROVED->value;
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
        }

        $record = $config['model']::create($data);

        AuditLogger::log('CREATE_MASTER_'.strtoupper($type), 'admin', $record, null, $data);

        return back()->with('success', 'Data master ditambahkan.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $config = $this->config($type);
        $record = $config['model']::findOrFail($id);
        $data = $request->validate($config['rules']);

        if ($config['slug'] && ($data['name'] ?? null) !== $record->name) {
            $data['slug'] = $this->uniqueSlug($config['model'], $data['name'], $record->id);
        }

        $old = $record->only(array_keys($data));
        $record->update($data);

        AuditLogger::log('UPDATE_MASTER_'.strtoupper($type), 'admin', $record, $old, $data);

        return back()->with('success', 'Data master diperbarui.');
    }

    public function destroy(string $type, int $id)
    {
        $config = $this->config($type);
        $record = $config['model']::findOrFail($id);

        AuditLogger::log('DELETE_MASTER_'.strtoupper($type), 'admin', $record);
        $record->delete();

        return back()->with('success', 'Data master dihapus.');
    }

    private function uniqueSlug(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while ($modelClass::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
