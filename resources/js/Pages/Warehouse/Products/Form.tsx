import ProductForm from '@/Components/ProductForm';

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
    return <ProductForm product={product} brands={brands} categories={categories} />;
}
