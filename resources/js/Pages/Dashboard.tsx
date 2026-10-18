import { EmptyState, PageHeader } from '@/Components/AppBits';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
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
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
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
    ArrowRight,
    CalendarCheck,
} from 'lucide-react';
import { ComponentType } from 'react';

type StatMeta = {
    label: string;
    icon: ComponentType<{ className?: string }>;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
};

const STAT_META: Record<string, StatMeta> = {
    total_events: { label: 'Total Event', icon: CalendarDays, tone: 'info' },
    active_events: { label: 'Event Aktif', icon: Activity, tone: 'success' },
    upcoming_events: { label: 'Event Mendatang', icon: CalendarClock, tone: 'info' },
    completed_events: { label: 'Event Selesai', icon: CheckCircle2, tone: 'success' },
    pending_reports: { label: 'Laporan Menunggu', icon: FileClock, tone: 'warning' },
    events_revision: { label: 'Laporan Revisi', icon: FileWarning, tone: 'warning' },
    total_sku: { label: 'Total SKU', icon: Package, tone: 'info' },
    low_stock: { label: 'Stok Menipis', icon: PackageX, tone: 'danger' },
    pending_requests: { label: 'Permintaan Stok', icon: ClipboardList, tone: 'warning' },
    pending: { label: 'Menunggu', icon: Clock, tone: 'warning' },
    preparing: { label: 'Disiapkan', icon: Utensils, tone: 'info' },
    ready: { label: 'Siap', icon: PackageCheck, tone: 'success' },
    dispatched: { label: 'Dikirim', icon: Truck, tone: 'info' },
    pending_permits: { label: 'Perizinan Aktif', icon: FileCheck2, tone: 'warning' },
    approved_permits: { label: 'Perizinan Disetujui', icon: ShieldCheck, tone: 'success' },
    draft_fjp: { label: 'FJP Draft', icon: FileText, tone: 'default' },
    mrv_pending: { label: 'MRV Menunggu', icon: ClipboardCheck, tone: 'warning' },
    kitchen_pending: { label: 'Kitchen Menunggu', icon: Utensils, tone: 'warning' },
};

