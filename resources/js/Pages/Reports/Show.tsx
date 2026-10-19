import { PageHeader } from '@/Components/AppBits';
import { Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Modal, StatCard, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime, formatNumber } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import { router, useForm } from '@inertiajs/react';
import { Percent, Target, TrendingUp } from 'lucide-react';
import { useState } from 'react';

export default function ReportsShow({
    report,
    documents,
    can,
}: {
    report: any;
    documents: any[];
    can: { verify: boolean; revision: boolean; submit: boolean };
}) {
    const [revisionOpen, setRevisionOpen] = useState(false);
    const revisionForm = useForm({ revision_note: '' });

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
                    <CardTitle>Dokumen</CardTitle>
                </CardHeader>
                <CardContent>
                    {documents.length === 0 ? (
                        <p className="text-sm text-muted">Belum ada dokumen.</p>
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nama</TH>
                                    <TH>Kategori</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {documents.map((d) => (
                                    <TR key={d.id}>
                                        <TD>{d.original_name}</TD>
                                        <TD>{d.category}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
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
