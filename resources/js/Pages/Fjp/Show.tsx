import { EmptyState, ModalForm, PageHeader } from '@/Components/AppBits';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Modal, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime, formatNumber } from '@/lib/utils';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarPlus, Download, MapPin, RefreshCw, Upload } from 'lucide-react';
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

export default function FjpShow({
    fjp,
    attachments,
    events,
    pics,
    can,
}: {
    fjp: any;
    attachments: any[];
    events: FjpEvent[];
    pics: { id: number; name: string }[];
    can: { update: boolean; delete: boolean; create_event: boolean };
}) {
    const [rejectOpen, setRejectOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [eventOpen, setEventOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [syncing, setSyncing] = useState(false);

    const attachForm = useForm<{ category: string; file: File | null }>({
        category: 'supporting',
        file: null,
    });

    const eventForm = useForm<{
        pic_id: string;
        event_date: string;
        sampling_target: string;
        notes: string;
    }>({
        pic_id: '',
        event_date: fjp.event_date ?? '',
        sampling_target: fjp.sampling_target != null ? String(fjp.sampling_target) : '',
        notes: '',
    });

    const openCreateEvent = () => {
        eventForm.clearErrors();
        eventForm.setData({
            pic_id: '',
            event_date: fjp.event_date ?? '',
            sampling_target: fjp.sampling_target != null ? String(fjp.sampling_target) : '',
            notes: '',
        });
        setEventOpen(true);
    };

    const submitCreateEvent = (e: React.FormEvent) => {
        e.preventDefault();
        eventForm.post(route('fjp.create-event', fjp.id), {
            onSuccess: () => setEventOpen(false),
        });
    };

    const workflow = (action: string, why?: string) => {
        router.post(route('fjp.workflow', fjp.id), { action, reason: why ?? '' }, { preserveScroll: true });
    };

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

    const dispatch = usePage().props as any;
    const pageErrors: Record<string, string> = dispatch.errors ?? {};

    const status = fjp.permit_status;

    return (
        <AppLayout title="Detail FJP">
            <Head title={fjp.event_name} />
            <PageHeader title={fjp.school_name || fjp.event_name} subtitle={`${fjp.fjp_number}${fjp.npsn ? ` · NPSN ${fjp.npsn}` : ''}`}>
                {can.create_event && status === 'approved' && events.length === 0 && (
                    <Button variant="primary" onClick={openCreateEvent}>
                        <CalendarPlus className="h-4 w-4" /> Buat Event
                    </Button>
                )}
                {can.update && (
                    <>
                        {status === 'draft' && (
                            <Button variant="primary" onClick={() => workflow('submit')}>Ajukan</Button>
                        )}
                        {(status === 'submitted' || status === 'pending') && (
                            <>
                                <Button variant="success" onClick={() => workflow('approve')}>Setujui</Button>
                                <Button variant="destructive" onClick={() => setRejectOpen(true)}>Tolak</Button>
                            </>
                        )}
                        {status !== 'cancelled' && status !== 'approved' && (
                            <Button variant="outline" onClick={() => setCancelOpen(true)}>Batalkan</Button>
                        )}
                        <Link href={route('fjp.edit', fjp.id)}>
                            <Button variant="subtle">Ubah</Button>
                        </Link>
                    </>
                )}
            </PageHeader>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex items-center justify-between">
                        <CardTitle>Detail FJP</CardTitle>
                        <StatusBadge status={fjp.permit_status} label={fjp.permit_status_label} />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <Detail label="Sekolah" value={fjp.school_name} />
                            <Detail label="NPSN" value={fjp.npsn} />
                            <Detail label="Alamat" value={fjp.address} className="col-span-2" />
                            <div className="col-span-2">
                                <dt className="text-xs uppercase tracking-wide text-slate-400">Link Lokasi</dt>
                                <dd className="mt-0.5">
                                    {fjp.maps_url ? (
                                        <a
                                            href={fjp.maps_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-500"
                                        >
                                            <MapPin className="h-4 w-4" /> Buka di Google Maps
                                        </a>
                                    ) : (
                                        <span className="text-slate-800">-</span>
                                    )}
                                </dd>
                            </div>
                            <Detail label="Catatan" value={fjp.notes} className="col-span-2" />
                        </dl>

                        {fjp.rejection_reason && (
                            <p className="mt-4 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
                                Alasan penolakan: {fjp.rejection_reason}
                            </p>
                        )}
                        {fjp.cancellation_reason && (
                            <p className="mt-4 rounded-xl bg-amber-50 px-3.5 py-2.5 text-sm text-amber-700">
                                Alasan pembatalan: {fjp.cancellation_reason}
                            </p>
                        )}
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
                            <span className="text-slate-500">Versi Sekolah</span>
                            <span className="font-semibold">{formatNumber(fjp.jumlah_siswa_sekolah)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-slate-500">Versi Dapodik</span>
                            <span className="font-semibold">{formatNumber(fjp.jumlah_siswa_dapodik)}</span>
                        </div>
                        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
                            <span className="text-slate-500">Selisih (Sekolah − Dapodik)</span>
                            <span className="font-semibold">
                                {fjp.difference === null ? 'Belum lengkap' : formatNumber(fjp.difference)}
                            </span>
                        </div>
                        {fjp.dapodik_source && (
                            <p className="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                Sumber Dapodik: {fjp.dapodik_source}
                                {fjp.dapodik_synced_at ? ` · ${formatDateTime(fjp.dapodik_synced_at)}` : ''}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Event Terkait</CardTitle>
                    {can.create_event && status === 'approved' && events.length > 0 && (
                        <Button variant="outline" size="sm" onClick={openCreateEvent}>
                            <CalendarPlus className="h-4 w-4" /> Buat Event
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    {pageErrors.event && (
                        <p className="mb-4 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
                            {pageErrors.event}
                        </p>
                    )}
                    {events.length === 0 ? (
                        <EmptyState
                            title="Belum ada event"
                            description={
                                status === 'approved'
                                    ? 'Buat event operasional dari FJP ini.'
                                    : 'Event dapat dibuat setelah perizinan FJP disetujui.'
                            }
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
                                                className="font-medium text-slate-800 hover:text-indigo-600"
                                            >
                                                {e.event_name}
                                            </Link>
                                            <p className="font-mono text-xs text-slate-400">
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
                                                className="text-xs font-medium text-indigo-600 hover:underline"
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
                                    <TH></TH>
                                </TR>
                            </THead>
                            <TBody>
                                {attachments.map((a) => (
                                    <TR key={a.id}>
                                        <TD>{a.original_name}</TD>
                                        <TD><Badge variant="outline">{a.category}</Badge></TD>
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
                                    <option value="permit">Perizinan</option>
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

            <Modal
                open={rejectOpen}
                onClose={() => setRejectOpen(false)}
                title="Tolak FJP"
                footer={
                    <>
                        <Button variant="outline" size="sm" onClick={() => setRejectOpen(false)}>Batal</Button>
                        <Button variant="destructive" size="sm" onClick={() => { workflow('reject', reason); setRejectOpen(false); setReason(''); }}>Tolak</Button>
                    </>
                }
            >
                <Field label="Alasan penolakan">
                    <Input value={reason} onChange={(e) => setReason(e.target.value)} />
                </Field>
            </Modal>

            <Modal
                open={cancelOpen}
                onClose={() => setCancelOpen(false)}
                title="Batalkan FJP"
                footer={
                    <>
                        <Button variant="outline" size="sm" onClick={() => setCancelOpen(false)}>Batal</Button>
                        <Button variant="destructive" size="sm" onClick={() => { workflow('cancel', reason); setCancelOpen(false); setReason(''); }}>Batalkan FJP</Button>
                    </>
                }
            >
                <Field label="Alasan pembatalan">
                    <Input value={reason} onChange={(e) => setReason(e.target.value)} />
                </Field>
            </Modal>
            <ModalForm
                open={eventOpen}
                onClose={() => setEventOpen(false)}
                title="Buat Event dari FJP"
                description="Data jadwal dan target diwarisi dari FJP. Tentukan PIC penanggung jawab."
                onSubmit={submitCreateEvent}
                submitLabel="Buat Event"
                submitDisabled={eventForm.processing}
            >
                <Field label="PIC" error={eventForm.errors.pic_id}>
                    <Select
                        value={eventForm.data.pic_id}
                        onChange={(e) => eventForm.setData('pic_id', e.target.value)}
                    >
                        <option value="">- Pilih PIC -</option>
                        {pics.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Tanggal Pelaksanaan" error={eventForm.errors.event_date}>
                    <Input
                        type="date"
                        value={eventForm.data.event_date}
                        onChange={(e) => eventForm.setData('event_date', e.target.value)}
                    />
                </Field>
                <Field label="Target Sampling" error={eventForm.errors.sampling_target}>
                    <Input
                        type="number"
                        min={0}
                        value={eventForm.data.sampling_target}
                        onChange={(e) => eventForm.setData('sampling_target', e.target.value)}
                    />
                </Field>
                <Field label="Catatan" error={eventForm.errors.notes}>
                    <Input
                        value={eventForm.data.notes}
                        onChange={(e) => eventForm.setData('notes', e.target.value)}
                        placeholder="Catatan event (opsional)"
                    />
                </Field>
            </ModalForm>
        </AppLayout>
    );
}

function Detail({ label, value, className }: { label: string; value: any; className?: string }) {
    return (
        <div className={className}>
            <dt className="text-xs uppercase tracking-wide text-slate-400">{label}</dt>
            <dd className="mt-0.5 text-slate-800">{value || '-'}</dd>
        </div>
    );
}
