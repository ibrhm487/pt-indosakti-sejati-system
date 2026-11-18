import { EmptyState, PageHeader, Pagination } from '@/Components/AppBits';
import { Badge, Button, Card } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';

type QuickReportSummary = {
    id: number;
    title: string;
    created_at: string | null;
    event: string | null;
    event_date: string | null;
    brand: string | null;
    venue: string | null;
    city: string | null;
    photos: { id: number | null; url: string }[];
    videos: { id: number; url: string; mime: string | null }[];
};

type Paginator = {
    data: QuickReportSummary[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

export default function QuickReportsHistory({ reports }: { reports: Paginator }) {
    return (
        <AppLayout title="Riwayat Laporan Saya">
            <Head title="Riwayat Laporan Saya" />
            <PageHeader
                title="Riwayat Laporan Saya"
                subtitle="Semua laporan yang sudah dikirim beserta dokumentasi tersimpan di sini."
            />

            {reports.data.length === 0 ? (
                <Card>
                    <EmptyState
                        title="Belum ada laporan terkirim"
                        description="Laporan yang sudah dikirim dari menu Quick Report akan tersimpan di sini."
                    />
                </Card>
            ) : (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        {reports.data.map((report) => (
                            <Card key={report.id} className="overflow-hidden">
                                {(report.photos.length > 0 || report.videos.length > 0) && (
                                    <div className="grid grid-cols-3 gap-2 bg-page p-3">
                                        {report.photos.slice(0, 3).map((photo, index) => (
                                            <img
                                                key={photo.id ?? `photo-${index}`}
                                                src={photo.url}
                                                alt={`Foto laporan ${index + 1}`}
                                                className="h-24 w-full rounded-lg object-cover"
                                                loading="lazy"
                                            />
                                        ))}
                                        {report.photos.length === 0 &&
                                            report.videos[0] && (
                                            <video
                                                src={report.videos[0].url}
                                                controls
                                                preload="metadata"
                                                className="col-span-3 max-h-48 w-full rounded-lg bg-black"
                                            />
                                        )}
                                    </div>
                                )}
                                <div className="space-y-3 p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <Badge variant="success">Terkirim</Badge>
                                            <h2 className="mt-2 truncate font-semibold text-text">
                                                {report.event ?? report.title}
                                            </h2>
                                            <p className="text-sm text-muted">
                                                {report.brand ?? 'Event'}
                                                {report.venue ? ` · ${report.venue}` : ''}
                                                {report.city ? `, ${report.city}` : ''}
                                            </p>
                                        </div>
                                        <time className="shrink-0 text-xs text-muted">
                                            {formatDateTime(report.created_at)}
                                        </time>
                                    </div>
                                    <p className="text-sm text-muted">
                                        Tanggal event: {formatDate(report.event_date)}
                                    </p>
                                    <Link href={route('quick-reports.show', report.id)}>
                                        <Button size="sm" variant="outline">
                                            Lihat detail laporan
                                        </Button>
                                    </Link>
                                </div>
                            </Card>
                        ))}
                    </div>
                    <Pagination paginator={reports} />
                </>
            )}
        </AppLayout>
    );
}
