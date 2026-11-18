import { Button, Field, Input, Select, Textarea } from '@/Components/ui';
import { compressImageFiles, formatFileSize } from '@/lib/images';
import { InertiaFormProps } from '@inertiajs/react';
import {
    BarChart3,
    Camera,
    Copy,
    Check,
    MapPin,
    MessageCircle,
    PenLine,
    ShoppingCart,
    Video,
} from 'lucide-react';
import { ChangeEvent, ReactNode, RefObject, useState } from 'react';

export type QuickReportVariant = 'school' | 'instore' | 'general';

export type QuickReportEventOption = {
    id: number;
    event_name: string;
    event_date: string | null;
    activity_date: string | null;
    activity_start_time: string | null;
    activity_end_time: string | null;
    sampling_target: number | null;
    brand: string | null;
    venue: string | null;
    city: string | null;
    event_type_slug: string | null;
    event_type_name: string | null;
    variant: QuickReportVariant;
};

export type QuickReportFormData = {
    event_id: string;
    activity_date: string;
    activity_start_time: string;
    activity_end_time: string;
    sampling_target: string;
    sampling_actual: string;
    selling_target: string;
    selling_actual: string;
    booth_package_1: string;
    booth_package_2: string;
    booth_package_3: string;
    store_package_1: string;
    store_package_2: string;
    store_package_3: string;
    learning: string;
    photos: File[];
    videos: File[];
};

export const EMPTY_QUICK_REPORT_FORM: QuickReportFormData = {
    event_id: '',
    activity_date: '',
    activity_start_time: '',
    activity_end_time: '',
    sampling_target: '',
    sampling_actual: '',
    selling_target: '',
    selling_actual: '',
    booth_package_1: '',
    booth_package_2: '',
    booth_package_3: '',
    store_package_1: '',
    store_package_2: '',
    store_package_3: '',
    learning: '',
    photos: [],
    videos: [],
};

const variantLabel = (variant: QuickReportVariant): string => {
    if (variant === 'school') return 'School Sampling';
    if (variant === 'instore') return 'InStore';

    return 'Quick Report';
};

const formatRupiah = (value: string): string =>
    `Rp ${new Intl.NumberFormat('id-ID').format(Number(value) || 0)}`;

const formatSchoolDate = (value: string): string => {
    if (!value) {
        return '-';
    }

    const date = new Date(`${value}T00:00:00`);

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        weekday: 'long',
    })
        .format(date)
        .toUpperCase();
};

export const buildQuickReportMessage = (
    event: QuickReportEventOption | undefined,
    data: QuickReportFormData,
): string => {
    if (!event) {
        return '';
    }

    if (event.variant === 'school') {
        return [
            '*QUICK REPORT*',
            '*MILO SCHOOL SAMPLING*',
            '',
            `Hari/Tgl : ${formatSchoolDate(data.activity_date || event.activity_date || event.event_date || '')}`,
            `Sekolah  : ${event.venue || event.event_name}`,
            `Kota     : ${event.city ?? '-'}`,
            '',
            '*KPI SAMPLING*',
            `Target : ${data.sampling_target || 0}`,
            `Actual : ${data.sampling_actual || 0}`,
            '',
            'Learning:',
            data.learning || '-',
        ].join('\n');
    }

    const lines = [
        '*QUICK REPORT*',
        `*${variantLabel(event.variant)}*`,
        '',
        `Event: ${event.event_name}`,
        `Brand: ${event.brand ?? '-'}`,
        `Hari/Tanggal: ${data.activity_date || event.activity_date || event.event_date || '-'}`,
        `Waktu: ${data.activity_start_time || '-'} - ${data.activity_end_time || '-'}`,
        `Venue: ${event.venue ?? '-'}`,
        `Kota: ${event.city ?? '-'}`,
        '',
        `Sampling: ${data.sampling_actual || 0} / ${data.sampling_target || 0}`,
        `Selling: ${formatRupiah(data.selling_actual)} / ${formatRupiah(
            data.selling_target,
        )}`,
    ];

    if (event.variant !== 'instore') {
        lines.push(
            '',
            'Selling Booth (Pcs)',
            `Paket 1: ${data.booth_package_1 || 0}`,
            `Paket 2: ${data.booth_package_2 || 0}`,
            `Paket 3: ${data.booth_package_3 || 0}`,
        );
    }

    lines.push(
        '',
        'Selling Toko (Pcs)',
        `Paket 1: ${data.store_package_1 || 0}`,
        `Paket 2: ${data.store_package_2 || 0}`,
        `Paket 3: ${data.store_package_3 || 0}`,
    );

    lines.push('', 'Learning:', data.learning || '-');

    return lines.join('\n');
};

