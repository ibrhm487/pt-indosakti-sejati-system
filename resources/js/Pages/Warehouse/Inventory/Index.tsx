import { CardTable, EmptyState, FilterCard, PageHeader, SearchInput } from '@/Components/AppBits';
import { Button, Field, Input, Modal, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type InventoryRow = {
    id: number;
    name: string;
    sku: string;
    brand: string | null;
    category: string | null;
    unit: string;
    on_hand: number;
    reserved: number;
    available: number;
    minimum_stock: number;
    status: string;
};

export default function InventoryIndex({
    products,
    filters,
}: {
    products: InventoryRow[];
    filters: { search?: string };
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
        <AppLayout title="Stok Gudang">
            <Head title="Stok Gudang" />
            <PageHeader title="Stok Gudang" subtitle="Posisi stok terkini seluruh produk">
                <Link href={route('warehouse.inventory.movements')}>
                    <Button variant="outline">Riwayat Pergerakan</Button>
                </Link>
            </PageHeader>

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
                    <Table>
                        <THead>
                            <TR>
                                <TH>Produk</TH>
                                <TH>Kategori</TH>
                                <TH className="text-right">On Hand</TH>
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
                                    <TD className="text-right">{formatNumber(p.on_hand)}</TD>
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
