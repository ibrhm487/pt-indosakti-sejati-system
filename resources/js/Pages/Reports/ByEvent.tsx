import { CardTable, EmptyState, FilterCard, PageHeader, Pagination, SearchInput } from '@/Components/AppBits';
import { Button, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatNumber } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type EventReport = {
    id: number;
    sampling_target: number;
    actual_sampling: number;
    achievement: number;
    status: string;
    status_label: string;
    submitted_by: string | null;
    verified_by: string | null;
};

type EventRow = {
    id: number;
    event_code: string;
    event_name: string;
    brand: string | null;
    venue: string | null;
    event_date: string | null;
    pic: string | null;
    sampling_target: number;
    report: EventReport | null;
};

type Paginator = {
    data: EventRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

export default function ReportsByEvent({
    events,
    filters,
    statuses,
}: {
    events: Paginator;
    filters: { search?: string; status?: string };
    statuses: { value: string; label: string }[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('reports.by-event'),
            { search: search || undefined, status: status || undefined },
            { preserveState: true },
        );
    };

    return (
        <AppLayout title="Laporan per Event">
            <Head title="Laporan per Event" />
            <PageHeader
                title="Laporan per Event"
                subtitle="Pantau status laporan terbaru untuk setiap event."
            >
                <Link href={route('reports.index')}>
                    <Button variant="outline">Riwayat Laporan</Button>
                </Link>
            </PageHeader>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        aria-label="Cari event"
                        placeholder="Cari nama atau kode event"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Select
                        aria-label="Filter status laporan"
                        className="sm:w-56"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">Semua status laporan</option>
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {events.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada event"
                        description="Event yang dapat Anda akses akan ditampilkan di sini."
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Event</TH>
                                        <TH>Tanggal</TH>
                                        <TH>Brand / Lokasi</TH>
                                        <TH>PIC</TH>
                                        <TH className="text-right">Target</TH>
                                        <TH className="text-right">Realisasi</TH>
                                        <TH className="text-right">Pencapaian</TH>
                                        <TH>Status Laporan</TH>
                                        <TH />
                                    </TR>
                                </THead>
                                <TBody>
                                    {events.data.map((event) => (
                                        <TR key={event.id}>
                                            <TD>
                                                <p className="font-medium text-slate-800">
                                                    {event.event_name}
                                                </p>
                                                <p className="text-xs text-slate-400">
                                                    {event.event_code}
                                                </p>
                                            </TD>
                                            <TD>{formatDate(event.event_date)}</TD>
                                            <TD>
                                                <p>{event.brand ?? '-'}</p>
                                                <p className="text-xs text-slate-400">
                                                    {event.venue ?? '-'}
                                                </p>
                                            </TD>
                                            <TD>{event.pic ?? '-'}</TD>
                                            <TD className="text-right">
                                                {formatNumber(
                                                    event.report?.sampling_target ?? event.sampling_target,
                                                )}
                                            </TD>
                                            <TD className="text-right">
                                                {event.report
                                                    ? formatNumber(event.report.actual_sampling)
                                                    : '-'}
                                            </TD>
                                            <TD className="text-right">
                                                {event.report && event.report.sampling_target > 0
                                                    ? `${event.report.achievement.toFixed(1)}%`
                                                    : '-'}
                                            </TD>
                                            <TD>
                                                {event.report ? (
                                                    <StatusBadge
                                                        status={event.report.status}
                                                        label={event.report.status_label}
                                                    />
                                                ) : (
                                                    <span className="text-sm text-slate-400">
                                                        Belum ada laporan
                                                    </span>
                                                )}
                                            </TD>
                                            <TD className="text-right">
                                                {event.report && (
                                                    <Link
                                                        href={route('reports.show', event.report.id)}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                                                    >
                                                        Detail
                                                    </Link>
                                                )}
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        </div>
                        <Pagination paginator={events} />
                    </>
                )}
            </CardTable>
        </AppLayout>
    );
}
