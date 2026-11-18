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
    Select,
    type BadgeProps,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { Download, RefreshCw } from 'lucide-react';
import { FormEvent, useState } from 'react';

type LogEntry = {
    timestamp: string;
    environment: string;
    level: string;
    message: string;
};

type Filters = {
    level: string;
    search: string;
    lines: number;
};

const LEVEL_VARIANT: Record<string, BadgeProps['variant']> = {
    emergency: 'danger',
    alert: 'danger',
    critical: 'danger',
    error: 'danger',
    warning: 'warning',
    notice: 'info',
    info: 'info',
    debug: 'default',
};

function formatBytes(value: number): string {
    if (value <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = value;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

export default function AdminLogsIndex({
    entries,
    levels,
    total,
    filters,
    log,
}: {
    entries: LogEntry[];
    levels: string[];
    total: number;
    filters: Filters;
    log: {
        exists: boolean;
        size: number;
        modified: string | null;
    };
}) {
    const [level, setLevel] = useState(filters.level);
    const [search, setSearch] = useState(filters.search);
    const [lines, setLines] = useState(String(filters.lines));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('admin.logs.index'),
            { level, search, lines },
            { preserveState: true },
        );
    };

    return (
        <AppLayout title="Log Aplikasi">
            <Head title="Log Aplikasi" />
            <PageHeader
                title="Log Aplikasi"
                subtitle="Telusuri aktivitas dan error terbaru dari laravel.log"
            >
                <Link href={route('admin.system.index')}>
                    <Button variant="outline">Kesehatan Sistem</Button>
                </Link>
                {log.exists && (
                    <Link href={route('admin.logs.download')}>
                        <Button variant="outline">
                            <Download className="h-4 w-4" /> Unduh
                        </Button>
                    </Link>
                )}
                <Button variant="primary" onClick={() => router.reload()}>
                    <RefreshCw className="h-4 w-4" /> Muat Ulang
                </Button>
            </PageHeader>

            <FilterCard>
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-3 sm:flex-row"
                >
                    <Select
                        className="sm:w-48"
                        value={level}
                        onChange={(event) => setLevel(event.target.value)}
                    >
                        <option value="all">Semua level</option>
                        {levels.map((item) => (
                            <option key={item} value={item}>
                                {item.toUpperCase()}
                            </option>
                        ))}
                    </Select>
                    <SearchInput
                        placeholder="Cari pesan log..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Select
                        className="sm:w-40"
                        value={lines}
                        onChange={(event) => setLines(event.target.value)}
                    >
                        <option value="100">100 baris</option>
                        <option value="300">300 baris</option>
                        <option value="500">500 baris</option>
                        <option value="1000">1000 baris</option>
                        <option value="2000">2000 baris</option>
                    </Select>
                    <Button
                        type="submit"
                        variant="default"
                        className="h-11 rounded-xl px-5"
                    >
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <div className="mb-4 flex flex-wrap items-center gap-2 text-xs text-muted">
                <Badge variant="outline">{total} entri cocok</Badge>
                <Badge variant="outline">{entries.length} ditampilkan</Badge>
                <Badge variant="outline">Ukuran {formatBytes(log.size)}</Badge>
                <span>Terakhir diubah {formatDateTime(log.modified)}</span>
            </div>

            <CardTable>
                {entries.length === 0 ? (
                    <EmptyState
                        title={
                            log.exists
                                ? 'Tidak ada log yang cocok'
                                : 'Log belum tersedia'
                        }
                        description={
                            log.exists
                                ? 'Ubah level atau kata kunci pencarian.'
                                : 'Log akan muncul setelah aplikasi menulis aktivitas.'
                        }
                    />
                ) : (
                    <div className="divide-y divide-border">
                        {entries.map((entry, index) => (
                            <div
                                key={`${entry.timestamp}-${index}`}
                                className="px-4 py-3 sm:px-5"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            LEVEL_VARIANT[entry.level] ??
                                            'default'
                                        }
                                    >
                                        {entry.level.toUpperCase()}
                                    </Badge>
                                    <span className="font-mono text-xs text-muted">
                                        {entry.timestamp}
                                    </span>
                                    <span className="text-xs text-muted">
                                        {entry.environment}
                                    </span>
                                </div>
                                <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-page p-3 font-mono text-xs text-text">
                                    {entry.message}
                                </pre>
                            </div>
                        ))}
                    </div>
                )}
            </CardTable>
        </AppLayout>
    );
}
