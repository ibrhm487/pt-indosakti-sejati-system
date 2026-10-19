import {
    CardTable,
    EmptyState,
    FilterCard,
    PageHeader,
    Pagination,
} from '@/Components/AppBits';
import ReportForm, {
    EMPTY_REPORT_FORM,
    ReportEventOption,
    ReportFormData,
} from '@/Components/ReportForm';
import {
    Button,
    Input,
    Modal,
    Select,
    StatusBadge,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatNumber } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function ReportsIndex({
    reports,
    filters,
    statuses,
    canCreate,
    events,
}: {
    reports: any;
    filters: { status?: string; date_from?: string; date_to?: string };
    statuses: { value: string; label: string }[];
    canCreate: boolean;
    events: ReportEventOption[];
}) {
    const [status, setStatus] = useState(filters.status ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const form = useForm<ReportFormData>(EMPTY_REPORT_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('reports.index'),
            { status, date_from: dateFrom, date_to: dateTo },
            { preserveState: true },
        );
    };

    const reset = () => {
        setStatus('');
        setDateFrom('');
        setDateTo('');
        router.get(route('reports.index'), {}, { preserveState: true });
    };

    const openCreate = () => {
        form.setData(EMPTY_REPORT_FORM);
        form.clearErrors();
        setCreateOpen(true);
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('reports.store'), {
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
            },
        });
    };

    return (
        <AppLayout title="Laporan">
            <Head title="Laporan" />
            <PageHeader
                title="Laporan Event"
                subtitle="Daftar laporan pelaksanaan event"
            >
                {canCreate && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Laporan Baru
                    </Button>
                )}
            </PageHeader>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <Select
                        className="sm:w-56"
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                    >
                        <option value="">Semua status</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </Select>
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Dari Tanggal
                        <Input
                            type="date"
                            className="sm:w-44"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Sampai Tanggal
                        <Input
                            type="date"
                            className="sm:w-44"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                        />
                    </label>
                    <Button
                        type="submit"
                        variant="default"
                        className="h-11 rounded-xl px-5"
                    >
                        Terapkan
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11 rounded-xl px-5"
                        onClick={reset}
                    >
                        Reset
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {reports.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada laporan"
                        description="Buat laporan baru untuk memulai."
                    />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Event</TH>
                                    <TH>Tanggal</TH>
                                    <TH className="text-right">Target</TH>
                                    <TH className="text-right">Realisasi</TH>
                                    <TH className="text-right">Pencapaian</TH>
                                    <TH>Status</TH>
                                    <TH>Diajukan Oleh</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {reports.data.map((r: any) => (
                                    <TR key={r.id}>
                                        <TD>
                                            <Link
                                                href={route('reports.show', r.id)}
                                                className="font-medium text-text hover:text-primary"
                                            >
                                                {r.event}
                                            </Link>
                                        </TD>
                                        <TD>{formatDate(r.event_date)}</TD>
                                        <TD className="text-right">
                                            {formatNumber(r.sampling_target)}
                                        </TD>
                                        <TD className="text-right">
                                            {formatNumber(r.actual_sampling)}
                                        </TD>
                                        <TD className="text-right">
                                            {r.achievement
                                                ? r.achievement.toFixed(1) + '%'
                                                : '-'}
                                        </TD>
                                        <TD>
                                            <StatusBadge
                                                status={r.status}
                                                label={r.status_label}
                                            />
                                        </TD>
                                        <TD>{r.submitted_by ?? '-'}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={reports} />
                    </>
                )}
            </CardTable>

            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                size="lg"
                title="Laporan Baru"
                description="Catat realisasi sampling untuk sebuah event."
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
                            form="report-create-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="report-create-form" onSubmit={submitCreate}>
                    <ReportForm form={form} events={events} />
                </form>
            </Modal>
        </AppLayout>
    );
}
