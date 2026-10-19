import { CardTable, EmptyState, FilterCard, PageHeader, SearchInput } from '@/Components/AppBits';
import { Button, Field, Input, Modal, StatCard, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
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
    const [delta, setDelta] = useState('');
    const [reason, setReason] = useState('');
    const permissions = usePage().props.auth.permissions;
    const canManage = permissions.includes('warehouse.manage');

    const search_ = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('warehouse.inventory.index'), { search }, { preserveState: true });
    };

    const openAdjust = (row: InventoryRow) => {
        setTarget(row);
        setDelta('');
        setReason('');
    };

    const submitAdjust = (e: FormEvent) => {
        e.preventDefault();
        if (!target) return;
        router.post(
            route('warehouse.inventory.adjust'),
            { product_id: target.id, delta, reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTarget(null);
                },
            },
        );
    };

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
                                    {canManage && <TH className="text-right">Aksi</TH>}
                                </TR>
                            </THead>
                            <TBody>
                                {products.map((p) => (
                                    <TR key={p.id}>
                                        <TD>
                                            <p className="font-medium text-slate-800">{p.name}</p>
                                            <p className="text-xs text-slate-400">
                                                {p.sku} · {p.brand ?? '-'}
                                            </p>
                                        </TD>
                                        <TD>{p.category ?? '-'}</TD>
                                        <TD className="text-right text-emerald-700">
                                            {formatNumber(p.stock_in)}
                                        </TD>
                                        <TD className="text-right text-red-600">
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
                                        {canManage && (
                                            <TD className="text-right">
                                                <Button variant="outline" size="sm" onClick={() => openAdjust(p)}>
                                                    Sesuaikan
                                                </Button>
                                            </TD>
                                        )}
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
                        <Button type="button" variant="primary" size="sm" onClick={submitAdjust}>
                            Simpan
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitAdjust}>
                    <Field label="Perubahan (delta)">
                        <Input
                            type="number"
                            placeholder="cth: 10 atau -5"
                            value={delta}
                            onChange={(e) => setDelta(e.target.value)}
                        />
                    </Field>
                    <Field label="Alasan">
                        <Input value={reason} onChange={(e) => setReason(e.target.value)} />
                    </Field>
                </form>
            </Modal>
        </AppLayout>
    );
}
