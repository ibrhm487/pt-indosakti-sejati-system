import { CardTable, EmptyState, FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import { Button, Select, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
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
    notes: string | null;
    by: string | null;
    at: string | null;
};

export default function InventoryMovements({
    movements,
    products,
    filters,
}: {
    movements: { data: MovementRow[]; links: any[]; [k: string]: any };
    products: { id: number; name: string; sku: string }[];
    filters: { product_id?: string; type?: string };
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
                                            <p className="font-medium text-slate-800">{m.product ?? '-'}</p>
                                            <p className="text-xs text-slate-400">{m.sku}</p>
                                        </TD>
                                        <TD>{m.type_label}</TD>
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
