import ReceiptForm, { RecentMovement, ReceiptProduct } from '@/Components/ReceiptForm';
import { Head } from '@inertiajs/react';

export default function ReceiptFormPage({
    products,
    recentMovements,
}: {
    products: ReceiptProduct[];
    recentMovements?: RecentMovement[];
}) {
    return (
        <>
            <Head title="Barang Masuk Baru" />
            <ReceiptForm products={products} recentMovements={recentMovements} />
        </>
    );
}
