import { CardTable, EmptyState, FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import { Button, Card, CardContent, CardHeader, CardTitle, Select, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatNumber } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type MovementRow = {
    id: number;
    product: string | null;
    sku: string | null;
    type: string;
    type_label: string;
    quantity: number;
    balance_after: number;
    lot_code: string | null;
    expired_date: string | null;
    notes: string | null;
    by: string | null;
    at: string | null;
};

type MovementSummary = {
    product: string;
    sku: string;
    unit: string;
    opening: number | null;
    stock_in: number;
    stock_out: number;
    returned: number;
    damaged: number;
    adjustments: number;
    on_hand: number;
    available: number;
};

export default function InventoryMovements({
    movements,
    products,
    filters,
    summary,
}: {
    movements: { data: MovementRow[]; links: any[]; [k: string]: any };
    products: { id: number; name: string; sku: string }[];
    filters: { product_id?: string; type?: string };
    summary: MovementSummary | null;
}) {
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : '');
    const [type, setType] = useState(filters.type ?? '');

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('warehouse.inventory.movements'),
            { product_id: productId, type },
            { preserveState: true },
        );
    };

    return (
        <AppLayout title="Pergerakan Stok">
            <Head title="Pergerakan Stok" />
            <PageHeader title="Pergerakan Stok" subtitle="Riwayat barang masuk, keluar, dan penyesuaian" />

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <Select
                        className="sm:w-72"
                        value={productId}
                        onChange={(e) => setProductId(e.target.value)}
                    >
                        <option value="">Semua produk</option>
                        {products.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name} ({p.sku})
                            </option>
                        ))}
                    </Select>
                    <Select className="sm:w-48" value={type} onChange={(e) => setType(e.target.value)}>
                        <option value="">Semua jenis</option>
                        <option value="stock_in">Masuk</option>
                        <option value="stock_out">Keluar</option>
                        <option value="return">Pengembalian</option>
                        <option value="damage">Rusak</option>
                        <option value="adjustment">Penyesuaian</option>
                    </Select>
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            {summary && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>
                            Rekap Mutasi — {summary.product} ({summary.sku})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-7">
                            <div>
                                <p className="text-xs text-muted">Stok Awal</p>
                                <p className="mt-1 text-lg font-semibold text-text">
                                    {summary.opening === null ? '—' : formatNumber(summary.opening)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">Barang Masuk</p>
                                <p className="mt-1 text-lg font-semibold text-success">+{formatNumber(summary.stock_in)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">Barang Keluar</p>
                                <p className="mt-1 text-lg font-semibold text-danger">-{formatNumber(summary.stock_out)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">Pengembalian</p>
                                <p className="mt-1 text-lg font-semibold text-success">+{formatNumber(summary.returned)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">Rusak</p>
                                <p className="mt-1 text-lg font-semibold text-warning">{formatNumber(summary.damaged)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">Penyesuaian</p>
                                <p className="mt-1 text-lg font-semibold text-text">{formatNumber(summary.adjustments)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">Saldo Saat Ini</p>
                                <p className="mt-1 text-lg font-semibold text-text">
                                    {formatNumber(summary.on_hand)} {summary.unit}
                                </p>
                            </div>
                        </div>
                        <p className="mt-3 text-xs text-muted">
                            Stok awal dihitung dari saldo mutasi pertama produk; tampilkan “—” bila derivasi tidak tersedia.
                            Saldo adalah sumber kebenaran dari database, bukan perhitungan frontend.
                        </p>
                    </CardContent>
                </Card>
            )}

            <CardTable>
                {movements.data.length === 0 ? (
                    <EmptyState title="Belum ada pergerakan stok" />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Waktu</TH>
                                    <TH>Produk</TH>
                                    <TH>Jenis</TH>
                                    <TH>Lot</TH>
                                    <TH className="text-right">Jumlah</TH>
                                    <TH className="text-right">Saldo</TH>
                                    <TH>Oleh</TH>
                                    <TH>Catatan</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {movements.data.map((m) => (
                                    <TR key={m.id}>
                                        <TD>{formatDateTime(m.at)}</TD>
                                        <TD>
                                            <p className="font-medium text-text">{m.product ?? '-'}</p>
                                            <p className="text-xs text-muted">{m.sku}</p>
                                        </TD>
                                        <TD>{m.type_label}</TD>
                                        <TD>
                                            <p className="font-medium text-text">{m.lot_code ?? '-'}</p>
                                            {m.expired_date && (
                                                <p className="text-xs text-muted">exp {m.expired_date}</p>
                                            )}
                                        </TD>
                                        <TD className="text-right">{formatNumber(m.quantity)}</TD>
                                        <TD className="text-right">{formatNumber(m.balance_after)}</TD>
                                        <TD>{m.by ?? '-'}</TD>
                                        <TD className="max-w-xs truncate">{m.notes ?? '-'}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={movements} />
                    </>
                )}
            </CardTable>
        </AppLayout>
    );
}
