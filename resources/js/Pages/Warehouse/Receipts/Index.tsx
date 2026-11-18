import { CardTable, EmptyState, PageHeader, Pagination } from '@/Components/AppBits';
import { Button, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatNumber } from '@/lib/utils';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type ReceiptRow = {
    id: number;
    receipt_number: string;
    product: string | null;
    sku: string | null;
    brand: string | null;
    quantity: number;
    supplier: string | null;
    received_date: string | null;
    recorded_by: string | null;
    photo_url: string | null;
};

type ReceiptSlipRow = {
    id: number;
    mrs_number: string;
    brand: string | null;
    receipt_type: string;
    do_number: string;
    shipper: string | null;
    received_date: string;
    items_count: number;
    total_received: number;
    recorded_by: string | null;
};

type Paginator<T> = { data: T[]; links: any[]; [key: string]: any };

export default function ReceiptsIndex({
    slips,
    receipts,
    filters,
    brands,
}: {
    slips: Paginator<ReceiptSlipRow>;
    receipts: Paginator<ReceiptRow>;
    filters: { brand_id: number | null };
    brands: { id: number; name: string }[];
}) {
    const permissions = usePage().props.auth.permissions;
    const brandHref = (brandId: number | null) =>
        route('warehouse.receipts.index', brandId === null ? {} : { brand_id: brandId });

    return (
        <AppLayout title="MRS Barang Masuk">
            <Head title="MRS Barang Masuk" />
            <PageHeader
                title="Material Receipt Slip"
                subtitle="Dokumen penerimaan barang dan riwayat penambahan stok gudang"
            >
                {permissions.includes('warehouse.manage') && (
                    <Link href={route('warehouse.receipts.create')}>
                        <Button variant="primary">
                            <Plus className="h-4 w-4" /> Buat MRS
                        </Button>
                    </Link>
                )}
            </PageHeader>

            <div className="mb-5 flex flex-wrap gap-2" aria-label="Navigasi brand MRS">
                <Link
                    href={brandHref(null)}
                    preserveState
                    preserveScroll
                    className={`rounded-lg border px-4 py-2 text-sm font-semibold transition-colors ${
                        filters.brand_id === null
                            ? 'border-primary bg-primary text-white'
                            : 'border-border bg-surface text-muted hover:border-primary hover:text-primary'
                    }`}
                    aria-current={filters.brand_id === null ? 'page' : undefined}
                >
                    Semua Brand
                </Link>
                {brands.map((brand) => (
                    <Link
                        key={brand.id}
                        href={brandHref(brand.id)}
                        preserveState
                        preserveScroll
                        className={`rounded-lg border px-4 py-2 text-sm font-semibold transition-colors ${
                            filters.brand_id === brand.id
                                ? 'border-primary bg-primary text-white'
                                : 'border-border bg-surface text-muted hover:border-primary hover:text-primary'
                        }`}
                        aria-current={filters.brand_id === brand.id ? 'page' : undefined}
                    >
                        {brand.name}
                    </Link>
                ))}
            </div>

            <CardTable>
                {slips.data.length === 0 ? (
                    <EmptyState title="Belum ada MRS" description="Buat MRS untuk mencatat barang masuk ke gudang." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>No. MRS</TH>
                                    <TH>Brand</TH>
                                    <TH>Type</TH>
                                    <TH>DO Number</TH>
                                    <TH>Shipper / Courier</TH>
                                    <TH>Tanggal</TH>
                                    <TH className="text-right">Barang / Qty</TH>
                                    <TH>Dicatat Oleh</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {slips.data.map((slip) => (
                                    <TR key={slip.id}>
                                        <TD>
                                            <Link
                                                href={route('warehouse.receipts.show', slip.id)}
                                                className="font-semibold text-primary hover:underline"
                                            >
                                                {slip.mrs_number}
                                            </Link>
                                        </TD>
                                        <TD>{slip.brand ?? '-'}</TD>
                                        <TD>{slip.receipt_type}</TD>
                                        <TD>{slip.do_number}</TD>
                                        <TD>{slip.shipper ?? '-'}</TD>
                                        <TD>{formatDate(slip.received_date)}</TD>
                                        <TD className="text-right">
                                            {slip.items_count} barang · {formatNumber(slip.total_received)}
                                        </TD>
                                        <TD>{slip.recorded_by ?? '-'}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={slips} />
                    </>
                )}
            </CardTable>

            {receipts.data.length > 0 && (
                <section className="mt-8 space-y-3">
                    <h2 className="text-sm font-semibold text-text">Riwayat Penerimaan Sebelumnya</h2>
                    <CardTable>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>No. Terima</TH>
                                    <TH>Produk</TH>
                                    <TH>Brand</TH>
                                    <TH className="text-right">Jumlah</TH>
                                    <TH>Supplier</TH>
                                    <TH>Tanggal Terima</TH>
                                    <TH>Dicatat Oleh</TH>
                                    <TH>Foto</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {receipts.data.map((receipt) => (
                                    <TR key={receipt.id}>
                                        <TD className="font-medium text-text">{receipt.receipt_number}</TD>
                                        <TD>
                                            <p className="font-medium text-text">{receipt.product ?? '-'}</p>
                                            <p className="text-xs text-muted">{receipt.sku}</p>
                                        </TD>
                                        <TD>{receipt.brand ?? '-'}</TD>
                                        <TD className="text-right">{formatNumber(receipt.quantity)}</TD>
                                        <TD>{receipt.supplier ?? '-'}</TD>
                                        <TD>{formatDate(receipt.received_date)}</TD>
                                        <TD>{receipt.recorded_by ?? '-'}</TD>
                                        <TD>
                                            {receipt.photo_url ? (
                                                <a href={receipt.photo_url} target="_blank" rel="noreferrer" className="text-sm font-medium text-primary hover:underline">
                                                    Lihat Foto
                                                </a>
                                            ) : (
                                                <span className="text-xs text-muted">—</span>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={receipts} />
                    </CardTable>
                </section>
            )}
        </AppLayout>
    );
}
