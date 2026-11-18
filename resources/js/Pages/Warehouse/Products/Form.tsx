import ProductForm from '@/Components/ProductForm';
import { Head } from '@inertiajs/react';

export default function ProductFormPage({
    product,
    brands,
    categories,
}: {
    product: {
        id: number;
        name: string;
        description?: string | null;
        sku: string;
        package_sku?: string | null;
        product_type?: string | null;
        brand_id: number | null;
        product_category_id: number | null;
        unit: string;
        units_per_case?: number | null;
        default_lot_code?: string | null;
        minimum_stock: number | null;
        is_active: boolean;
    } | null;
    brands: { id: number; name: string }[];
    categories: { id: number; name: string }[];
}) {
    return (
        <>
            <Head title={product ? 'Ubah Produk' : 'Produk Baru'} />
            <ProductForm product={product} brands={brands} categories={categories} />
        </>
    );
}
