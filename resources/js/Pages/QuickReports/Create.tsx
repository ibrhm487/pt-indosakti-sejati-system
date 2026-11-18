import { PageHeader } from '@/Components/AppBits';
import QuickReportForm, {
    EMPTY_QUICK_REPORT_FORM,
    QuickReportEventOption,
    QuickReportFormData,
} from '@/Components/QuickReportForm';
import { Button, Card, CardContent, CardHeader, CardTitle } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatCurrency, formatDate, formatNumber } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { Camera, ClipboardList, FileText, ImageOff, Trash2, Video } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef } from 'react';

export default function QuickReportsCreate({
    events,
    initialEventId,
}: {
    events: QuickReportEventOption[];
    initialEventId?: number | null;
}) {
    const initialEvent = events.find((event) => String(event.id) === String(initialEventId ?? ''));
    const form = useForm<QuickReportFormData>({
        ...EMPTY_QUICK_REPORT_FORM,
        event_id: initialEventId ? String(initialEventId) : '',
        activity_date: initialEvent?.activity_date ?? '',
        activity_start_time: initialEvent?.activity_start_time ?? '',
        activity_end_time: initialEvent?.activity_end_time ?? '',
        sampling_target:
            initialEvent?.sampling_target !== null && initialEvent?.sampling_target !== undefined
                ? String(initialEvent.sampling_target)
                : '',
    });
    const { post, processing } = form;
    const photosInputRef = useRef<HTMLInputElement | null>(null);
    const videosInputRef = useRef<HTMLInputElement | null>(null);

    const photoPreviews = useMemo(
        () =>
            form.data.photos.map((file) => ({
                file,
                url: URL.createObjectURL(file),
            })),
        [form.data.photos],
    );
    const videoPreviews = useMemo(
        () =>
            form.data.videos.map((file) => ({
                file,
                url: URL.createObjectURL(file),
            })),
        [form.data.videos],
    );

    useEffect(
        () => () => {
            photoPreviews.forEach((preview) => URL.revokeObjectURL(preview.url));
        },
        [photoPreviews],
    );

    useEffect(
        () => () => {
            videoPreviews.forEach((preview) => URL.revokeObjectURL(preview.url));
        },
        [videoPreviews],
    );

    const selectedEvent = events.find((event) => String(event.id) === form.data.event_id);
    const uploadTooLarge =
        [...form.data.photos, ...form.data.videos].reduce(
            (total, file) => total + file.size,
            0,
        ) > 120 * 1024 * 1024;
    const boothTotal = [
        form.data.booth_package_1,
        form.data.booth_package_2,
        form.data.booth_package_3,
    ].reduce((sum, value) => sum + (Number(value) || 0), 0);
    const storeTotal = [
        form.data.store_package_1,
        form.data.store_package_2,
        form.data.store_package_3,
    ].reduce((sum, value) => sum + (Number(value) || 0), 0);

    const clearPhotos = () => {
        form.setData('photos', []);
        if (photosInputRef.current) {
            photosInputRef.current.value = '';
        }
    };

    const clearVideos = () => {
        form.setData('videos', []);
        if (videosInputRef.current) {
            videosInputRef.current.value = '';
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
                            photosInputRef={photosInputRef}
                            videosInputRef={videosInputRef}
                        />
                    </Card>

                    <div className="mt-4 flex items-center gap-2">
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={processing || events.length === 0 || uploadTooLarge}
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
                                <Camera className="h-4 w-4 text-muted" /> Preview Foto & Video
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {photoPreviews.length > 0 ? (
                                <div className="space-y-3">
                                    <div className="grid grid-cols-2 gap-2">
                                        {photoPreviews.map((preview, index) => (
                                            <img
                                                key={`${preview.file.name}-${index}`}
                                                src={preview.url}
                                                alt={`Preview foto ${index + 1}`}
                                                className="h-28 w-full rounded-xl border border-border bg-page object-cover"
                                            />
                                        ))}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={clearPhotos}
                                    >
                                        <Trash2 className="h-4 w-4" /> Hapus Semua Foto
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border bg-page/60 px-4 py-8 text-center">
                                    <ImageOff className="h-8 w-8 text-muted/60" />
                                    <p className="text-sm font-medium text-muted">
                                        Belum ada foto dipilih
                                    </p>
                                    <p className="text-xs text-muted">
                                        Foto yang Anda pilih akan tampil di sini sebelum dikirim.
                                    </p>
                                </div>
                            )}

                            {videoPreviews.length > 0 && (
                                <div className="mt-4 space-y-2">
                                    <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted">
                                        <Video className="h-3.5 w-3.5" /> Video Aktivitas
                                    </p>
                                    {videoPreviews.map((preview, index) => (
                                        <video
                                            key={`${preview.file.name}-${index}`}
                                            src={preview.url}
                                            controls
                                            className="w-full rounded-xl border border-border bg-black"
                                        />
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={clearVideos}
                                    >
                                        <Trash2 className="h-4 w-4" /> Hapus Semua Video
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>

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
                                        {formatDate(selectedEvent.event_date)}
                                        {selectedEvent.brand ? ` · ${selectedEvent.brand}` : ''}
                                    </p>
                                )}
                                {selectedEvent?.venue && (
                                    <p className="text-xs text-muted">
                                        {selectedEvent.venue}
                                        {selectedEvent.city ? `, ${selectedEvent.city}` : ''}
                                    </p>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted">
                                        Sampling
                                    </p>
                                    <p className="mt-0.5 font-medium text-text">
                                        {formatNumber(form.data.sampling_actual || 0)} /{' '}
                                        {formatNumber(form.data.sampling_target || 0)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted">
                                        Selling
                                    </p>
                                    <p className="mt-0.5 font-medium text-text">
                                        {formatCurrency(form.data.selling_actual || 0)}
                                    </p>
                                    <p className="text-xs text-muted">
                                        Target {formatCurrency(form.data.selling_target || 0)}
                                    </p>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted">
                                        Booth
                                    </p>
                                    <p className="mt-0.5 font-medium text-text">
                                        {formatNumber(boothTotal)} pcs
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted">
                                        Toko
                                    </p>
                                    <p className="mt-0.5 font-medium text-text">
                                        {formatNumber(storeTotal)} pcs
                                    </p>
                                </div>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted">
                                    Learning
                                </p>
                                <p className="mt-0.5 whitespace-pre-wrap text-text">
                                    {form.data.learning || 'Belum diisi'}
                                </p>
                            </div>
                            <div className="flex items-center gap-2 rounded-lg bg-page px-3 py-2 text-xs text-muted">
                                <FileText className="h-3.5 w-3.5" />
                                {form.data.learning.length}/3000 karakter ·{' '}
                                {photoPreviews.length > 0
                                    ? `${photoPreviews.length} foto siap dikirim`
                                    : 'Foto belum dipilih'}
                            </div>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </AppLayout>
    );
}
