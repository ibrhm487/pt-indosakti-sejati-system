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
        sku: string;
        brand_id: number | null;
        product_category_id: number | null;
        unit: string;
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
