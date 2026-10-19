import { PageHeader } from '@/Components/AppBits';
import ReportForm, {
    EMPTY_REPORT_FORM,
    ReportEventOption,
    ReportFormData,
} from '@/Components/ReportForm';
import { Button, Card, CardContent, CardHeader, CardTitle, StatCard } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList, Percent, Target, TrendingUp } from 'lucide-react';
import { FormEvent } from 'react';

export default function ReportsCreate({
    events,
}: {
    events: ReportEventOption[];
}) {
    const form = useForm<ReportFormData>(EMPTY_REPORT_FORM);
    const { post, processing } = form;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('reports.store'));
    };

    const selectedEvent = events.find((event) => String(event.id) === form.data.event_id);
    const target = Number(form.data.sampling_target || selectedEvent?.sampling_target || 0);
    const actual = Number(form.data.actual_sampling || 0);
    const achievement = target > 0 ? (actual / target) * 100 : null;

    return (
        <AppLayout title="Buat Laporan">
            <Head title="Buat Laporan" />
            <PageHeader
                title="Buat Laporan"
                subtitle="Catat realisasi sampling untuk sebuah event"
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <form onSubmit={submit} className="lg:col-span-2">
                    <Card className="p-5 sm:p-6">
                        <ReportForm form={form} events={events} />
                    </Card>

                    <div className="mt-4 flex items-center gap-2">
                        <Button type="submit" variant="primary" disabled={processing}>
                            {processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                        <Link href={route('reports.index')}>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </Link>
                    </div>
                </form>

                <aside className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                        <StatCard
                            label="Target"
                            value={target > 0 ? formatNumber(target) : '-'}
                            icon={<Target className="h-5 w-5" />}
                            tone="info"
                        />
                        <StatCard
                            label="Realisasi"
                            value={form.data.actual_sampling ? formatNumber(actual) : '-'}
                            icon={<TrendingUp className="h-5 w-5" />}
                            tone="success"
                        />
                        <StatCard
                            label="Pencapaian"
                            value={achievement !== null ? `${achievement.toFixed(1)}%` : '-'}
                            icon={<Percent className="h-5 w-5" />}
                            tone={
                                achievement === null
                                    ? 'default'
                                    : achievement >= 100
                                      ? 'success'
                                      : achievement >= 75
                                        ? 'warning'
                                        : 'danger'
                            }
                        />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ClipboardList className="h-4 w-4 text-muted" /> Ringkasan Laporan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted">
                                    Event
                                </p>
                                <p className="mt-0.5 font-medium text-text">
                                    {selectedEvent ? selectedEvent.event_name : 'Belum dipilih'}
                                </p>
                                {selectedEvent?.event_date && (
                                    <p className="text-xs text-muted">
                                        {selectedEvent.event_date}
                                    </p>
                                )}
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted">
                                    Permasalahan
                                </p>
                                <p className="mt-0.5 text-text">
                                    {form.data.issues || 'Tidak ada kendala yang dituliskan'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted">
                                    Catatan
                                </p>
                                <p className="mt-0.5 whitespace-pre-wrap text-text">
                                    {form.data.notes || 'Belum diisi'}
                                </p>
                            </div>
                            <p className="rounded-lg bg-page px-3 py-2 text-xs text-muted">
                                {selectedEvent
                                    ? 'Target sampling akan mengikuti event bila dikosongkan.'
                                    : 'Pilih event terlebih dahulu untuk melihat target.'}
                            </p>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </AppLayout>
    );
}
