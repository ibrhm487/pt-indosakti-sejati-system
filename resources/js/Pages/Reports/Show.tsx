import { ConfirmButton, PageHeader } from '@/Components/AppBits';
import { Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Modal, Select, StatCard, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime, formatNumber } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import { router, useForm } from '@inertiajs/react';
import { Download, Percent, Target, TrendingUp, Trash2 } from 'lucide-react';
import { useState } from 'react';

type ReportDocument = {
    id: number;
    category: string;
    original_name: string;
    mime: string | null;
    preview_url: string;
    download_url: string;
    can_delete: boolean;
};

export default function ReportsShow({
    report,
    documents,
    can,
}: {
    report: any;
    documents: ReportDocument[];
    can: { verify: boolean; revision: boolean; submit: boolean; upload: boolean };
}) {
    const [revisionOpen, setRevisionOpen] = useState(false);
    const revisionForm = useForm({ revision_note: '' });
    const uploadForm = useForm<{
        documentable_type: string;
        documentable_id: number;
        category: string;
        files: File[];
    }>({
        documentable_type: 'report',
        documentable_id: report.id,
        category: 'photo',
        files: [],
    });

    const submitReport = () => {
        router.post(route('reports.submit', report.id), {}, { preserveScroll: true });
    };

    const verifyReport = () => {
        router.post(route('reports.verify', report.id), {}, { preserveScroll: true });
    };

    const requestRevision = () => {
        revisionForm.post(route('reports.revision', report.id), {
            preserveScroll: true,
            onSuccess: () => {
                setRevisionOpen(false);
                revisionForm.reset();
            },
        });
    };

    const submitUpload = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post(route('documents.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => uploadForm.reset('files'),
        });
    };

    const canSubmit = can.submit;
    const canVerify = can.verify && report.status === 'submitted';
    const canRequestRevision = can.revision && report.status === 'submitted';

    return (
        <AppLayout title="Detail Laporan">
            <Head title={`Laporan - ${report.event}`} />

            <PageHeader
                title={`Laporan ${report.event ?? ''}`}
                subtitle="Detail laporan harian, realisasi sampling, dan verifikasi."
                breadcrumbs={[
                    { label: 'Laporan', href: route('reports.index') },
                    { label: 'Detail' },
                ]}
            />

            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Target Sampling"
                    value={formatNumber(report.sampling_target ?? 0)}
                    icon={<Target className="h-5 w-5" />}
                    tone="info"
                />
                <StatCard
                    label="Realisasi Sampling"
                    value={formatNumber(report.actual_sampling ?? 0)}
                    icon={<TrendingUp className="h-5 w-5" />}
                    tone="success"
                />
                <StatCard
                    label="Pencapaian"
                    value={report.achievement ? `${report.achievement.toFixed(1)}%` : '-'}
                    icon={<Percent className="h-5 w-5" />}
                    tone={
                        report.achievement >= 100
                            ? 'success'
                            : report.achievement >= 75
                              ? 'warning'
                              : report.achievement > 0
                                ? 'danger'
                                : 'default'
                    }
                />
            </div>

            <Card className="mb-6">
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Detail Laporan</CardTitle>
                    <StatusBadge status={report.status} label={report.status_label} />
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <Detail label="Event" value={report.event} />
                        <Detail label="Tanggal Event" value={formatDate(report.event_date)} />
                        <Detail label="Target Sampling" value={report.sampling_target} />
                        <Detail label="Realisasi Sampling" value={report.actual_sampling} />
                        <Detail label="Pencapaian" value={report.achievement ? report.achievement.toFixed(1) + '%' : '-'} />
                        <Detail label="Permasalahan" value={report.issues} className="col-span-2" />
                        <Detail label="Catatan" value={report.notes} className="col-span-2" />
                        <Detail label="Diajukan Oleh" value={report.submitted_by} />
                        <Detail label="Tanggal Diajukan" value={formatDateTime(report.submitted_at)} />
                        <Detail label="Diverifikasi Oleh" value={report.verified_by} />
                        <Detail label="Tanggal Diverifikasi" value={formatDateTime(report.verified_at)} />
                        <Detail label="Catatan Revisi" value={report.revision_note} className="col-span-2" />
                    </dl>
                </CardContent>
            </Card>

            <div className="mb-6 flex flex-wrap gap-2">
                {canSubmit && (
                    <Button variant="primary" onClick={submitReport}>
                        Ajukan
                    </Button>
                )}
                {canVerify && (
                    <Button variant="success" onClick={verifyReport}>
                        Verifikasi
                    </Button>
                )}
                {canRequestRevision && (
                    <Button variant="outline" onClick={() => setRevisionOpen(true)}>
                        Minta Revisi
                    </Button>
                )}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Dokumen & Foto Bukti</CardTitle>
                </CardHeader>
                <CardContent>
                    {can.upload && (
                        <form
                            onSubmit={submitUpload}
                            className="mb-5 grid gap-3 border-b border-border pb-5 sm:grid-cols-[1fr_180px_auto] sm:items-end"
                        >
                            <Field
                                label="File (bisa pilih beberapa)"
                                error={
                                    uploadForm.errors.files ??
                                    (
                                        uploadForm.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    )['files.0']
                                }
                            >
                                <Input
                                    type="file"
                                    multiple
                                    accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx"
                                    onChange={(e) =>
                                        uploadForm.setData(
                                            'files',
                                            Array.from(e.target.files ?? []),
                                        )
                                    }
                                />
                                <p className="mt-1 text-xs text-muted">
                                    Foto, video, PDF, DOC, XLS · maksimal 100 MB per file.
                                </p>
                            </Field>
                            <Field label="Kategori" error={uploadForm.errors.category}>
                                <Select
                                    value={uploadForm.data.category}
                                    onChange={(e) => uploadForm.setData('category', e.target.value)}
                                >
                                    <option value="photo">Foto bukti</option>
                                    <option value="video">Video bukti</option>
                                    <option value="document">Dokumen</option>
                                    <option value="supporting">Pendukung</option>
                                    <option value="other">Lainnya</option>
                                </Select>
                            </Field>
                            <Button
                                type="submit"
                                variant="primary"
                                disabled={
                                    uploadForm.processing || uploadForm.data.files.length === 0
                                }
                            >
                                {uploadForm.processing ? 'Mengunggah…' : 'Unggah'}
                            </Button>
                        </form>
                    )}
                    {documents.length === 0 ? (
                        <p className="text-sm text-muted">Belum ada dokumen.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Pratinjau</TH>
                                        <TH>Nama</TH>
                                        <TH>Kategori</TH>
                                        <TH className="text-right">Aksi</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {documents.map((d) => (
                                        <TR key={d.id}>
                                            <TD>
                                                {(d.mime ?? '').startsWith('image/') ? (
                                                    <a
                                                        href={d.preview_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <img
                                                            src={d.preview_url}
                                                            alt={d.original_name}
                                                            className="h-12 w-16 rounded-md border border-border object-cover"
                                                        />
                                                    </a>
                                                ) : (d.mime ?? '').startsWith('video/') ? (
                                                    <video
                                                        src={d.preview_url}
                                                        controls
                                                        className="h-12 w-20 rounded-md bg-black object-contain"
                                                    />
                                                ) : (
                                                    <span className="text-xs text-muted">-</span>
                                                )}
                                            </TD>
                                            <TD>{d.original_name}</TD>
                                            <TD className="capitalize">{d.category}</TD>
                                            <TD className="text-right">
                                                <div className="flex items-center justify-end gap-3">
                                                    <a
                                                        href={d.download_url}
                                                        className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                        Unduh
                                                    </a>
                                                    {d.can_delete && (
                                                        <ConfirmButton
                                                            title="Hapus dokumen"
                                                            description={`Hapus dokumen "${d.original_name}"?`}
                                                            confirmLabel="Ya, hapus"
                                                            onConfirm={() =>
                                                                router.delete(
                                                                    route(
                                                                        'documents.destroy',
                                                                        d.id,
                                                                    ),
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                            Hapus
                                                        </ConfirmButton>
                                                    )}
                                                </div>
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Modal
                open={revisionOpen}
                onClose={() => setRevisionOpen(false)}
                title="Minta Revisi"
                footer={
                    <>
                        <Button variant="outline" size="sm" onClick={() => setRevisionOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={requestRevision}
                            disabled={revisionForm.processing}
                        >
                            {revisionForm.processing ? 'Mengirim…' : 'Kirim'}
                        </Button>
                    </>
                }
            >
                <Field label="Catatan Revisi" error={revisionForm.errors.revision_note}>
                    <Input
                        value={revisionForm.data.revision_note}
                        onChange={(e) => revisionForm.setData('revision_note', e.target.value)}
                    />
                </Field>
            </Modal>
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
