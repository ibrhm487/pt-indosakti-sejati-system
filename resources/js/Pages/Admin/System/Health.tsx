import { PageHeader } from '@/Components/AppBits';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDateTime, formatNumber } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Database,
    HardDrive,
    RefreshCw,
    ScrollText,
    ServerCog,
    Settings2,
} from 'lucide-react';
import { ReactNode } from 'react';

type LogEntry = {
    timestamp: string;
    environment: string;
    level: string;
    message: string;
};

type HealthProps = {
    app: {
        name: string;
        environment: string;
        debug: boolean;
        url: string;
        timezone: string;
        laravel: string;
        php: string;
        os: string;
        server: string;
    };
    runtime: {
        memory_limit: string;
        memory_usage: number;
        memory_peak: number;
        max_execution_time: number;
        upload_max_filesize: string;
        post_max_size: string;
        cache: string;
        queue: string;
        session: string;
    };
    database: {
        connection: string;
        driver: string | null;
        database: string | null;
        version: string | null;
        error: string | null;
    };
    storage: {
        storage_writable: boolean;
        cache_writable: boolean;
        disk_total: number | null;
        disk_free: number | null;
        log_size: number | null;
        log_modified: string | null;
    };
    migrations: {
        ran: number | null;
        total: number | null;
        pending: number | null;
        pending_list: string[];
    };
    counts: {
        users: number | null;
        roles: number | null;
        permissions: number | null;
        failed_jobs: number | null;
    };
    log: {
        exists: boolean;
        size: number;
        modified: string | null;
        last_error: LogEntry | null;
    };
};

