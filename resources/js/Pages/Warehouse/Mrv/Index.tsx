import { CardTable, EmptyState, FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import { Button, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type MrvRow = {
    id: number;
    mrv_number: string;
    event: string | null;
    requester: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
};

export default function MrvIndex({
    requests,
    filters,
    statuses,
}: {
    requests: { data: MrvRow[]; links: any[]; [k: string]: any };
    filters: { status?: string };
    statuses: { value: string; label: string }[];
}) {
    const [status, setStatus] = useState(filters.status ?? '');
    const permissions = usePage().props.auth.permissions;
    const canCreate = permissions.includes('warehouse.request');

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('warehouse.mrv.index'), { status }, { preserveState: true });
    };

    return (
        <AppLayout title="MRV">
            <Head title="MRV" />
            <PageHeader title="Permintaan Stok (MRV)" subtitle="Daftar Material Request Voucher">
                {canCreate && (
                    <Link href={route('warehouse.mrv.create')}>
                        <Button variant="primary">
                            <Plus className="h-4 w-4" /> MRV
                        </Button>
                    </Link>
                )}
            </PageHeader>

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
                    <EmptyState title="Belum ada MRV" description="Buat permintaan stok baru untuk event." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>No. MRV</TH>
                                    <TH>Event</TH>
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
                                                className="font-medium text-slate-800 hover:text-indigo-600"
                                            >
                                                {r.mrv_number}
                                            </Link>
                                        </TD>
                                        <TD>{r.event ?? '-'}</TD>
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
