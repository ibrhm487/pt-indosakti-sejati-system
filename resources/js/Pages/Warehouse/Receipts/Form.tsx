import ReceiptForm, { RecentMovement, ReceiptProduct } from '@/Components/ReceiptForm';

export default function ReceiptFormPage({
    products,
    mrsNumber,
    today,
    recentMovements,
}: {
    products: ReceiptProduct[];
    mrsNumber: string;
    today: string;
    recentMovements?: RecentMovement[];
}) {
    return <ReceiptForm products={products} mrsNumber={mrsNumber} today={today} recentMovements={recentMovements} />;
}
