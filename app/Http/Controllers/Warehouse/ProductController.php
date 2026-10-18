<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::query()->with(['brand:id,name', 'category:id,name', 'inventory']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->latest()->paginate(15)->withQueryString()->through(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'brand' => $product->brand?->name,
            'brand_id' => $product->brand_id,
            'category' => $product->category?->name,
            'product_category_id' => $product->product_category_id,
            'unit' => $product->unit,
            'minimum_stock' => $product->minimum_stock,
            'on_hand' => $product->inventory?->qty_on_hand ?? 0,
            'reserved' => $product->inventory?->qty_reserved ?? 0,
            'available' => $product->availableStock(),
            'status' => $product->stockStatus(),
            'is_active' => $product->is_active,
        ]);

        return Inertia::render('Warehouse/Products/Index', [
            'products' => $products,
            'filters' => $request->only('search'),
            'can' => ['manage' => $request->user()->hasPermission('warehouse.manage')],
            ...$this->formOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Warehouse/Products/Form', [
            'product' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $product = Product::create($data);

        AuditLogger::log('CREATE_PRODUCT', 'warehouse', $product, null, $data);

        return redirect()->route('warehouse.products.index')->with('success', 'Produk ditambahkan.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Warehouse/Products/Form', [
            'product' => $product,
            ...$this->formOptions(),
        ]);
    }

    /**
     * @return array{brands: Collection, categories: Collection}
     */
    private function formOptions(): array
    {
        return [
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'categories' => ProductCategory::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $old = $product->only(array_keys($data));
        $product->update($data);

        AuditLogger::log('UPDATE_PRODUCT', 'warehouse', $product, $old, $data);

        return redirect()->route('warehouse.products.index')->with('success', 'Produk diperbarui.');
    }

    public function destroy(Product $product)
    {
        if ($product->hasStockHistory()) {
            return back()->withErrors(['product' => 'Produk memiliki riwayat stok dan tidak dapat dihapus. Nonaktifkan saja produk ini.']);
        }

        AuditLogger::log('DELETE_PRODUCT', 'warehouse', $product);
        $product->delete();

        return back()->with('success', 'Produk dihapus.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product?->id)],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    public static function slugify(string $name): string
    {
        return Str::slug($name);
    }
}
