import {
    CardTable,
    EmptyState,
    FilterCard,
    PageHeader,
    SearchInput,
} from '@/Components/AppBits';
import {
    Badge,
    Button,
    Input,
    StatCard,
    StatusBadge,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatCurrency, formatDate, formatNumber } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    CircleCheck,
    CircleDashed,
    Download,
    Target,
    Wallet,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

type DailySummary = {
    days: number;
    events: number;
    reported: number;
    missing: number;
    target: number;
    actual: number;
    achievement: number;
    shifts: number;
    payroll: number;
};

type DailyRow = {
    id: number;
    date: string;
    event_code: string;
    event_name: string;
    brand: string | null;
    venue: string | null;
    city: string | null;
    pic: string | null;
    status: string;
    status_label: string;
    team_leader: string | null;
    spg_count: number;
    team_count: number;
    target: number;
    actual: number | null;
    achievement: number | null;
    reported: boolean;
    report_id: number | null;
    report_status: string | null;
    report_status_label: string | null;
    shifts: number;
    fee_rate: number;
    total_fee: number;
};

type DailyGroup = {
    date: string;
    summary: DailySummary;
    events: DailyRow[];
};

type Filters = {
    search?: string;
    date?: string;
    date_from?: string;
    date_to?: string;
};

const achievementVariant = (achievement: number | null) => {
    if (achievement === null) return 'default' as const;
    if (achievement >= 100) return 'success' as const;
    if (achievement >= 80) return 'warning' as const;
    return 'danger' as const;
};

