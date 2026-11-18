import { EmptyState, PageHeader } from '@/Components/AppBits';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime, formatNumber } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, History, MapPin, RefreshCw, Upload } from 'lucide-react';
import { useState } from 'react';

type FjpEvent = {
    id: number;
    event_code: string;
    event_name: string;
    event_date: string | null;
    status: string;
    status_label: string;
    url: string;
};

type HistoryEntry = {
    id: number;
    action: string;
    user: string | null;
    role: string | null;
    created_at: string | null;
    reason: string | null;
};

const HISTORY_LABELS: Record<string, string> = {
    CREATE_FJP: 'FJP dibuat',
    UPDATE_FJP: 'FJP diperbarui',
    UPDATE_FJP_INLINE: 'FJP diperbarui (inline)',
    DELETE_FJP: 'FJP dihapus',
    SYNC_DAPODIK: 'Sinkronisasi Dapodik',
    UPLOAD_FJP_ATTACHMENT: 'Lampiran diunggah',
};

export default function FjpShow({
    fjp,
    attachments,
    events,
    history,
    can,
}: {
    fjp: any;
    attachments: any[];
    events: FjpEvent[];
    history: HistoryEntry[];
    can: {
        update: boolean;
        delete: boolean;
    };
}) {
    const [syncing, setSyncing] = useState(false);

    const attachForm = useForm<{ category: string; file: File | null }>({
        category: 'supporting',
        file: null,
    });

    const syncDapodik = () => {
        if (!fjp.npsn) return;
        setSyncing(true);
        router.post(route('fjp.dapodik', fjp.id), {}, {
            preserveScroll: true,
            onFinish: () => setSyncing(false),
        });
    };

    const uploadAttachment = (e: React.FormEvent) => {
        e.preventDefault();
        attachForm.post(route('fjp.attachments.store', fjp.id), {
            preserveScroll: true,
            onSuccess: () => attachForm.reset('file'),
        });
    };

    return (
        <AppLayout title="Detail FJP">
            <Head title={fjp.event_name} />
            <PageHeader title={fjp.school_name || fjp.event_name} subtitle={fjp.npsn ? `NPSN ${fjp.npsn}` : undefined}>
                {can.update && (
                    <Link href={route('fjp.edit', fjp.id)}>
                        <Button variant="subtle">Ubah</Button>
                    </Link>
                )}
            </PageHeader>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex items-center justify-between">
                        <CardTitle>Detail FJP</CardTitle>
                        <StatusBadge status={fjp.status} label={fjp.status_label} />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <Detail label="Sekolah" value={fjp.school_name} />
                            <Detail label="NPSN" value={fjp.npsn} />
                            <Detail label="Alamat" value={fjp.address} className="col-span-2" />
                            <Detail label="Kota/Kabupaten" value={fjp.city} />
                            <Detail label="Kecamatan" value={fjp.kecamatan} />
                            <Detail label="Kelurahan" value={fjp.kelurahan} />
                            <Detail label="Provinsi" value={fjp.province} />
                            <Detail label="Venue Penjadwalan PIC/Tim" value={fjp.venue_name} className="col-span-2" />
                            <div className="col-span-2">
                                <dt className="text-xs uppercase tracking-wide text-muted">Link Lokasi</dt>
                                <dd className="mt-0.5">
                                    {fjp.maps_url ? (
                                        <a
                                            href={fjp.maps_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-primary hover:text-primary"
                                        >
                                            <MapPin className="h-4 w-4" /> Buka di Google Maps
                                        </a>
                                    ) : (
                                        <span className="text-text">-</span>
                                    )}
                                </dd>
                            </div>
                            <Detail label="Catatan" value={fjp.notes} className="col-span-2" />
                        </dl>

                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex items-center justify-between">
                        <CardTitle>Jumlah Siswa</CardTitle>
                        {can.update && (
                            <Button size="sm" variant="outline" onClick={syncDapodik} disabled={syncing || !fjp.npsn}>
                                <RefreshCw className={`h-3.5 w-3.5 ${syncing ? 'animate-spin' : ''}`} /> Sinkron
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-muted">Versi Sekolah</span>
                            <span className="font-semibold">{formatNumber(fjp.jumlah_siswa_sekolah)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-muted">Versi Dapodik</span>
                            <span className="font-semibold">{formatNumber(fjp.jumlah_siswa_dapodik)}</span>
                        </div>
                        <div className="flex items-center justify-between border-t border-border pt-4">
                            <span className="text-muted">Selisih (Sekolah − Dapodik)</span>
                            <span className="font-semibold">
                                {fjp.difference === null ? 'Belum lengkap' : formatNumber(fjp.difference)}
                            </span>
                        </div>
                        {fjp.dapodik_source && (
                            <>
                                <p className="rounded-md bg-warning/10 px-3 py-2 text-xs text-warning">
                                    Sumber Dapodik: {fjp.dapodik_source}
                                    {fjp.dapodik_synced_at ? ` · ${formatDateTime(fjp.dapodik_synced_at)}` : ''}
                                </p>
                                {String(fjp.dapodik_source).toLowerCase().includes('simulasi') && (
                                    <p className="rounded-md bg-warning/10 px-3 py-2 text-xs text-warning border border-warning/20">
                                        Data Simulasi/Demo (bukan data resmi Dapodik)
                                    </p>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Event Terkait</CardTitle>
                </CardHeader>
                <CardContent>
                    {events.length === 0 ? (
                        <EmptyState
                            title="Belum ada event"
                            description="PIC dapat membuat jadwal dan penugasan tim melalui generator jadwal pada daftar FJP."
                        />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nama Event</TH>
                                    <TH>Tanggal</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {events.map((e) => (
                                    <TR key={e.id}>
                                        <TD>
                                            <Link
                                                href={e.url}
                                                className="font-medium text-text hover:text-primary"
                                            >
                                                {e.event_name}
                                            </Link>
                                            <p className="font-mono text-xs text-muted">
                                                {e.event_code}
                                            </p>
                                        </TD>
                                        <TD>{formatDate(e.event_date)}</TD>
                                        <TD>
                                            <StatusBadge status={e.status} label={e.status_label} />
                                        </TD>
                                        <TD className="text-right">
                                            <Link
                                                href={e.url}
                                                className="text-xs font-medium text-primary hover:underline"
                                            >
                                                Detail
                                            </Link>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Lampiran</CardTitle>
                </CardHeader>
                <CardContent>
                    {attachments.length === 0 ? (
                        <EmptyState title="Belum ada lampiran" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nama</TH>
                                    <TH>Kategori</TH>
                                    <TH>Tanggal</TH>
                                    <TH>Pengunggah</TH>
                                    <TH></TH>
                                </TR>
                            </THead>
                            <TBody>
                                {attachments.map((a) => (
                                    <TR key={a.id}>
                                        <TD>{a.original_name}</TD>
                                        <TD><Badge variant="outline">{a.category}</Badge></TD>
                                        <TD>{formatDateTime(a.uploaded_at)}</TD>
                                        <TD>{a.uploaded_by ?? '-'}</TD>
                                        <TD className="text-right">
                                            <a href={a.url}>
                                                <Button size="sm" variant="ghost"><Download className="h-4 w-4" /></Button>
                                            </a>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}

                    {can.update && (
                        <form onSubmit={uploadAttachment} className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <Field label="Kategori" className="sm:w-48">
                                <Select value={attachForm.data.category} onChange={(e) => attachForm.setData('category', e.target.value)}>
                                    <option value="supporting">Pendukung</option>
                                    <option value="school">Sekolah</option>
                                    <option value="other">Lainnya</option>
                                </Select>
                            </Field>
                            <Field label="File" className="flex-1" error={attachForm.errors.file}>
                                <Input type="file" onChange={(e) => attachForm.setData('file', e.target.files?.[0] ?? null)} />
                            </Field>
                            <Button type="submit" variant="primary" disabled={attachForm.processing}>
                                <Upload className="h-4 w-4" /> Unggah
                            </Button>
                        </form>
                    )}
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <History className="h-4 w-4" /> Riwayat FJP
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {history.length === 0 ? (
                        <EmptyState title="Belum ada riwayat" />
                    ) : (
                        <ol className="space-y-3">
                            {history.map((h) => (
                                <li key={h.id} className="flex items-start justify-between gap-4 rounded-xl bg-page px-3.5 py-2.5 text-sm">
                                    <div>
                                        <p className="font-medium text-text">
                                            {HISTORY_LABELS[h.action] ?? h.action}
                                        </p>
                                        {h.reason && (
                                            <p className="mt-0.5 text-xs text-muted">
                                                Alasan: {h.reason}
                                            </p>
                                        )}
                                    </div>
                                    <div className="shrink-0 text-right text-xs text-muted">
                                        <p className="font-medium text-text">{h.user ?? 'Sistem'}</p>
                                        <p>{formatDateTime(h.created_at)}</p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                </CardContent>
            </Card>

        </AppLayout>
    );
}

function Detail({ label, value, className }: { label: string; value: any; className?: string }) {
    return (
        <div className={className}>
            <dt className="text-xs uppercase tracking-wide text-muted">{label}</dt>
            <dd className="mt-0.5 text-text">{value || '-'}</dd>
        </div>
    );
}