function formatBytes(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = value;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${size.toFixed(size >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
}

function InfoRow({
    label,
    value,
    tone,
}: {
    label: string;
    value: ReactNode;
    tone?: 'default' | 'success' | 'warning' | 'danger';
}) {
    return (
        <div className="flex items-start justify-between gap-3 py-1.5">
            <span className="text-sm text-muted">{label}</span>
            <span
                className={cn(
                    'text-right text-sm font-medium text-text',
                    tone === 'success' && 'text-success',
                    tone === 'warning' && 'text-warning',
                    tone === 'danger' && 'text-danger',
                )}
            >
                {value}
            </span>
        </div>
    );
}

function SectionCard({
    icon,
    title,
    badge,
    children,
}: {
    icon: ReactNode;
    title: string;
    badge?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex items-center justify-between gap-3">
                <CardTitle className="flex items-center gap-2">
                    <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-page text-primary">
                        {icon}
                    </span>
                    {title}
                </CardTitle>
                {badge}
            </CardHeader>
            <CardContent className="divide-y divide-border/60">
                {children}
            </CardContent>
        </Card>
    );
}

export default function AdminSystemHealth({
    app,
    runtime,
    database,
    storage,
    migrations,
    counts,
    log,
}: HealthProps) {
    const diskUsed =
        storage.disk_total && storage.disk_free !== null
            ? storage.disk_total - storage.disk_free
            : null;
    const diskPercent =
        diskUsed !== null && storage.disk_total
            ? Math.min(100, (diskUsed / storage.disk_total) * 100)
            : null;

    const issues: string[] = [];

    if (database.error) {
        issues.push('Koneksi database bermasalah.');
    }

    if ((migrations.pending ?? 0) > 0) {
        issues.push(`${migrations.pending} migrasi belum dijalankan.`);
    }

    if ((counts.failed_jobs ?? 0) > 0) {
        issues.push(`${counts.failed_jobs} failed job menunggu.`);
    }

    if (!storage.storage_writable) {
        issues.push('Folder storage tidak dapat ditulis.');
    }

    if (!storage.cache_writable) {
        issues.push('Folder bootstrap/cache tidak dapat ditulis.');
    }

    return (
        <AppLayout title="Kesehatan Sistem">
            <Head title="Kesehatan Sistem" />
            <PageHeader
                title="Kesehatan Sistem"
                subtitle="Ringkasan konfigurasi, database, penyimpanan, dan log"
            >
                <Link href={route('admin.logs.index')}>
                    <Button variant="outline">
                        <ScrollText className="h-4 w-4" /> Log Aplikasi
                    </Button>
                </Link>
                <Button variant="primary" onClick={() => router.reload()}>
                    <RefreshCw className="h-4 w-4" /> Muat Ulang
                </Button>
            </PageHeader>

            <div
                className={cn(
                    'mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                    issues.length === 0
                        ? 'border-success/20 bg-success/10 text-success'
                        : 'border-warning/20 bg-warning/10 text-warning',
                )}
            >
                {issues.length === 0 ? (
                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                ) : (
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                )}
                <div>
                    <p className="font-semibold">
                        {issues.length === 0
                            ? 'Semua pemeriksaan utama normal.'
                            : `${issues.length} hal perlu diperhatikan.`}
                    </p>
                    {issues.length > 0 && (
                        <ul className="mt-1 list-inside list-disc space-y-0.5">
                            {issues.map((issue) => (
                                <li key={issue}>{issue}</li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <SectionCard
                    icon={<ServerCog className="h-4 w-4" />}
                    title="Aplikasi"
                    badge={
                        <Badge
                            variant={app.debug ? 'warning' : 'success'}
                        >
                            {app.debug ? 'Debug aktif' : 'Debug nonaktif'}
                        </Badge>
                    }
                >
                    <InfoRow label="Nama" value={app.name} />
                    <InfoRow label="Environment" value={app.environment} />
                    <InfoRow label="Laravel" value={app.laravel} />
                    <InfoRow label="PHP" value={app.php} />
                    <InfoRow label="Timezone" value={app.timezone} />
                    <InfoRow label="URL" value={app.url} />
                    <InfoRow label="Sistem Operasi" value={app.os} />
                </SectionCard>

                <SectionCard
                    icon={<Database className="h-4 w-4" />}
                    title="Database"
                    badge={
                        <Badge
                            variant={database.error ? 'danger' : 'success'}
                        >
                            {database.error ? 'Bermasalah' : 'Terhubung'}
                        </Badge>
                    }
                >
                    <InfoRow label="Koneksi" value={database.connection} />
                    <InfoRow label="Driver" value={database.driver ?? '-'} />
                    <InfoRow label="Database" value={database.database ?? '-'} />
                    <InfoRow
                        label="Versi"
                        value={database.version ?? 'Tidak tersedia'}
                    />
                    <InfoRow
                        label="Pengguna"
                        value={formatNumber(counts.users)}
                    />
                    <InfoRow label="Role" value={formatNumber(counts.roles)} />
                    <InfoRow
                        label="Izin"
                        value={formatNumber(counts.permissions)}
                    />
                    <InfoRow
                        label="Failed Job"
                        value={formatNumber(counts.failed_jobs)}
                        tone={
                            (counts.failed_jobs ?? 0) > 0
                                ? 'danger'
                                : 'success'
                        }
                    />
                    {database.error && (
                        <p className="pt-2 text-xs text-danger">
                            {database.error}
                        </p>
                    )}
                </SectionCard>

                <SectionCard
                    icon={<Settings2 className="h-4 w-4" />}
                    title="Runtime PHP"
                >
                    <InfoRow
                        label="Memory limit"
                        value={runtime.memory_limit}
                    />
                    <InfoRow
                        label="Memory terpakai"
                        value={formatBytes(runtime.memory_usage)}
                    />
                    <InfoRow
                        label="Memory peak"
                        value={formatBytes(runtime.memory_peak)}
                    />
                    <InfoRow
                        label="Max execution"
                        value={`${runtime.max_execution_time} detik`}
                    />
                    <InfoRow
                        label="Upload maksimal"
                        value={runtime.upload_max_filesize}
                    />
                    <InfoRow
                        label="Post maksimal"
                        value={runtime.post_max_size}
                    />
                    <InfoRow label="Cache" value={runtime.cache} />
                    <InfoRow label="Queue" value={runtime.queue} />
                    <InfoRow label="Session" value={runtime.session} />
                </SectionCard>

                <SectionCard
                    icon={<HardDrive className="h-4 w-4" />}
                    title="Penyimpanan & Migrasi"
                    badge={
                        <Badge
                            variant={
                                (migrations.pending ?? 0) > 0
                                    ? 'warning'
                                    : 'success'
                            }
                        >
                            {migrations.pending ?? 0} pending
                        </Badge>
                    }
                >
                    <InfoRow
                        label="Storage writable"
                        value={storage.storage_writable ? 'Ya' : 'Tidak'}
                        tone={
                            storage.storage_writable ? 'success' : 'danger'
                        }
                    />
                    <InfoRow
                        label="Cache writable"
                        value={storage.cache_writable ? 'Ya' : 'Tidak'}
                        tone={storage.cache_writable ? 'success' : 'danger'}
                    />
                    <InfoRow
                        label="Disk terpakai"
                        value={
                            diskUsed !== null
                                ? `${formatBytes(diskUsed)} / ${formatBytes(storage.disk_total)}`
                                : '-'
                        }
                    />
                    {diskPercent !== null && (
                        <div className="py-2">
                            <div className="h-2 overflow-hidden rounded-full bg-page">
                                <div
                                    className={cn(
                                        'h-full rounded-full',
                                        diskPercent >= 90
                                            ? 'bg-danger'
                                            : diskPercent >= 75
                                              ? 'bg-warning'
                                              : 'bg-success',
                                    )}
                                    style={{
                                        width: `${diskPercent}%`,
                                    }}
                                />
                            </div>
                            <p className="mt-1 text-xs text-muted">
                                {diskPercent.toFixed(1)}% terpakai
                            </p>
                        </div>
                    )}
                    <InfoRow
                        label="Migrasi"
                        value={`${migrations.ran ?? '-'} / ${migrations.total ?? '-'} dijalankan`}
                    />
                    <InfoRow
                        label="Log size"
                        value={formatBytes(storage.log_size)}
                    />
                    <InfoRow
                        label="Log diubah"
                        value={formatDateTime(storage.log_modified)}
                    />
                    {migrations.pending_list.length > 0 && (
                        <div className="pt-2">
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">
                                Migrasi tertunda
                            </p>
                            <ul className="space-y-1 text-xs text-warning">
                                {migrations.pending_list.map((migration) => (
                                    <li
                                        key={migration}
                                        className="font-mono"
                                    >
                                        {migration}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </SectionCard>

                <SectionCard
                    icon={<ScrollText className="h-4 w-4" />}
                    title="Log Aplikasi"
                    badge={
                        <Badge variant={log.exists ? 'success' : 'outline'}>
                            {log.exists ? 'Tersedia' : 'Belum ada'}
                        </Badge>
                    }
                >
                    <InfoRow
                        label="Ukuran"
                        value={formatBytes(log.size)}
                    />
                    <InfoRow
                        label="Terakhir diubah"
                        value={formatDateTime(log.modified)}
                    />
                    <InfoRow
                        label="Error terakhir"
                        value={log.last_error ? 'Ada' : 'Tidak ada'}
                        tone={log.last_error ? 'danger' : 'success'}
                    />
                    {log.last_error && (
                        <div className="pt-2">
                            <div className="mb-1 flex flex-wrap items-center gap-2">
                                <Badge variant="danger">
                                    {log.last_error.level}
                                </Badge>
                                <span className="text-xs text-muted">
                                    {log.last_error.timestamp}
                                </span>
                            </div>
                            <pre className="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg bg-page p-3 text-xs text-text">
                                {log.last_error.message}
                            </pre>
                        </div>
                    )}
                </SectionCard>
            </div>
        </AppLayout>
    );
}
