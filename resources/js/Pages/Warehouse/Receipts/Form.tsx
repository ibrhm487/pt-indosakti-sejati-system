import ReceiptForm, { RecentMovement, ReceiptProduct } from '@/Components/ReceiptForm';

export default function ReceiptFormPage({
    products,
    recentMovements,
}: {
    products: ReceiptProduct[];
    recentMovements?: RecentMovement[];
}) {
    return <ReceiptForm products={products} recentMovements={recentMovements} />;
}
