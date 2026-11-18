import { PageHeader } from '@/Components/AppBits';
import { Button, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import StockCardPrint, {
    StockCardData,
    useStockCardPdfDownload,
} from '@/Pages/Warehouse/Inventory/StockCardPrint';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';

export default function StockCardPage(props: StockCardData) {
    const card: StockCardData = props;
    const { downloadPdf, downloadingPdf } = useStockCardPdfDownload(card);

    const go = (next: { lot?: string; direction?: 'incoming' | 'outgoing' }) => {
        router.get(
            route('warehouse.inventory.stock-card', props.product.id),
            {
                lot: next.lot ?? props.selected_lot ?? '',
                direction: next.direction ?? props.direction,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout title={`Stock Card ${props.product.name}`}>
            <Head title={`Stock Card ${props.product.sku}`} />
            <div className="mrs-screen-content">
                <PageHeader
                    title={`Stock Card — ${props.product.name}`}
                    subtitle={`${props.product.sku} · Lot ${props.selected_lot ?? '-'}`}
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href={route('warehouse.inventory.index')}>
                            <Button variant="outline">
                                <ArrowLeft className="h-4 w-4" /> Kembali
                            </Button>
                        </Link>
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer className="h-4 w-4" /> Cetak
                        </Button>
                        <Button onClick={downloadPdf} disabled={downloadingPdf}>
                            <Download className="h-4 w-4" />
                            {downloadingPdf ? 'Menyiapkan PDF…' : 'Unduh PDF'}
                        </Button>
                    </div>
                </PageHeader>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant={props.direction === 'incoming' ? 'primary' : 'outline'}
                            onClick={() => go({ direction: 'incoming' })}
                        >
                            SC Incoming
                        </Button>
                        <Button
                            variant={props.direction === 'outgoing' ? 'primary' : 'outline'}
                            onClick={() => go({ direction: 'outgoing' })}
                        >
                            SC Outgoing
                        </Button>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-muted">
                        Lot
                        <Select
                            value={props.selected_lot ?? ''}
                            onChange={(event) => go({ lot: event.target.value })}
                            className="h-11 min-w-[14rem] rounded-xl"
                        >
                            {props.lots.length === 0 && <option value="">Belum ada lot</option>}
                            {props.lots.map((lot) => (
                                <option key={lot.lot_code} value={lot.lot_code}>
                                    {lot.lot_code}
                                    {lot.expired_date ? ` · exp ${lot.expired_date}` : ''}
                                </option>
                            ))}
                        </Select>
                    </label>
                </div>

                <p className="mb-3 text-sm text-muted">
                    {props.rows.length} baris pergerakan · total masuk {props.totals.incoming} {props.product.unit} ·
                    total keluar {props.totals.outgoing + props.totals.damage} {props.product.unit}
                </p>
                <h2 className="mb-3 text-sm font-semibold text-text">Pratinjau lembar cetak</h2>
            </div>
            <StockCardPrint card={card} />
        </AppLayout>
    );
}
