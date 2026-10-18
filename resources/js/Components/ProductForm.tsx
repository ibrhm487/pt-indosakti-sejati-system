import { Button, Card, CardContent, Field, Input, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, InertiaFormProps, useForm } from '@inertiajs/react';
import { Boxes, Package, Tags } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';

export type ProductOption = { id: number; name: string };

export type ProductFormData = {
    name: string;
    sku: string;
    brand_id: string;
    product_category_id: string;
    unit: string;
    minimum_stock: string;
    is_active: boolean;
};

export const EMPTY_PRODUCT_FORM: ProductFormData = {
    name: '',
    sku: '',
    brand_id: '',
    product_category_id: '',
    unit: 'pcs',
    minimum_stock: '0',
    is_active: true,
};

export function productToForm(p: {
    name: string;
    sku: string;
    brand_id: number | null;
    product_category_id: number | null;
    unit: string;
    minimum_stock: number | null;
    is_active: boolean;
}): ProductFormData {
    return {
        name: p.name,
        sku: p.sku,
        brand_id: p.brand_id ? String(p.brand_id) : '',
        product_category_id: p.product_category_id ? String(p.product_category_id) : '',
        unit: p.unit,
        minimum_stock: p.minimum_stock != null ? String(p.minimum_stock) : '0',
        is_active: p.is_active,
    };
}

function Section({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    {icon}
                </span>
                {title}
            </div>
            {children}
        </div>
    );
}

export function ProductFormFields({
    form,
    brands,
    categories,
}: {
    form: InertiaFormProps<ProductFormData>;
    brands: ProductOption[];
    categories: ProductOption[];
}) {
    const { data, setData, errors } = form;

    return (
        <div className="space-y-6">
            <Section icon={<Package className="h-3.5 w-3.5" />} title="Informasi Produk">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama Produk" error={errors.name}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </Field>
                    <Field label="SKU" error={errors.sku}>
                        <Input value={data.sku} onChange={(e) => setData('sku', e.target.value)} />
                    </Field>
                </div>
            </Section>

            <Section icon={<Tags className="h-3.5 w-3.5" />} title="Klasifikasi">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Brand" error={errors.brand_id}>
                        <Select value={data.brand_id} onChange={(e) => setData('brand_id', e.target.value)}>
                            <option value="">- Tanpa Brand -</option>
                            {brands.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Kategori" error={errors.product_category_id}>
                        <Select
                            value={data.product_category_id}
                            onChange={(e) => setData('product_category_id', e.target.value)}
                        >
                            <option value="">- Tanpa Kategori -</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
            </Section>

            <Section icon={<Boxes className="h-3.5 w-3.5" />} title="Stok & Status">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="Satuan" error={errors.unit}>
                        <Input value={data.unit} onChange={(e) => setData('unit', e.target.value)} />
                    </Field>
                    <Field label="Stok Minimum" error={errors.minimum_stock}>
                        <Input
                            type="number"
                            min={0}
                            value={data.minimum_stock}
                            onChange={(e) => setData('minimum_stock', e.target.value)}
                        />
                    </Field>
                    <Field label="Status Aktif">
                        <Select
                            value={data.is_active ? '1' : '0'}
                            onChange={(e) => setData('is_active', e.target.value === '1')}
                        >
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </Select>
                    </Field>
                </div>
            </Section>
        </div>
    );
}

export default function ProductForm({
    product,
    brands,
    categories,
}: {
    product: {
        id: number;
        name: string;
        sku: string;
        brand_id: number | null;
        product_category_id: number | null;
        unit: string;
        minimum_stock: number | null;
        is_active: boolean;
    } | null;
    brands: ProductOption[];
    categories: ProductOption[];
}) {
    const isEdit = !!product;
    const form = useForm<ProductFormData>(product ? productToForm(product) : EMPTY_PRODUCT_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit && product) {
            form.put(route('warehouse.products.update', product.id));
        } else {
            form.post(route('warehouse.products.store'));
        }
    };

    return (
        <AppLayout title={isEdit ? 'Ubah Produk' : 'Tambah Produk'}>
            <Head title={isEdit ? 'Ubah Produk' : 'Tambah Produk'} />
            <form onSubmit={submit}>
                <Card className="mb-6">
                    <CardContent className="pt-6">
                        <ProductFormFields form={form} brands={brands} categories={categories} />
                    </CardContent>
                </Card>

                <div className="flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={form.processing}>
                        {form.processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('warehouse.products.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
