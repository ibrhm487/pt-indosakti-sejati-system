import { EmptyState, FilterCard, PageHeader } from '@/Components/AppBits';
import { EventBrandGroup, EventGroupView } from '@/Components/EventGroupView';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    ChartCard,
    Input,
    Select,
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
import { cn, formatDate, formatNumber } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    Clock,
    FileCheck2,
    FileClock,
    FileText,
    FileWarning,
    Package,
    PackageCheck,
    PackageX,
    ShieldCheck,
    TrendingUp,
    Truck,
    Utensils,
} from 'lucide-react';
import { ComponentType, FormEvent, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

type StatMeta = {
    label: string;
    icon: ComponentType<{ className?: string }>;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info' | 'accent';
    href?: string;
};

const STAT_META: Record<string, StatMeta> = {
    total_events: { label: 'Total Event', icon: CalendarDays, tone: 'info' },
    active_events: { label: 'Event Aktif', icon: Activity, tone: 'success' },
    upcoming_events: { label: 'Event Mendatang', icon: CalendarClock, tone: 'info' },
    completed_events: { label: 'Event Selesai', icon: CheckCircle2, tone: 'success' },
    pending_reports: {
        label: 'Laporan Menunggu',
        icon: FileClock,
        tone: 'warning',
        href: route('reports.index'),
    },
    events_revision: {
        label: 'Laporan Revisi',
        icon: FileWarning,
        tone: 'warning',
        href: route('reports.index'),
    },
    total_sku: { label: 'Total SKU', icon: Package, tone: 'info' },
    low_stock: {
        label: 'Stok Menipis',
        icon: PackageX,
        tone: 'danger',
        href: route('warehouse.inventory.index'),
    },
    pending_requests: {
        label: 'Permintaan Stok',
        icon: ClipboardList,
        tone: 'warning',
        href: route('warehouse.mrv.index'),
    },
    pending: { label: 'Menunggu', icon: Clock, tone: 'warning' },
    preparing: { label: 'Disiapkan', icon: Utensils, tone: 'info' },
    ready: { label: 'Siap', icon: PackageCheck, tone: 'success' },
    dispatched: { label: 'Dikirim', icon: Truck, tone: 'accent' },
    pending_permits: {
        label: 'Perizinan Menunggu',
        icon: FileCheck2,
        tone: 'warning',
        href: route('permits.index'),
    },
    approved_permits: { label: 'Perizinan Disetujui', icon: ShieldCheck, tone: 'success' },
    draft_fjp: { label: 'FJP Draft', icon: FileText, tone: 'default', href: route('fjp.index') },
    mrv_pending: {
        label: 'MRV Menunggu',
        icon: ClipboardCheck,
        tone: 'warning',
        href: route('warehouse.mrv.index'),
    },
    kitchen_pending: {
        label: 'Kitchen Menunggu',
        icon: Utensils,
        tone: 'warning',
        href: route('kitchen.index'),
    },
};

const STAT_ORDER = [
    'total_events',
    'active_events',
    'upcoming_events',
    'completed_events',
    'pending_reports',
    'events_revision',
    'total_sku',
    'low_stock',
    'pending_requests',
    'pending_permits',
    'approved_permits',
    'draft_fjp',
    'mrv_pending',
    'kitchen_pending',
    'pending',
    'preparing',
    'ready',
    'dispatched',
];

const ROLE_LABELS: Record<string, string> = {
    developer: 'Developer',
    system_admin: 'Admin Sistem',
    permitter: 'Permitter',
    pic: 'PIC',
    team: 'Tim Lapangan',
    warehouse: 'Gudang',
    kitchen: 'Kitchen',
    admin_event: 'Admin Event',
};

const COLORS = {
    primary: '#D91E2A',
    success: '#16845B',
    accent: '#D9A441',
    border: '#E5E7EB',
    muted: '#667085',
};

function progressTone(percentage: number) {
    if (percentage >= 100) return 'bg-success';
    if (percentage >= 60) return 'bg-primary';
    return 'bg-warning';
}

function SectionCard({
    title,
    action,
    children,
}: {
    title: string;
    action?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex items-center justify-between">
                <CardTitle>{title}</CardTitle>
                {action}
            </CardHeader>
            {children}
        </Card>
    );
}

function TrendChart({ trend }: { trend: { month: string; total: number; completed: number }[] }) {
    const hasData = trend.some((row) => row.total > 0);

    if (!hasData) {
        return (
            <EmptyState
                title="Belum ada tren event"
                description="Grafik muncul begitu ada event pada 6 bulan terakhir."
            />
        );
    }

    return (
        <div className="h-64 w-full" role="img" aria-label="Grafik tren event 6 bulan terakhir">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={trend} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke={COLORS.border} vertical={false} />
                    <XAxis
                        dataKey="month"
                        tick={{ fontSize: 11, fill: COLORS.muted }}
                        axisLine={{ stroke: COLORS.border }}
                        tickLine={false}
                    />
                    <YAxis
                        allowDecimals={false}
                        tick={{ fontSize: 11, fill: COLORS.muted }}
                        axisLine={false}
                        tickLine={false}
                    />
                    <Tooltip
                        cursor={{ fill: 'rgba(31,58,95,0.05)' }}
                        contentStyle={{
                            borderRadius: 10,
                            border: `1px solid ${COLORS.border}`,
                            fontSize: 12,
                        }}
                    />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    <Bar dataKey="total" name="Terjadwal" fill={COLORS.primary} radius={[4, 4, 0, 0]} maxBarSize={32} />
                    <Bar dataKey="completed" name="Selesai" fill={COLORS.success} radius={[4, 4, 0, 0]} maxBarSize={32} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

export default function Dashboard({
    role,
    stats = {},
    quarterly = [],
    upcomingGroups = [],
    trend = [],
    schoolComparison = [],
    filters = {},
    brands = [],
}: {
    role: string | null;
    stats: Record<string, number>;
    quarterly: {
        brand: string;
        actual: number;
        target: number;
        percentage: number;
        actual_shifts: number;
        target_shifts: number;
        shift_percentage: number;
    }[];
    upcomingGroups: EventBrandGroup[];
    trend: { month: string; total: number; completed: number }[];
    schoolComparison: any[];
    filters: { brand_id?: number | string | null; date_from?: string | null; date_to?: string | null };
    brands: { id: number; name: string }[];
}) {
    const [brandId, setBrandId] = useState(
        filters.brand_id != null ? String(filters.brand_id) : '',
    );
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const applyFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('dashboard'),
            {
                brand_id: brandId || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const resetFilters = () => {
        setBrandId('');
        setDateFrom('');
        setDateTo('');
        router.get(route('dashboard'), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const orderedKeys = [
        ...STAT_ORDER.filter((k) => k in stats),
        ...Object.keys(stats).filter((k) => !STAT_ORDER.includes(k)),
    ];

    const totalQuarterlyActual = quarterly.reduce((s, r) => s + r.actual, 0);
    const totalQuarterlyTarget = quarterly.reduce((s, r) => s + r.target, 0);
    const totalQuarterlyActualShifts = quarterly.reduce((s, r) => s + r.actual_shifts, 0);
    const totalQuarterlyTargetShifts = quarterly.reduce((s, r) => s + r.target_shifts, 0);
    const totalQuarterlyPct =
        totalQuarterlyTarget > 0
            ? Math.round((totalQuarterlyActual / totalQuarterlyTarget) * 100)
            : 0;

    const periodLabel =
        dateFrom || dateTo
            ? `${dateFrom ? formatDate(dateFrom) : 'Awal'} – ${dateTo ? formatDate(dateTo) : 'Sekarang'}`
            : new Date().toLocaleDateString('id-ID', {
                  month: 'long',
                  year: 'numeric',
              });

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />
            <PageHeader
                title="Ringkasan Operasional"
                subtitle={`${ROLE_LABELS[role ?? ''] ?? 'Semua Peran'} · Periode ${periodLabel}`}
            />

            <FilterCard>
                <form
                    onSubmit={applyFilters}
                    className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                >
                    <Select
                        className="sm:w-56"
                        value={brandId}
                        onChange={(e) => setBrandId(e.target.value)}
                    >
                        <option value="">Semua brand</option>
                        {brands.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </Select>
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Dari Tanggal
                        <Input
                            type="date"
                            className="sm:w-44"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Sampai Tanggal
                        <Input
                            type="date"
                            className="sm:w-44"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                        />
                    </label>
                    <Button
                        type="submit"
                        variant="default"
                        className="h-11 rounded-xl px-5"
                    >
                        Terapkan
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11 rounded-xl px-5"
                        onClick={resetFilters}
                    >
                        Reset
                    </Button>
                </form>
            </FilterCard>

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                {orderedKeys.slice(0, 8).map((key) => {
                    const meta = STAT_META[key];
                    const card = (
                        <StatCard
                            label={meta?.label ?? key}
                            value={formatNumber(stats[key])}
                            icon={meta?.icon && <meta.icon className="h-5 w-5" />}
                            tone={meta?.tone}
                        />
                    );
                    return meta?.href ? (
                        <Link key={key} href={meta.href} className="block transition-transform hover:-translate-y-0.5">
                            {card}
                        </Link>
                    ) : (
                        <div key={key}>{card}</div>
                    );
                })}
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <ChartCard
                    title="Tren Event — 6 Bulan Terakhir"
                    subtitle="Jumlah event terjadwal dibanding yang sudah selesai, mengikuti cakupan peran Anda."
                >
                    <TrendChart trend={trend} />
                </ChartCard>

                <SectionCard title="Target Kuartal per Brand">
                    <CardContent className="space-y-4">
                        <p className="text-xs text-muted">
                            Realisasi tercatat saat produk keluar dari gudang melalui MRV.
                        </p>
                        {quarterly.length === 0 && (
                            <EmptyState
                                title="Belum ada target"
                                description="Atur target kuartal per brand di menu Target Kuartal."
                            />
                        )}
                        {quarterly.length > 0 && (
                            <div className="rounded-xl border border-border bg-page/60 px-4 py-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium uppercase tracking-wide text-muted">
                                        Total kuartal ini
                                    </span>
                                    <span className="text-sm font-semibold text-text">
                                        {totalQuarterlyPct}%
                                    </span>
                                </div>
                                <div className="mt-1 font-mono text-sm text-muted">
                                    {formatNumber(totalQuarterlyActual)} / {formatNumber(totalQuarterlyTarget)} cups
                                    <span className="ml-3 text-xs">
                                        {formatNumber(totalQuarterlyActualShifts)} / {formatNumber(totalQuarterlyTargetShifts)} shift
                                    </span>
                                </div>
                            </div>
                        )}
                        {quarterly.map((row) => (
                            <div key={row.brand}>
                                <div className="mb-1.5 flex items-center justify-between text-sm">
                                    <span className="font-medium text-text">{row.brand}</span>
                                    <span className="font-mono text-xs text-muted">
                                        {formatNumber(row.actual)} / {formatNumber(row.target)}
                                        <span className="ml-1.5 tabular-nums font-semibold text-text">
                                            {row.percentage}%
                                        </span>
                                        <span className="ml-2">
                                            · {formatNumber(row.actual_shifts)} / {formatNumber(row.target_shifts)} shift
                                            {row.target_shifts > 0 ? ` (${row.shift_percentage}%)` : ''}
                                        </span>
                                    </span>
                                </div>
                                <div
                                    className="h-2 w-full overflow-hidden rounded-full bg-border/60"
                                    role="progressbar"
                                    aria-label={`Pencapaian ${row.brand}`}
                                    aria-valuenow={row.percentage}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                >
                                    <div
                                        className={cn(
                                            'h-full rounded-full transition-[width] duration-500',
                                            progressTone(row.percentage),
                                        )}
                                        style={{ width: `${Math.min(100, row.percentage)}%` }}
                                    />
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </SectionCard>
            </div>

            <SectionCard
                title="Event Mendatang"
                action={
                    <Link
                        href={route('events.index')}
                        className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary-hover"
                    >
                        Lihat semua
                        <ArrowRight className="h-3.5 w-3.5" />
                    </Link>
                }
            >
                <CardContent className="p-0">
                    {upcomingGroups.length === 0 ? (
                        <EmptyState
                            title="Tidak ada event mendatang"
                            description="Event yang akan datang akan tampil di sini."
                        />
                    ) : (
                        <EventGroupView
                            groups={upcomingGroups}
                            compact
                            renderTable={(rows) => (
                                <Table>
                                    <THead><TR><TH>Event</TH><TH>Lokasi</TH><TH>Status</TH></TR></THead>
                                    <TBody>
                                        {rows.map((event) => (
                                            <TR key={event.id}>
                                                <TD><Link href={route('events.show', event.id)} className="font-medium text-text hover:text-primary">{event.event_name}</Link></TD>
                                                <TD>{event.venue ?? '-'}</TD>
                                                <TD><StatusBadge status={event.status} label={event.status_label} /></TD>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            )}
                        />
                    )}
                </CardContent>
            </SectionCard>

            {(schoolComparison.length > 0 ||
                role === 'permitter' ||
                role === 'system_admin' ||
                role === 'developer') && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle className="inline-flex items-center gap-2">
                            <TrendingUp className="h-4 w-4 text-primary" />
                            Perbandingan Jumlah Siswa (Sekolah vs Dapodik)
                        </CardTitle>
                    </CardHeader>
                    <CardContent className={schoolComparison.length === 0 ? '' : 'p-0'}>
                        {schoolComparison.length === 0 ? (
                            <EmptyState
                                title="Belum ada data perbandingan"
                                description="Data muncul setelah FJP memiliki jumlah siswa versi sekolah dan versi Dapodik."
                            />
                        ) : (
                            <Table>
                            <THead>
                                <TR>
                                    <TH>FJP</TH>
                                    <TH className="text-right">Sekolah</TH>
                                    <TH className="text-right">Dapodik</TH>
                                    <TH className="text-right">Selisih</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {schoolComparison.map((row) => (
                                    <TR key={row.id}>
                                        <TD>
                                            <Link
                                                href={route('fjp.show', row.id)}
                                                className="font-medium text-text hover:text-primary"
                                            >
                                                {row.event_name}
                                            </Link>
                                        </TD>
                                        <TD className="text-right font-mono">
                                            {formatNumber(row.sekolah)}
                                        </TD>
                                        <TD className="text-right font-mono">
                                            {formatNumber(row.dapodik)}
                                        </TD>
                                        <TD className="text-right">
                                            {row.difference === null ? (
                                                <span className="text-muted">-</span>
                                            ) : row.difference === 0 ? (
                                                <span className="font-mono text-sm font-medium text-success">0</span>
                                            ) : (
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-xs font-medium',
                                                        row.difference > 0
                                                            ? 'bg-warning/10 text-warning'
                                                            : 'bg-info/10 text-info',
                                                    )}
                                                >
                                                    {row.difference > 0 ? '+' : ''}
                                                    {formatNumber(row.difference)}
                                                </span>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            )}
        </AppLayout>
    );
}
