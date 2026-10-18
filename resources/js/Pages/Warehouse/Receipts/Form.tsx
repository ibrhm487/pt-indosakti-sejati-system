import ReceiptForm, { ReceiptProduct } from '@/Components/ReceiptForm';

export default function ReceiptFormPage({ products }: { products: ReceiptProduct[] }) {
    return <ReceiptForm products={products} />;
}
