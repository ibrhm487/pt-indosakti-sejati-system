import { EmptyState, PageHeader } from '@/Components/AppBits';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Modal, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function PermitsShow({
    permit,
    documents,
}: {
    permit: any;
    documents: any[];
}) {
    const permissions: string[] = usePage().props.auth.permissions;
    const canUpdate = permissions.includes('permits.update');
    const canApprove = permissions.includes('permits.approve');
    const canReject = permissions.includes('permits.reject');

    const [modalAction, setModalAction] = useState<string | null>(null);
    const [reason, setReason] = useState('');

    const docForm = useForm<{ category: string; file: File | null }>({
        category: 'supporting',
        file: null,
    });

    const workflow = (action: string, why?: string) => {
        router.post(route('permits.transition', permit.id), { action, reason: why ?? '' }, { preserveScroll: true });
    };

    const openReasonModal = (action: string) => {
        setReason('');
        setModalAction(action);
    };

    const submitReason = () => {
        if (modalAction) {
            workflow(modalAction, reason);
        }
        setModalAction(null);
        setReason('');
    };

    const uploadDocument = (e: FormEvent) => {
        e.preventDefault();
        docForm.post(route('permits.documents.store', permit.id), {
            preserveScroll: true,
            onSuccess: () => docForm.reset('file'),
        });
    };

    const status = permit.status;
    const actionTitle: Record<string, string> = {
        reject: 'Tolak Perizinan',
        revision: 'Minta Revisi',
        cancel: 'Batalkan Perizinan',
    };

    return (
        <AppLayout title="Detail Perizinan">
            <Head title={permit.permit_number} />
            <PageHeader title={permit.event ?? 'Perizinan'} subtitle={permit.permit_number}>
                {canUpdate && status === 'pending' && (
                    <Button variant="primary" onClick={() => workflow('submit')}>Ajukan</Button>
                )}
                {canApprove && status === 'submitted' && (
                    <Button variant="success" onClick={() => workflow('approve')}>Setujui</Button>
                )}
                {canReject && status === 'submitted' && (
                    <>
                        <Button variant="destructive" onClick={() => openReasonModal('reject')}>Tolak</Button>
                        <Button variant="outline" onClick={() => openReasonModal('revision')}>Minta Revisi</Button>
                    </>
                )}
                {canUpdate && status !== 'cancelled' && status !== 'approved' && (
                    <Button variant="outline" onClick={() => openReasonModal('cancel')}>Batalkan</Button>
                )}
            </PageHeader>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex items-center justify-between">
                        <CardTitle>Detail Perizinan</CardTitle>
                        <StatusBadge status={permit.status} label={permit.status_label} />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <Detail label="Nomor Izin" value={permit.permit_number} />
                            <Detail label="Event" value={permit.event} />
                            <Detail label="Lokasi" value={permit.venue} />
                            <Detail label="Tenggat" value={formatDate(permit.deadline)} />
                            <Detail label="Penanggung Jawab" value={permit.assignee} className="col-span-2" />
                            <Detail label="Catatan" value={permit.notes} className="col-span-2" />
                        </dl>

                        {permit.reason && (
                            <p className="mt-4 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
                                Alasan: {permit.reason}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Dokumen</CardTitle>
                </CardHeader>
                <CardContent>
                    {documents.length === 0 ? (
                        <EmptyState title="Belum ada dokumen" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nama</TH>
                                    <TH>Kategori</TH>
                                    <TH>Versi</TH>
                                    <TH></TH>
                                </TR>
                            </THead>
                            <TBody>
                                {documents.map((d) => (
                                    <TR key={d.id}>
                                        <TD>{d.original_name}</TD>
                                        <TD><Badge variant="outline">{d.category}</Badge></TD>
                                        <TD>v{d.version}</TD>
                                        <TD className="text-right">
                                            <a href={d.url}>
                                                <Button size="sm" variant="ghost"><Download className="h-4 w-4" /></Button>
                                            </a>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}

                    {canUpdate && (
                        <form onSubmit={uploadDocument} className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <Field label="Kategori" className="sm:w-48">
                                <Select value={docForm.data.category} onChange={(e) => docForm.setData('category', e.target.value)}>
                                    <option value="supporting">Pendukung</option>
                                    <option value="permit">Perizinan</option>
                                    <option value="school">Sekolah</option>
                                    <option value="other">Lainnya</option>
                                </Select>
                            </Field>
                            <Field label="File" className="flex-1" error={docForm.errors.file}>
                                <Input type="file" onChange={(e) => docForm.setData('file', e.target.files?.[0] ?? null)} />
                            </Field>
                            <Button type="submit" variant="primary" disabled={docForm.processing}>
                                <Upload className="h-4 w-4" /> Unggah
                            </Button>
                        </form>
                    )}
                </CardContent>
            </Card>

            <Modal
                open={modalAction !== null}
                onClose={() => setModalAction(null)}
                title={modalAction ? actionTitle[modalAction] : 'Konfirmasi'}
                footer={
                    <>
                        <Button variant="outline" size="sm" onClick={() => setModalAction(null)}>Batal</Button>
                        <Button variant="destructive" size="sm" onClick={submitReason}>Kirim</Button>
                    </>
                }
            >
                <Field label="Alasan">
                    <Input value={reason} onChange={(e) => setReason(e.target.value)} />
                </Field>
            </Modal>
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
