import { PageHeader } from '@/Components/AppBits';
import QuickReportForm, {
    EMPTY_QUICK_REPORT_FORM,
    QuickReportEventOption,
    QuickReportFormData,
} from '@/Components/QuickReportForm';
import { Button, Card, CardContent, CardHeader, CardTitle } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { Camera, ClipboardList, FileText, ImageOff, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef } from 'react';

export default function QuickReportsCreate({
    events,
    initialEventId,
}: {
    events: QuickReportEventOption[];
    initialEventId?: number | null;
}) {
    const form = useForm<QuickReportFormData>({
        ...EMPTY_QUICK_REPORT_FORM,
        event_id: initialEventId ? String(initialEventId) : '',
    });
    const { post, processing } = form;
    const photoInputRef = useRef<HTMLInputElement | null>(null);

    const photo = form.data.photo;
    const previewUrl = useMemo(() => (photo ? URL.createObjectURL(photo) : null), [photo]);

    useEffect(() => () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
    }, [previewUrl]);

    const selectedEvent = events.find((event) => String(event.id) === form.data.event_id);

    const clearPhoto = () => {
        form.setData('photo', null);
        if (photoInputRef.current) {
            photoInputRef.current.value = '';
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('quick-reports.store'), { forceFormData: true });
    };

    return (
        <AppLayout title="Buat Quick Report">
            <Head title="Buat Quick Report" />
            <PageHeader
                title="Buat Quick Report"
                subtitle="Kirim catatan singkat kegiatan lapangan"
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <form onSubmit={submit} className="lg:col-span-2">
                    <Card className="p-5 sm:p-6">
                        <QuickReportForm
                            form={form}
                            events={events}
                            photoInputRef={photoInputRef}
                        />
                    </Card>

                    <div className="mt-4 flex items-center gap-2">
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={processing || events.length === 0}
                        >
                            {processing ? 'Mengirim…' : 'Kirim Quick Report'}
                        </Button>
                        <Link href={route('quick-reports.index')}>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </Link>
                    </div>
                </form>

                <aside className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Camera className="h-4 w-4 text-slate-400" /> Preview Bukti Foto
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {previewUrl ? (
                                <div className="space-y-3">
                                    <img
                                        src={previewUrl}
                                        alt="Preview bukti foto"
                                        className="max-h-64 w-full rounded-xl border border-slate-200 object-contain bg-slate-50"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={clearPhoto}
                                    >
                                        <Trash2 className="h-4 w-4" /> Hapus Foto
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center">
                                    <ImageOff className="h-8 w-8 text-slate-300" />
                                    <p className="text-sm font-medium text-slate-500">
                                        Belum ada foto dipilih
                                    </p>
                                    <p className="text-xs text-slate-400">
                                        Foto yang Anda pilih akan tampil di sini sebelum dikirim.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ClipboardList className="h-4 w-4 text-slate-400" /> Ringkasan Laporan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">
                                    Event
                                </p>
                                <p className="mt-0.5 font-medium text-slate-800">
                                    {selectedEvent ? selectedEvent.event_name : 'Belum dipilih'}
                                </p>
                                {selectedEvent?.event_date && (
                                    <p className="text-xs text-slate-400">
                                        {formatDate(selectedEvent.event_date)}
                                    </p>
                                )}
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">
                                    Judul
                                </p>
                                <p className="mt-0.5 font-medium text-slate-800">
                                    {form.data.title || 'Belum diisi'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">
                                    Catatan
                                </p>
                                <p className="mt-0.5 whitespace-pre-wrap text-slate-700">
                                    {form.data.body || 'Belum diisi'}
                                </p>
                            </div>
                            <div className="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                <FileText className="h-3.5 w-3.5" />
                                {form.data.body.length}/3000 karakter ·{' '}
                                {photo ? 'Foto siap dikirim' : 'Foto belum dipilih'}
                            </div>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </AppLayout>
    );
}