const STAT_ORDER = [
    'total_events',
    'active_events',
    'upcoming_events',
    'completed_events',
    'pending_reports',
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

function progressTone(percentage: number) {
    if (percentage >= 100) return 'bg-emerald-500';
    if (percentage >= 60) return 'bg-indigo-600';
    return 'bg-amber-500';
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

export default function Dashboard({
    stats,
    quarterly,
    upcomingEvents,
    schoolComparison,
}: {
    stats: Record<string, number>;
    quarterly: { brand: string; actual: number; target: number; percentage: number }[];
    upcomingEvents: any[];
    schoolComparison: any[];
}) {
    const orderedKeys = [
        ...STAT_ORDER.filter((k) => k in stats),
        ...Object.keys(stats).filter((k) => !STAT_ORDER.includes(k)),
    ];

    const totalQuarterlyActual = quarterly.reduce((s, r) => s + r.actual, 0);
    const totalQuarterlyTarget = quarterly.reduce((s, r) => s + r.target, 0);
    const totalQuarterlyPct =
        totalQuarterlyTarget > 0
            ? Math.round((totalQuarterlyActual / totalQuarterlyTarget) * 100)
            : 0;

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />
            <PageHeader
                title="Dashboard"
                subtitle="Ringkasan operasional event Indosakti"
            />

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                {orderedKeys.slice(0, 8).map((key) => {
                    const meta = STAT_META[key];
                    return (
                        <StatCard
                            key={key}
                            label={meta?.label ?? key}
                            value={formatNumber(stats[key])}
                            icon={meta?.icon && <meta.icon className="h-5 w-5" />}
                            tone={meta?.tone}
                        />
                    );
                })}
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <SectionCard title="Target Kuartal per Brand">
                    <CardContent className="space-y-4">
                        {quarterly.length === 0 && (
                            <EmptyState
                                title="Belum ada target"
                                description="Atur target kuartal per brand di menu Target Kuartal."
                            />
                        )}
                        {quarterly.length > 0 && (
                            <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 px-4 py-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                        Total kuartal ini
                                    </span>
                                    <span className="text-sm font-semibold text-slate-900">
                                        {totalQuarterlyPct}%
                                    </span>
                                </div>
                                <div className="mt-1 font-mono text-sm text-slate-600">
                                    {formatNumber(totalQuarterlyActual)} / {formatNumber(totalQuarterlyTarget)} cup
                                </div>
                            </div>
                        )}
                        {quarterly.map((row) => (
                            <div key={row.brand}>
                                <div className="mb-1.5 flex items-center justify-between text-sm">
                                    <span className="font-medium text-slate-700">
                                        {row.brand}
                                    </span>
                                    <span className="font-mono text-xs text-slate-500">
                                        {formatNumber(row.actual)} / {formatNumber(row.target)}
                                        <span className="ml-1.5 tabular-nums font-semibold text-slate-700">
                                            {row.percentage}%
                                        </span>
                                    </span>
                                </div>
                                <div
                                    className="h-2 w-full overflow-hidden rounded-full bg-slate-100"
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
                                        style={{
                                            width: `${Math.min(100, row.percentage)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </SectionCard>

                <SectionCard
                    title="Event Mendatang"
                    action={
                        <Link
                            href={route('events.index')}
                            className="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500"
                        >
                            Lihat semua
                            <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    }
                >
                    <CardContent className="p-0">
                        {upcomingEvents.length === 0 ? (
                            <EmptyState
                                title="Tidak ada event mendatang"
                                description="Event yang akan datang akan tampil di sini."
                            />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Event</TH>
                                        <TH>Tanggal</TH>
                                        <TH>Status</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {upcomingEvents.map((event) => (
                                        <TR key={event.id}>
                                            <TD>
                                                <Link
                                                    href={route('events.show', event.id)}
                                                    className="font-medium text-slate-800 hover:text-indigo-600"
                                                >
                                                    {event.event_name}
                                                </Link>
                                                <p className="text-xs text-slate-400">
                                                    {event.brand} · {event.venue}
                                                </p>
                                            </TD>
                                            <TD className="whitespace-nowrap">
                                                <span className="inline-flex items-center gap-1.5 text-slate-600">
                                                    <CalendarCheck className="h-3.5 w-3.5 text-slate-400" />
                                                    {formatDate(event.event_date)}
                                                </span>
                                            </TD>
                                            <TD>
                                                <StatusBadge
                                                    status={event.status}
                                                    label={event.status_label}
                                                />
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </CardContent>
                </SectionCard>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle className="inline-flex items-center gap-2">
                        <TrendingUp className="h-4 w-4 text-indigo-600" />
                        Perbandingan Jumlah Siswa (Sekolah vs Dapodik)
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    {schoolComparison.length === 0 ? (
                        <EmptyState
                            title="Belum ada data perbandingan"
                            description="Data muncul setelah FJP memiliki jumlah siswa sekolah & Dapodik."
                        />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>FJP</TH>
                                    <TH className="text-right">Sekolah</TH>
                                    <TH className="text-right">Dapodik (simulasi)</TH>
                                    <TH className="text-right">Selisih</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {schoolComparison.map((row) => (
                                    <TR key={row.id}>
                                        <TD>
                                            <Link
                                                href={route('fjp.show', row.id)}
                                                className="font-medium text-slate-800 hover:text-indigo-600"
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
                                                <span className="text-slate-400">-</span>
                                            ) : row.difference === 0 ? (
                                                <span className="font-mono text-sm font-medium text-emerald-600">
                                                    0
                                                </span>
                                            ) : (
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-xs font-medium',
                                                        row.difference > 0
                                                            ? 'bg-amber-100 text-amber-700'
                                                            : 'bg-sky-100 text-sky-700',
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
        </AppLayout>
    );
}
