import { CardTable, EmptyState, FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import { Button, Input, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatNumber } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type OmfRow = {
    id: number;
    omf_number: string;
    issued_date: string | null;
    brand: string | null;
    department: string | null;
    recipient: string | null;
    items_count: number;
    total_quantity: number;
    recorded_by: string | null;
};

type Paginator<T> = { data: T[]; links: any[]; [key: string]: any };

export default function OmfIndex({
    forms,
    filters,
    brands,
}: {
    forms: Paginator<OmfRow>;
    filters: { search: string; brand_id: number | null };
    brands: { id: number; name: string }[];
}) {
    const [search, setSearch] = useState(filters.search);
    const permissions = usePage().props.auth.permissions;
    const brandHref = (brandId: number | null) =>
        route('warehouse.omf.index', {
            ...(search ? { search } : {}),
            ...(brandId !== null ? { brand_id: brandId } : {}),
        });
    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('warehouse.omf.index'),
            { search, brand_id: filters.brand_id ?? '' },
            { preserveState: true },
        );
    };

    return (
        <AppLayout title="OMF Barang Keluar">
            <Head title="OMF Barang Keluar" />
            <PageHeader
                title="Out Material Form"
                subtitle="Dokumen pengeluaran barang gudang dengan pemotongan stok per lot"
            >
                {permissions.includes('warehouse.manage') && (
                    <Link href={route('warehouse.omf.create')}>
                        <Button variant="primary">
                            <Plus className="h-4 w-4" /> Buat OMF
                        </Button>
                    </Link>
                )}
            </PageHeader>

            <div className="mb-5 flex flex-wrap gap-2" aria-label="Navigasi brand OMF">
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

            <FilterCard>
                <form onSubmit={applyFilters} className="flex flex-col gap-3 sm:flex-row">
                    <Input
                        aria-label="Cari nomor OMF, penerima, atau departemen"
                        className="sm:max-w-sm"
                        placeholder="Cari nomor, penerima, departemen..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {forms.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada OMF"
                        description="Buat OMF untuk mencatat barang keluar dari gudang."
                    />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>No. OMF</TH>
                                    <TH>Tanggal</TH>
                                    <TH>Brand</TH>
                                    <TH>Departemen</TH>
                                    <TH>Penerima</TH>
                                    <TH className="text-right">Barang / Qty</TH>
                                    <TH>Dicatat Oleh</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {forms.data.map((form) => (
                                    <TR key={form.id}>
                                        <TD>
                                            <Link
                                                href={route('warehouse.omf.show', form.id)}
                                                className="font-semibold text-primary hover:underline"
                                            >
                                                {form.omf_number}
                                            </Link>
                                        </TD>
                                        <TD>{formatDate(form.issued_date)}</TD>
                                        <TD>{form.brand ?? '-'}</TD>
                                        <TD>{form.department ?? '-'}</TD>
                                        <TD>{form.recipient ?? '-'}</TD>
                                        <TD className="text-right">
                                            {form.items_count} barang ·{' '}
                                            {formatNumber(form.total_quantity)}
                                        </TD>
                                        <TD>{form.recorded_by ?? '-'}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={forms} />
                    </>
                )}
            </CardTable>
        </AppLayout>
    );
}
