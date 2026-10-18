import { EmptyState, PageHeader, Pagination } from '@/Components/AppBits';
import QuickReportForm, {
    EMPTY_QUICK_REPORT_FORM,
    QuickReportEventOption,
    QuickReportFormData,
} from '@/Components/QuickReportForm';
import { Button, Card, Modal } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/utils';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type QuickReport = {
    id: number;
    title: string;
    body: string | null;
    photo_url: string | null;
    created_at: string | null;
    event: string | null;
    event_date: string | null;
    user: string | null;
};

type Paginator = {
    data: QuickReport[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

export default function QuickReportsIndex({
    reports,
    canCreate,
    events,
}: {
    reports: Paginator;
    canCreate: boolean;
    events: QuickReportEventOption[];
}) {
    const [createOpen, setCreateOpen] = useState(false);
    const form = useForm<QuickReportFormData>(EMPTY_QUICK_REPORT_FORM);

    const openCreate = () => {
        form.setData(EMPTY_QUICK_REPORT_FORM);
        form.clearErrors();
        setCreateOpen(true);
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('quick-reports.store'), {
            forceFormData: true,
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
            },
        });
    };

    return (
        <AppLayout title="Quick Report">
            <Head title="Quick Report" />
            <PageHeader
                title="Quick Report"
                subtitle="Catatan singkat kegiatan lapangan beserta bukti foto."
            >
                {canCreate && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Buat Quick Report
                    </Button>
                )}
            </PageHeader>

            {reports.data.length === 0 ? (
                <Card>
                    <EmptyState
                        title="Belum ada Quick Report"
                        description="Laporan yang dikirim untuk event yang dapat Anda akses akan tampil di sini."
                    />
                </Card>
            ) : (
                <>
                    <div className="grid gap-4 lg:grid-cols-2">
                        {reports.data.map((report) => (
                            <Card key={report.id} className="overflow-hidden">
                                {report.photo_url && (
                                    <img
                                        src={report.photo_url}
                                        alt={`Bukti foto: ${report.title}`}
                                        className="h-56 w-full bg-slate-100 object-cover"
                                        loading="lazy"
                                    />
                                )}
                                <div className="space-y-2 p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <h2 className="font-semibold text-slate-900">
                                            {report.title}
                                        </h2>
                                        <time className="text-xs text-slate-400">
                                            {formatDateTime(report.created_at)}
                                        </time>
                                    </div>
                                    <p className="text-sm text-slate-600">
                                        {report.body || 'Tidak ada catatan tambahan.'}
                                    </p>
                                    <div className="border-t border-slate-100 pt-2 text-xs text-slate-500">
                                        <span className="font-medium text-slate-700">
                                            {report.event ?? 'Event'}
                                        </span>
                                        {report.event_date && ` · ${report.event_date}`}
                                        {report.user && ` · ${report.user}`}
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                    <Pagination paginator={reports} />
                </>
            )}

            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                size="lg"
                title="Buat Quick Report"
                description="Kirim catatan singkat kegiatan lapangan beserta foto."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setCreateOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="quick-report-create-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing || events.length === 0}
                        >
                            {form.processing ? 'Mengirim…' : 'Kirim'}
                        </Button>
                    </>
                }
            >
                <form id="quick-report-create-form" onSubmit={submitCreate}>
                    <QuickReportForm form={form} events={events} />
                </form>
            </Modal>
        </AppLayout>
    );
}
