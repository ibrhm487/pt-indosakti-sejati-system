import { CardTable, EmptyState, FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import { Button, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type MrvRow = {
    id: number;
    mrv_number: string;
    brand: string | null;
    activity_type: string | null;
    activity_date: string | null;
    period: string;
    requester: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
};

export default function MrvIndex({
    requests,
    filters,
    brands,
    statuses,
}: {
    requests: { data: MrvRow[]; links: any[]; [k: string]: any };
    filters: { status?: string; brand_id?: number | null };
    brands: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
}) {
    const [status, setStatus] = useState(filters.status ?? '');
    const permissions = usePage().props.auth.permissions;
    const canCreate = permissions.includes('warehouse.request');
    const brandHref = (brandId: number | null) =>
        route('warehouse.mrv.index', {
            ...(status ? { status } : {}),
            ...(brandId !== null ? { brand_id: brandId } : {}),
        });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('warehouse.mrv.index'),
            { status, brand_id: filters.brand_id ?? '' },
            { preserveState: true },
        );
    };

    return (
        <AppLayout title="MRV">
            <Head title="MRV" />
            <PageHeader title="Permintaan Stok (MRV)" subtitle="Daftar pengajuan material per kegiatan">
                {canCreate && (
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('warehouse.mrv.create')}>
                            <Button variant="outline">
                                <Plus className="h-4 w-4" /> MRV satu tim
                            </Button>
                        </Link>
                        <Link href={route('warehouse.mrv.bulk.create')}>
                            <Button variant="primary">
                                <Plus className="h-4 w-4" /> MRV banyak tim
                            </Button>
                        </Link>
                    </div>
                )}
            </PageHeader>

            <div className="mb-5 flex flex-wrap gap-2" aria-label="Navigasi brand MRV">
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
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <Select className="sm:w-56" value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="">Semua status</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </Select>
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {requests.data.length === 0 ? (
                    <EmptyState title="Belum ada MRV" description="Buat pengajuan material untuk kegiatan." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>No. MRV</TH>
                                    <TH>Brand</TH>
                                    <TH>Jenis Kegiatan</TH>
                                    <TH>Tanggal Kegiatan</TH>
                                    <TH>Bulan / Minggu</TH>
                                    <TH>Pemohon</TH>
                                    <TH>Dibuat</TH>
                                    <TH>Status</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {requests.data.map((r) => (
                                    <TR key={r.id}>
                                        <TD>
                                            <Link
                                                href={route('warehouse.mrv.show', r.id)}
                                                className="font-medium text-text hover:text-primary"
                                            >
                                                {r.mrv_number}
                                            </Link>
                                        </TD>
                                        <TD>{r.brand ?? '-'}</TD>
                                        <TD>{r.activity_type ?? '-'}</TD>
                                        <TD>{formatDate(r.activity_date)}</TD>
                                        <TD>{r.period}</TD>
                                        <TD>{r.requester ?? '-'}</TD>
                                        <TD>{formatDateTime(r.created_at)}</TD>
                                        <TD>
                                            <StatusBadge status={r.status} label={r.status_label} />
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={requests} />
                    </>
                )}
            </CardTable>
        </AppLayout>
    );
}
