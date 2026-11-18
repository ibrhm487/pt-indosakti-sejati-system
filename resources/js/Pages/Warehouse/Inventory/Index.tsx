import { CardTable, EmptyState, FilterCard, PageHeader, SearchInput } from '@/Components/AppBits';
import { Button, Field, Input, Modal, Select, StatCard, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { PackageSearch, PackageX, Truck } from 'lucide-react';
import { FormEvent, useState } from 'react';

type InventoryRow = {
    id: number;
    name: string;
    sku: string;
    brand: string | null;
    category: string | null;
    unit: string;
    stock_in: number;
    stock_out: number;
    on_hand: number;
    reserved: number;
    available: number;
    minimum_stock: number;
    status: string;
    lots: { lot_code: string; quantity: number; expired_date: string | null }[];
};

type AdjustmentFormData = {
    product_id: number | null;
    lot_code: string;
    actual_quantity: string;
    reason: string;
};

export default function InventoryIndex({
    products,
    filters,
    summary,
}: {
    products: InventoryRow[];
    filters: { search?: string };
    summary: {
        total_products: number;
        low_stock: number;
        out_of_stock: number;
        mrv_pending: number | null;
    };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [target, setTarget] = useState<InventoryRow | null>(null);
    const adjustmentForm = useForm<AdjustmentFormData>({
        product_id: null,
        lot_code: '',
        actual_quantity: '',
        reason: '',
    });
    const permissions = usePage().props.auth.permissions;
    const canManage = permissions.includes('warehouse.manage');

    const search_ = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('warehouse.inventory.index'), { search }, { preserveState: true });
    };

    const openAdjust = (row: InventoryRow) => {
        setTarget(row);
        const firstLot = row.lots[0];
        adjustmentForm.setData({
            product_id: row.id,
            lot_code: firstLot?.lot_code ?? '',
            actual_quantity: firstLot ? String(firstLot.quantity) : '',
            reason: '',
        });
        adjustmentForm.clearErrors();
    };

    const submitAdjust = (e: FormEvent) => {
        e.preventDefault();
        if (!target) return;
        adjustmentForm.post(route('warehouse.inventory.adjust'), {
            preserveScroll: true,
            onSuccess: () => setTarget(null),
        });
    };

    const selectedLot = target?.lots.find((lot) => lot.lot_code === adjustmentForm.data.lot_code);

    return (
        <AppLayout title="Kontrol Stok & MRV">
            <Head title="Kontrol Stok & MRV" />
            <PageHeader
                title="Kontrol Stok & MRV"
                subtitle="Saldo stok, permintaan MRV, dan pergerakan barang per produk"
            >
                <Link href={route('warehouse.inventory.movements')}>
                    <Button variant="outline">Riwayat Pergerakan</Button>
                </Link>
            </PageHeader>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total Produk"
                    value={formatNumber(summary.total_products)}
                    icon={<PackageSearch className="h-5 w-5" />}
                    tone="info"
                />
                <StatCard
                    label="Stok Menipis"
                    value={formatNumber(summary.low_stock)}
                    icon={<PackageX className="h-5 w-5" />}
                    tone={summary.low_stock > 0 ? 'warning' : 'default'}
                />
                <StatCard
                    label="Stok Habis"
                    value={formatNumber(summary.out_of_stock)}
                    icon={<PackageX className="h-5 w-5" />}
                    tone={summary.out_of_stock > 0 ? 'danger' : 'default'}
                />
                {summary.mrv_pending !== null && (
                    <StatCard
                        label="MRV Menunggu"
                        value={formatNumber(summary.mrv_pending)}
                        hint="Perlu persetujuan gudang"
                        icon={<Truck className="h-5 w-5" />}
                        tone={summary.mrv_pending > 0 ? 'warning' : 'default'}
                    />
                )}
            </div>

            <FilterCard>
                <form onSubmit={search_} className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        placeholder="Cari nama atau SKU…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {products.length === 0 ? (
                    <EmptyState title="Belum ada produk" />
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Produk</TH>
                                    <TH>Kategori</TH>
                                    <TH className="text-right">Stok Masuk</TH>
                                    <TH className="text-right">Stok Keluar</TH>
                                    <TH className="text-right">Saldo</TH>
                                    <TH className="text-right">Reserved</TH>
                                    <TH className="text-right">Tersedia</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {products.map((p) => (
                                    <TR key={p.id}>
                                        <TD>
                                            <p className="font-medium text-text">{p.name}</p>
                                            <p className="text-xs text-muted">
                                                {p.sku} · {p.brand ?? '-'}
                                            </p>
                                        </TD>
                                        <TD>{p.category ?? '-'}</TD>
                                        <TD className="text-right text-success">
                                            {formatNumber(p.stock_in)}
                                        </TD>
                                        <TD className="text-right text-danger">
                                            {formatNumber(p.stock_out)}
                                        </TD>
                                        <TD className="text-right font-medium">{formatNumber(p.on_hand)}</TD>
                                        <TD className="text-right">{formatNumber(p.reserved)}</TD>
                                        <TD className="text-right font-medium">{formatNumber(p.available)}</TD>
                                        <TD>
                                            <StatusBadge
                                                status={p.status}
                                                label={
                                                    p.status === 'out_of_stock'
                                                        ? 'Habis'
                                                        : p.status === 'low_stock'
                                                          ? 'Menipis'
                                                          : 'Tersedia'
                                                }
                                            />
                                        </TD>
                                        <TD className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Link href={route('warehouse.inventory.stock-card', p.id)}>
                                                    <Button variant="outline" size="sm">
                                                        Stock Card
                                                    </Button>
                                                </Link>
                                                {canManage && (
                                                    <Button variant="outline" size="sm" onClick={() => openAdjust(p)}>
                                                        Sesuaikan
                                                    </Button>
                                                )}
                                            </div>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </div>
                )}
            </CardTable>

            <Modal
                open={!!target}
                onClose={() => setTarget(null)}
                title={`Sesuaikan Stok${target ? ` — ${target.name}` : ''}`}
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setTarget(null)}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="inventory-adjust-form"
                            variant="primary"
                            size="sm"
                            disabled={adjustmentForm.processing || !target?.lots.length}
                        >
                            {adjustmentForm.processing ? 'Menyimpan…' : 'Simpan penyesuaian'}
                        </Button>
                    </>
                }
            >
                <form id="inventory-adjust-form" onSubmit={submitAdjust} className="space-y-3">
                    {target?.lots.length ? (
                        <>
                            <Field label="Lot Code" error={adjustmentForm.errors.lot_code}>
                                <Select
                                    value={adjustmentForm.data.lot_code}
                                    onChange={(event) => {
                                        const lot = target.lots.find((item) => item.lot_code === event.target.value);
                                        adjustmentForm.setData({
                                            ...adjustmentForm.data,
                                            lot_code: event.target.value,
                                            actual_quantity: lot ? String(lot.quantity) : '',
                                        });
                                    }}
                                >
                                    {target.lots.map((lot) => (
                                        <option key={lot.lot_code} value={lot.lot_code}>
                                            {lot.lot_code} · saldo {formatNumber(lot.quantity)}
                                            {lot.expired_date ? ` · ED ${lot.expired_date}` : ''}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <p className="text-xs text-muted">
                                Saldo lot saat ini: {formatNumber(selectedLot?.quantity ?? 0)} {target.unit}.
                                Penyesuaian akan memperbarui saldo produk dan tetap tercatat pada lot ini.
                            </p>
                            <Field label={`Saldo fisik aktual (${target.unit})`} error={adjustmentForm.errors.actual_quantity}>
                                <Input
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={adjustmentForm.data.actual_quantity}
                                    onChange={(event) => adjustmentForm.setData('actual_quantity', event.target.value)}
                                    required
                                />
                            </Field>
                        </>
                    ) : (
                        <p className="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-text">
                            Produk ini belum memiliki lot pada riwayat stok. Catat penerimaan melalui MRS terlebih dahulu sebelum melakukan penyesuaian.
                        </p>
                    )}
                    <Field label="Alasan" error={adjustmentForm.errors.reason}>
                        <Input
                            value={adjustmentForm.data.reason}
                            onChange={(event) => adjustmentForm.setData('reason', event.target.value)}
                            required
                        />
                    </Field>
                </form>
            </Modal>
        </AppLayout>
    );
}
