import {
    CardTable,
    ConfirmButton,
    EmptyState,
    FilterCard,
    PageHeader,
    Pagination,
    SearchInput,
} from '@/Components/AppBits';
import {
    EMPTY_PRODUCT_FORM,
    ProductFormData,
    ProductFormFields,
    ProductOption,
    productToForm,
} from '@/Components/ProductForm';
import { Button, Modal, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type ProductRow = {
    id: number;
    name: string;
    sku: string;
    package_sku: string | null;
    product_type: string | null;
    brand: string | null;
    brand_id: number | null;
    category: string | null;
    product_category_id: number | null;
    unit: string;
    units_per_case: number | null;
    default_lot_code: string | null;
    minimum_stock: number;
    on_hand: number;
    reserved: number;
    available: number;
    status: string;
    is_active: boolean;
};

export default function ProductsIndex({
    products,
    filters,
    brands,
    categories,
}: {
    products: { data: ProductRow[]; links: any[]; [k: string]: any };
    filters: { search?: string };
    brands: ProductOption[];
    categories: ProductOption[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const permissions = usePage().props.auth.permissions;
    const canManage = permissions.includes('warehouse.manage');

    const form = useForm<ProductFormData>(EMPTY_PRODUCT_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('warehouse.products.index'), { search }, { preserveState: true });
    };

    const remove = (id: number) => {
        router.delete(route('warehouse.products.destroy', id), { preserveScroll: true });
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(EMPTY_PRODUCT_FORM);
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (row: ProductRow) => {
        setEditingId(row.id);
        form.setData(productToForm(row));
        form.clearErrors();
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setEditingId(null);
        form.reset();
        form.clearErrors();
    };

    const submitForm = (e: FormEvent) => {
        e.preventDefault();
        if (editingId) {
            form.put(route('warehouse.products.update', editingId), { onSuccess: closeForm });
        } else {
            form.post(route('warehouse.products.store'), { onSuccess: closeForm });
        }
    };

    return (
        <AppLayout title="Produk">
            <Head title="Produk" />
            <PageHeader title="Produk" subtitle="Master produk & stok gudang">
                {canManage && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Produk
                    </Button>
                )}
            </PageHeader>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        placeholder="Cari nama, item number, atau SKU…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {products.data.length === 0 ? (
                    <EmptyState title="Belum ada produk" description="Tambahkan produk untuk mulai mengelola stok." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Produk</TH>
                                    <TH>Kategori</TH>
                                    <TH className="text-right">Stok</TH>
                                    <TH className="text-right">Tersedia</TH>
                                    <TH>Status</TH>
                                    {canManage && <TH className="text-right">Aksi</TH>}
                                </TR>
                            </THead>
                            <TBody>
                                {products.data.map((p) => (
                                    <TR key={p.id}>
                                        <TD>
                                            <p className="font-medium text-text">{p.name}</p>
                                            <p className="text-xs text-muted">
                                                Item {p.sku}
                                                {p.package_sku ? ` · SKU ${p.package_sku}` : ''}
                                                {' · '}{p.brand ?? '-'}
                                            </p>
                                            {p.product_type && (
                                                <p className="text-xs text-muted">
                                                    Type {p.product_type}
                                                </p>
                                            )}
                                            {p.units_per_case && (
                                                <p className="text-xs text-muted">
                                                    {formatNumber(p.units_per_case)} {p.unit} / case
                                                </p>
                                            )}
                                            {p.default_lot_code && (
                                                <p className="text-xs text-muted">Lot {p.default_lot_code}</p>
                                            )}
                                        </TD>
                                        <TD>{p.category ?? '-'}</TD>
                                        <TD className="text-right">
                                            {formatNumber(p.on_hand)} {p.unit}
                                            <span className="block text-xs text-muted">
                                                min {formatNumber(p.minimum_stock)}
                                            </span>
                                        </TD>
                                        <TD className="text-right">{formatNumber(p.available)}</TD>
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
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="outline" size="sm" onClick={() => openEdit(p)}>
                                                        Ubah
                                                    </Button>
                                                    <ConfirmButton
                                                        onConfirm={() => remove(p.id)}
                                                        title="Hapus produk"
                                                        description={`Hapus "${p.name}"?`}
                                                    >
                                                        Hapus
                                                    </ConfirmButton>
                                                </div>
                                            </TD>
                                        )}
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={products} />
                    </>
                )}
            </CardTable>

            <Modal
                open={formOpen}
                onClose={closeForm}
                size="lg"
                title={editingId ? 'Ubah Produk' : 'Tambah Produk'}
                description="Kelola informasi master produk gudang."
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={closeForm}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="product-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="product-form" onSubmit={submitForm}>
                    <ProductFormFields form={form} brands={brands} categories={categories} />
                </form>
            </Modal>
        </AppLayout>
    );
}
