import { EmptyState, FilterCard, PageHeader, SearchInput } from '@/Components/AppBits';
import { EventBrandGroup, EventGroupView } from '@/Components/EventGroupView';
import { Button, Input, Select, StatCard, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarCheck, CircleCheck, CircleDashed, Clock, ShieldCheck } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function ReportsByEvent({
    eventGroups,
    filters,
    summary,
    statuses,
}: {
    eventGroups: EventBrandGroup[];
    filters: {
        search?: string;
        status?: string;
        date_from?: string;
        date_to?: string;
    };
    summary: {
        total: number;
        reported: number;
        missing: number;
        submitted: number;
        revision: number;
        verified: number;
    };
    statuses: { value: string; label: string }[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('reports.by-event'),
            {
                search: search || undefined,
                status: status || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true },
        );
    };

    const reset = () => {
        setSearch('');
        setStatus('');
        setDateFrom('');
        setDateTo('');
        router.get(route('reports.by-event'), {}, { preserveState: true });
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

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    label="Total Event"
                    value={formatNumber(summary.total)}
                    icon={<CalendarCheck className="h-5 w-5" />}
                    tone="info"
                />
                <StatCard
                    label="Dengan Laporan"
                    value={formatNumber(summary.reported)}
                    icon={<CircleCheck className="h-5 w-5" />}
                    tone="success"
                />
                <StatCard
                    label="Belum Ada Laporan"
                    value={formatNumber(summary.missing)}
                    icon={<CircleDashed className="h-5 w-5" />}
                    tone="warning"
                />
                <StatCard
                    label="Menunggu Verifikasi"
                    value={formatNumber(summary.submitted + summary.revision)}
                    hint={`${summary.revision} perlu revisi`}
                    icon={<Clock className="h-5 w-5" />}
                />
                <StatCard
                    label="Terverifikasi"
                    value={formatNumber(summary.verified)}
                    icon={<ShieldCheck className="h-5 w-5" />}
                    tone="success"
                />
            </div>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-end">
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
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Dari Tanggal
                        <Input
                            type="date"
                            className="sm:w-44"
                            value={dateFrom}
                            onChange={(event) => setDateFrom(event.target.value)}
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Sampai Tanggal
                        <Input
                            type="date"
                            className="sm:w-44"
                            value={dateTo}
                            onChange={(event) => setDateTo(event.target.value)}
                        />
                    </label>
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11 rounded-xl px-5"
                        onClick={reset}
                    >
                        Reset
                    </Button>
                </form>
            </FilterCard>

            <EventGroupView
                groups={eventGroups}
                empty={
                    <EmptyState
                        title="Belum ada event"
                        description="Event yang dapat Anda akses akan ditampilkan di sini."
                    />
                }
                renderTable={(rows, type) => (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Event</TH>
                                <TH>{type.requires_school ? 'Sekolah' : 'Venue'}</TH>
                                <TH>PIC</TH>
                                <TH className="text-right">Target</TH>
                                <TH className="text-right">Realisasi</TH>
                                <TH className="text-right">Pencapaian</TH>
                                <TH>Status Laporan</TH>
                                <TH />
                            </TR>
                        </THead>
                        <TBody>
                            {rows.map((event) => (
                                        <TR key={event.id}>
                                            <TD>
                                                <p className="font-medium text-text">
                                                    {event.event_name}
                                                </p>
                                                <p className="text-xs text-muted">
                                                    {event.event_code}
                                                </p>
                                            </TD>
                                            <TD>
                                                <p>{event.venue ?? '-'}</p>
                                                <p className="text-xs text-muted">{event.venue_city ?? '-'}</p>
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
                                                    <span className="text-sm text-muted">
                                                        Belum ada laporan
                                                    </span>
                                                )}
                                            </TD>
                                            <TD className="text-right">
                                                {event.report && (
                                                    <Link
                                                        href={route('reports.show', event.report.id)}
                                                        className="text-sm font-medium text-primary hover:text-primary"
                                                    >
                                                        Detail
                                                    </Link>
                                                )}
                                            </TD>
                                        </TR>
                            ))}
                        </TBody>
                    </Table>
                )}
            />
        </AppLayout>
    );
}
