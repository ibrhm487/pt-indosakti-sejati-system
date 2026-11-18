import { PageHeader } from '@/Components/AppBits';
import { Badge, Button, Card } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatCurrency, formatDate, formatDateTime, formatNumber } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';

type QuickReport = {
    id: number;
    title: string;
    body: string | null;
    created_at: string | null;
    event: string | null;
    event_date: string | null;
    activity_date: string | null;
    activity_start_time: string | null;
    activity_end_time: string | null;
    attendance_status: string | null;
    brand: string | null;
    venue: string | null;
    city: string | null;
    user: string | null;
    variant: 'school' | 'instore' | 'general';
    sampling_target: number | null;
    sampling_actual: number | null;
    selling_target: string | null;
    selling_actual: string | null;
    booth_package_1: number | null;
    booth_package_2: number | null;
    booth_package_3: number | null;
    store_package_1: number | null;
    store_package_2: number | null;
    store_package_3: number | null;
    learning: string | null;
    photos: { id: number | null; url: string }[];
    videos: { id: number; url: string; mime: string | null }[];
};

export default function QuickReportsShow({ report }: { report: QuickReport }) {
    return (
        <AppLayout title="Detail Quick Report">
            <Head title="Detail Quick Report" />
            <PageHeader
                title="Detail Laporan"
                subtitle="Data yang tersimpan dari Quick Report dan absensi event."
            >
                <Link href={route('quick-reports.history')}>
                    <Button variant="outline">Kembali ke riwayat</Button>
                </Link>
            </PageHeader>

            <div className="space-y-4">
                <Card className="space-y-4 p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <Badge variant="success">Terkirim</Badge>
                            <h2 className="mt-2 text-xl font-semibold text-text">
                                {report.event ?? report.title}
                            </h2>
                            <p className="text-sm text-muted">
                                {report.brand ?? 'Event'}
                                {report.venue ? ` · ${report.venue}` : ''}
                                {report.city ? `, ${report.city}` : ''}
                            </p>
                        </div>
                        <p className="text-sm text-muted">
                            Dikirim {formatDateTime(report.created_at)}
                        </p>
                    </div>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <Detail label="Tanggal Event" value={formatDate(report.event_date)} />
                        <Detail label="Jenis Laporan" value={variantLabel(report.variant)} />
                        <Detail label="Pengirim" value={report.user ?? '-'} />
                        <Detail
                            label="Status Absensi"
                            value={report.attendance_status ?? 'Tidak tercatat'}
                        />
                        <Detail label="Tanggal Kegiatan" value={formatDate(report.activity_date)} />
                        <Detail
                            label="Jam Kegiatan"
                            value={`${report.activity_start_time ?? '-'} - ${report.activity_end_time ?? '-'}`}
                        />
                    </dl>
                </Card>

                {(report.photos.length > 0 || report.videos.length > 0) && (
                    <Card className="space-y-4 p-5">
                        <h2 className="font-semibold text-text">Dokumentasi</h2>
                        {report.photos.length > 0 && (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {report.photos.map((photo, index) => (
                                    <a
                                        key={photo.id ?? `photo-${index}`}
                                        href={photo.url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <img
                                            src={photo.url}
                                            alt={`Foto laporan ${index + 1}`}
                                            className="h-52 w-full rounded-lg object-cover"
                                        />
                                    </a>
                                ))}
                            </div>
                        )}
                        {report.videos.map((video) => (
                            <video
                                key={video.id}
                                src={video.url}
                                controls
                                preload="metadata"
                                className="max-h-96 w-full rounded-lg bg-black"
                            />
                        ))}
                    </Card>
                )}

                <Card className="space-y-4 p-5">
                    <h2 className="font-semibold text-text">Hasil Kegiatan</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Metric
                            title="Sampling"
                            target={formatNumber(report.sampling_target ?? 0)}
                            actual={formatNumber(report.sampling_actual ?? 0)}
                        />
                        <Metric
                            title="Selling"
                            target={formatCurrency(report.selling_target ?? 0)}
                            actual={formatCurrency(report.selling_actual ?? 0)}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {report.variant !== 'instore' && (
                            <PackageList
                                title="Selling Booth"
                                packages={[
                                    report.booth_package_1,
                                    report.booth_package_2,
                                    report.booth_package_3,
                                ]}
                            />
                        )}
                        {report.variant !== 'school' && (
                            <PackageList
                                title="Selling Toko"
                                packages={[
                                    report.store_package_1,
                                    report.store_package_2,
                                    report.store_package_3,
                                ]}
                            />
                        )}
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-muted">Learning / Catatan</h3>
                        <p className="mt-1 whitespace-pre-wrap text-sm text-text">
                            {report.learning || report.body || 'Tidak ada catatan.'}
                        </p>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted">{label}</dt>
            <dd className="font-medium text-text">{value}</dd>
        </div>
    );
}

function Metric({
    title,
    target,
    actual,
}: {
    title: string;
    target: string;
    actual: string;
}) {
    return (
        <div className="rounded-xl bg-page/60 p-4">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-muted">{title}</h3>
            <dl className="mt-2 flex justify-between gap-3 text-sm">
                <dt className="text-muted">Target</dt>
                <dd className="font-medium text-text">{target}</dd>
            </dl>
            <dl className="flex justify-between gap-3 text-sm">
                <dt className="text-muted">Actual</dt>
                <dd className="font-medium text-text">{actual}</dd>
            </dl>
        </div>
    );
}

function PackageList({ title, packages }: { title: string; packages: (number | null)[] }) {
    return (
        <div className="rounded-xl border border-border p-3">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-muted">{title}</h3>
            <ul className="mt-2 space-y-1 text-sm">
                {packages.map((value, index) => (
                    <li key={index} className="flex justify-between gap-3">
                        <span className="text-muted">Paket {index + 1}</span>
                        <span className="font-medium text-text">
                            {value === null ? '-' : `${formatNumber(value)} pcs`}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function variantLabel(variant: QuickReport['variant']): string {
    if (variant === 'school') {
        return 'School Sampling';
    }

    if (variant === 'instore') {
        return 'InStore';
    }

    return 'Umum';
}