export default function ReportsDaily({
    days,
    summary,
    filters,
}: {
    days: DailyGroup[];
    summary: DailySummary;
    rate: number;
    filters: Filters;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [date, setDate] = useState(filters.date ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const params = () => ({
        search: search || undefined,
        date: date || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('reports.daily'), params(), { preserveState: true });
    };

    const reset = () => {
        setSearch('');
        setDate('');
        setDateFrom('');
        setDateTo('');
        router.get(route('reports.daily'), {}, { preserveState: true });
    };

    const exportCsv = () => {
        window.location.href = route('reports.daily.export', params());
    };

    return (
        <AppLayout title="Laporan Harian">
            <Head title="Laporan Harian" />
            <PageHeader
                title="Laporan Harian"
                subtitle="Rekap output per hari: hasil sampling, status laporan, shift, dan fee tim."
            >
                <Button variant="outline" onClick={exportCsv}>
                    <Download className="mr-2 h-4 w-4" />
                    Export CSV
                </Button>
                <Link href={route('reports.by-event')}>
                    <Button variant="outline">Laporan per Event</Button>
                </Link>
            </PageHeader>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    label="Total Event"
                    value={formatNumber(summary.events)}
                    hint={`${summary.days} hari`}
                    icon={<CalendarDays className="h-5 w-5" />}
                    tone="info"
                />
                <StatCard
                    label="Sudah Lapor"
                    value={formatNumber(summary.reported)}
                    hint={`${summary.missing} belum ada laporan`}
                    icon={<CircleCheck className="h-5 w-5" />}
                    tone="success"
                />
                <StatCard
                    label="Belum Ada Laporan"
                    value={formatNumber(summary.missing)}
                    icon={<CircleDashed className="h-5 w-5" />}
                    tone={summary.missing > 0 ? 'warning' : 'default'}
                />
                <StatCard
                    label="Pencapaian Sampling"
                    value={`${summary.achievement.toFixed(1)}%`}
                    hint={`${formatNumber(summary.actual)} / ${formatNumber(summary.target)}`}
                    icon={<Target className="h-5 w-5" />}
                    tone={achievementVariant(summary.achievement)}
                />
                <StatCard
                    label="Shift & Fee"
                    value={formatCurrency(summary.payroll)}
                    hint={`${formatNumber(summary.shifts)} shift layak bayar`}
                    icon={<Wallet className="h-5 w-5" />}
                    tone="accent"
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
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Tanggal
                        <Input
                            type="date"
                            className="sm:w-44"
                            value={date}
                            onChange={(event) => setDate(event.target.value)}
                        />
                    </label>
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

            {days.length === 0 ? (
                <CardTable>
                    <EmptyState
                        title="Belum ada event pada periode ini"
                        description="Ubah filter tanggal atau kata kunci untuk melihat rekap event lainnya."
                    />
                </CardTable>
            ) : (
                days.map((day) => (
                    <div key={day.date} className="mb-6">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold text-text">
                                {formatDate(day.date)}
                            </h2>
                            <div className="flex flex-wrap items-center gap-3 text-xs text-muted">
                                <span>{day.summary.events} event</span>
                                <span>{day.summary.reported} lapor</span>
                                <span>{day.summary.missing} belum</span>
                                <span>
                                    Pencapaian {day.summary.achievement.toFixed(1)}%
                                </span>
                                <span>{day.summary.shifts} shift</span>
                                <span>{formatCurrency(day.summary.payroll)}</span>
                            </div>
                        </div>
                        <CardTable>
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Event</TH>
                                        <TH>Brand / Lokasi</TH>
                                        <TH>PIC</TH>
                                        <TH>Tim</TH>
                                        <TH className="text-right">Target</TH>
                                        <TH className="text-right">Aktual</TH>
                                        <TH className="text-right">Pencapaian</TH>
                                        <TH>Laporan</TH>
                                        <TH className="text-right">Shift / Fee</TH>
                                        <TH />
                                    </TR>
                                </THead>
                                <TBody>
                                    {day.events.map((event) => (
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
                                                <p>{event.brand ?? '-'}</p>
                                                <p className="text-xs text-muted">
                                                    {[event.venue, event.city]
                                                        .filter(Boolean)
                                                        .join(', ') || '-'}
                                                </p>
                                            </TD>
                                            <TD>{event.pic ?? '-'}</TD>
                                            <TD>
                                                <p>{event.team_leader ?? '-'}</p>
                                                <p className="text-xs text-muted">
                                                    TL · {event.spg_count} SPG
                                                </p>
                                            </TD>
                                            <TD className="text-right">
                                                {formatNumber(event.target)}
                                            </TD>
                                            <TD className="text-right">
                                                {event.actual === null
                                                    ? '-'
                                                    : formatNumber(event.actual)}
                                            </TD>
                                            <TD className="text-right">
                                                {event.achievement === null ? (
                                                    '-'
                                                ) : (
                                                    <Badge
                                                        variant={achievementVariant(
                                                            event.achievement,
                                                        )}
                                                    >
                                                        {event.achievement.toFixed(1)}%
                                                    </Badge>
                                                )}
                                            </TD>
                                            <TD>
                                                {event.report_status ? (
                                                    <StatusBadge
                                                        status={event.report_status}
                                                        label={event.report_status_label ?? undefined}
                                                    />
                                                ) : (
                                                    <Badge variant="warning">
                                                        Belum lapor
                                                    </Badge>
                                                )}
                                            </TD>
                                            <TD className="text-right">
                                                <p>{event.shifts} shift</p>
                                                <p className="text-xs text-muted">
                                                    {formatCurrency(event.total_fee)}
                                                </p>
                                            </TD>
                                            <TD className="text-right">
                                                {event.report_id ? (
                                                    <Link
                                                        href={route(
                                                            'reports.show',
                                                            event.report_id,
                                                        )}
                                                        className="text-sm font-medium text-primary hover:text-primary"
                                                    >
                                                        Detail
                                                    </Link>
                                                ) : (
                                                    <Link
                                                        href={route('events.show', event.id)}
                                                        className="text-sm font-medium text-primary hover:text-primary"
                                                    >
                                                        Event
                                                    </Link>
                                                )}
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                                <tfoot className="border-t border-border bg-page/60">
                                    <TR className="hover:bg-transparent">
                                        <TD className="font-semibold" colSpan={4}>
                                            Total hari ini
                                        </TD>
                                        <TD className="text-right font-semibold">
                                            {formatNumber(day.summary.target)}
                                        </TD>
                                        <TD className="text-right font-semibold">
                                            {formatNumber(day.summary.actual)}
                                        </TD>
                                        <TD className="text-right font-semibold">
                                            {day.summary.achievement.toFixed(1)}%
                                        </TD>
                                        <TD className="text-sm text-muted">
                                            {day.summary.reported}/{day.summary.events} lapor
                                        </TD>
                                        <TD className="text-right font-semibold">
                                            {day.summary.shifts} ·{' '}
                                            {formatCurrency(day.summary.payroll)}
                                        </TD>
                                        <TD />
                                    </TR>
                                </tfoot>
                            </Table>
                        </CardTable>
                    </div>
                ))
            )}
        </AppLayout>
    );
}