function Section({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
                <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-page text-muted">
                    {icon}
                </span>
                {title}
            </div>
            {children}
        </div>
    );
}

function NumberField({
    label,
    value,
    error,
    onChange,
    prefix,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    prefix?: string;
}) {
    return (
        <Field label={label} error={error}>
            <div className="relative">
                {prefix && (
                    <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted">
                        {prefix}
                    </span>
                )}
                <Input
                    type="number"
                    min={0}
                    step={prefix === 'Rp' ? 1000 : 1}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className={prefix ? 'pl-10' : undefined}
                />
            </div>
        </Field>
    );
}

export default function QuickReportForm({
    form,
    events,
    photosInputRef,
    videosInputRef,
}: {
    form: InertiaFormProps<QuickReportFormData>;
    events: QuickReportEventOption[];
    photosInputRef?: RefObject<HTMLInputElement | null>;
    videosInputRef?: RefObject<HTMLInputElement | null>;
}) {
    const { data, setData, errors } = form;
    const [copied, setCopied] = useState(false);
    const [compressingPhotos, setCompressingPhotos] = useState(false);
    const selectedEvent = events.find((event) => String(event.id) === data.event_id);
    const message = buildQuickReportMessage(selectedEvent, data);
    const totalUploadBytes = [...data.photos, ...data.videos].reduce(
        (total, file) => total + file.size,
        0,
    );
    const uploadTooLarge = totalUploadBytes > 120 * 1024 * 1024;

    const handlePhotosChange = async (event: ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files ?? []);

        setData('photos', files);

        if (files.length === 0) {
            return;
        }

        setCompressingPhotos(true);

        try {
            setData('photos', await compressImageFiles(files));
        } finally {
            setCompressingPhotos(false);
        }
    };

    const selectEvent = (value: string) => {
        const event = events.find((item) => String(item.id) === value);

        setData({
            ...data,
            event_id: value,
            activity_date: event?.activity_date ?? '',
            activity_start_time: event?.activity_start_time ?? '',
            activity_end_time: event?.activity_end_time ?? '',
            sampling_target:
                event?.sampling_target !== null && event?.sampling_target !== undefined
                    ? String(event.sampling_target)
                    : '',
        });
    };

    return (
        <div className="space-y-6">
            <Section icon={<PenLine className="h-3.5 w-3.5" />} title="Informasi Event">
                <Field label="Event" error={errors.event_id}>
                    <Select value={data.event_id} onChange={(e) => selectEvent(e.target.value)}>
                        <option value="">Pilih event yang ditugaskan</option>
                        {events.map((event) => (
                            <option key={event.id} value={event.id}>
                                {event.event_name}
                                {event.event_date ? ` (${event.event_date})` : ''}
                            </option>
                        ))}
                    </Select>
                </Field>
                {selectedEvent && (
                    <div className="grid gap-2 rounded-xl bg-page/60 p-3 text-xs text-muted sm:grid-cols-2">
                        <p className="sm:col-span-2">
                            <span className="inline-flex rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-primary">
                                Template {variantLabel(selectedEvent.variant)}
                            </span>
                        </p>
                        <p>
                            <span className="font-medium text-text">Brand:</span>{' '}
                            {selectedEvent.brand ?? '-'}
                        </p>
                        <p className="inline-flex items-center gap-1">
                            <MapPin className="h-3.5 w-3.5" />
                            {selectedEvent.venue ?? 'Lokasi belum diisi'}
                        </p>
                        <p>
                            <span className="font-medium text-text">Kota:</span>{' '}
                            {selectedEvent.city ?? '-'}
                        </p>
                    </div>
                )}
            </Section>

            <Section icon={<PenLine className="h-3.5 w-3.5" />} title="Jadwal & Absensi">
                <p className="text-xs text-muted">
                    Tanggal dan jam otomatis mengikuti jadwal yang ditetapkan PIC. Sesuaikan
                    bila ada perubahan aktual sebelum mengirim laporan.
                </p>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="Tanggal Kegiatan" error={errors.activity_date}>
                        <Input
                            type="date"
                            value={data.activity_date}
                            onChange={(e) => setData('activity_date', e.target.value)}
                        />
                    </Field>
                    <Field label="Jam Mulai" error={errors.activity_start_time}>
                        <Input
                            type="time"
                            value={data.activity_start_time}
                            onChange={(e) => setData('activity_start_time', e.target.value)}
                        />
                    </Field>
                    <Field label="Jam Selesai" error={errors.activity_end_time}>
                        <Input
                            type="time"
                            value={data.activity_end_time}
                            onChange={(e) => setData('activity_end_time', e.target.value)}
                        />
                    </Field>
                </div>
            </Section>

            <Section icon={<BarChart3 className="h-3.5 w-3.5" />} title="KPI Sampling">
                <div className="grid gap-4 sm:grid-cols-2">
                    <NumberField
                        label="Target"
                        value={data.sampling_target}
                        error={errors.sampling_target}
                        onChange={(value) => setData('sampling_target', value)}
                    />
                    <NumberField
                        label="Actual"
                        value={data.sampling_actual}
                        error={errors.sampling_actual}
                        onChange={(value) => setData('sampling_actual', value)}
                    />
                </div>
                <p className="text-xs text-muted">
                    Target terisi otomatis dari Jumlah Siswa versi Sekolah pada FJP dan dapat
                    disesuaikan sebelum dikirim.
                </p>
            </Section>

            {selectedEvent?.variant !== 'school' && (
                <Section icon={<ShoppingCart className="h-3.5 w-3.5" />} title="KPI Selling">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <NumberField
                            label="Target"
                            prefix="Rp"
                            value={data.selling_target}
                            error={errors.selling_target}
                            onChange={(value) => setData('selling_target', value)}
                        />
                        <NumberField
                            label="Actual"
                            prefix="Rp"
                            value={data.selling_actual}
                            error={errors.selling_actual}
                            onChange={(value) => setData('selling_actual', value)}
                        />
                    </div>
                </Section>
            )}

            {selectedEvent?.variant !== 'instore' && selectedEvent?.variant !== 'school' && (
                <Section
                    icon={<ShoppingCart className="h-3.5 w-3.5" />}
                    title="Selling Booth (Pcs)"
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <NumberField
                            label="Paket 1 Renceng"
                            value={data.booth_package_1}
                            error={errors.booth_package_1}
                            onChange={(value) => setData('booth_package_1', value)}
                        />
                        <NumberField
                            label="Paket 2 Renceng"
                            value={data.booth_package_2}
                            error={errors.booth_package_2}
                            onChange={(value) => setData('booth_package_2', value)}
                        />
                        <NumberField
                            label="Paket 3 Renceng"
                            value={data.booth_package_3}
                            error={errors.booth_package_3}
                            onChange={(value) => setData('booth_package_3', value)}
                        />
                    </div>
                </Section>
            )}

            {selectedEvent?.variant !== 'school' && (
                <Section
                    icon={<ShoppingCart className="h-3.5 w-3.5" />}
                    title="Selling Toko (Pcs)"
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <NumberField
                            label="Paket 1 Renceng"
                            value={data.store_package_1}
                            error={errors.store_package_1}
                            onChange={(value) => setData('store_package_1', value)}
                        />
                        <NumberField
                            label="Paket 2 Renceng"
                            value={data.store_package_2}
                            error={errors.store_package_2}
                            onChange={(value) => setData('store_package_2', value)}
                        />
                        <NumberField
                            label="Paket 3 Renceng"
                            value={data.store_package_3}
                            error={errors.store_package_3}
                            onChange={(value) => setData('store_package_3', value)}
                        />
                    </div>
                </Section>
            )}

            <Section icon={<PenLine className="h-3.5 w-3.5" />} title="Learning">
                <Field label="Pembelajaran lapangan" error={errors.learning}>
                    <Textarea
                        rows={4}
                        maxLength={3000}
                        value={data.learning}
                        onChange={(e) => setData('learning', e.target.value)}
                        placeholder="Contoh: pengunjung pasar kebanyakan pedagang, pasar ramai pada malam hari."
                    />
                </Field>
            </Section>

            <Section
                icon={<MessageCircle className="h-3.5 w-3.5" />}
                title="Preview WhatsApp"
            >
                <div className="rounded-xl border border-border bg-page/60 p-3">
                    <pre className="max-h-72 overflow-auto whitespace-pre-wrap font-sans text-sm text-text">
                        {message || 'Pilih event untuk melihat preview pesan.'}
                    </pre>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={!message}
                    onClick={async () => {
                        await navigator.clipboard.writeText(message);
                        setCopied(true);
                        window.setTimeout(() => setCopied(false), 2000);
                    }}
                >
                    {copied ? (
                        <Check className="h-4 w-4" />
                    ) : (
                        <Copy className="h-4 w-4" />
                    )}
                    {copied ? 'Tersalin' : 'Salin Pesan'}
                </Button>
            </Section>

            <Section
                icon={<Camera className="h-3.5 w-3.5" />}
                title="Foto & Video Aktivitas"
            >
                <Field
                    label="Foto kegiatan (bisa pilih beberapa; JPG, PNG, atau WEBP; maks. 10 MB per foto; foto besar otomatis diperkecil)"
                    error={
                        errors.photos ??
                        errors['photos.0'] ??
                        (errors as Record<string, string | undefined>).photo
                    }
                >
                    <Input
                        ref={photosInputRef}
                        required
                        multiple
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={handlePhotosChange}
                    />
                </Field>
                {compressingPhotos && (
                    <p className="text-xs text-muted">Mengoptimalkan ukuran foto…</p>
                )}
                {data.photos.length > 0 && (
                    <ul className="space-y-1 rounded-xl border border-border bg-page/60 p-3 text-xs text-muted">
                        {data.photos.map((file, index) => (
                            <li key={`${file.name}-${index}`} className="truncate">
                                Foto {index + 1}: {file.name} · {formatFileSize(file.size)}
                            </li>
                        ))}
                    </ul>
                )}
                <Field
                    label={`Video aktivitas${selectedEvent?.variant === 'school' ? ' (wajib)' : ''} (MP4, MOV, atau WEBM; maks. 50 MB per video)`}
                    error={errors.videos ?? errors['videos.0']}
                >
                    <Input
                        ref={videosInputRef}
                        multiple
                        required={selectedEvent?.variant === 'school'}
                        type="file"
                        accept="video/mp4,video/quicktime,video/webm"
                        onChange={(e) => setData('videos', Array.from(e.target.files ?? []))}
                    />
                </Field>
                {data.videos.length > 0 && (
                    <div className="flex items-center justify-between gap-3 rounded-xl border border-border bg-page/60 p-3 text-xs text-muted">
                        <ul className="space-y-1">
                            {data.videos.map((file, index) => (
                                <li key={`${file.name}-${index}`} className="inline-flex items-center gap-2">
                                    <Video className="h-4 w-4 shrink-0" />
                                    {file.name} · {formatFileSize(file.size)}
                                </li>
                            ))}
                        </ul>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setData('videos', []);
                                if (videosInputRef?.current) {
                                    videosInputRef.current.value = '';
                                }
                            }}
                        >
                            Hapus Video
                        </Button>
                    </div>
                )}
                {totalUploadBytes > 0 && (
                    <p className={uploadTooLarge ? 'text-sm font-medium text-danger' : 'text-xs text-muted'}>
                        Total file: {formatFileSize(totalUploadBytes)} · maks. 120 MB per pengiriman.
                        {uploadTooLarge ? ' Kurangi jumlah atau ukuran video.' : ''}
                    </p>
                )}
                {events.length === 0 && (
                    <p className="text-sm text-warning">
                        Belum ada event aktif yang ditugaskan kepada Anda.
                    </p>
                )}
            </Section>
        </div>
    );
}
