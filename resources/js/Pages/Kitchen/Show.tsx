import { ModalForm, PageHeader } from '@/Components/AppBits';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Field,
    Input,
    Modal,
    Select,
    StatusBadge,
    Table,
    TBody,
    TD,
    Textarea,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate, formatDateTime } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ChefHat,
    ClipboardList,
    Droplets,
    ImagePlus,
    PackageCheck,
    Trash2,
} from 'lucide-react';
import { FormEvent, ReactNode, useEffect, useMemo, useRef, useState } from 'react';

type KitchenData = {
    id: number;
    event: string | null;
    event_date: string | null;
    jug_quantity: number;
    preparation_date: string | null;
    preparation_time: string | null;
    assignee: string | null;
    notes: string | null;
    status: string;
    status_label: string;
    stage: string;
    stage_label: string;
    completed: boolean;
};

type MediaItem = {
    id: number;
    type: string;
    url: string;
    original_name: string;
    size: number;
};

type MediaGroups = {
    washing: MediaItem[];
    cooking: MediaItem[];
    ready: MediaItem[];
};

type ReportRow = {
    id: number;
    prepared_quantity: number;
    condition: string | null;
    notes: string | null;
    prepared_by: string | null;
    prepared_at: string | null;
    washing_at: string | null;
    cooking_at: string | null;
    ready_at: string | null;
    stage: string;
    stage_label: string;
    photo_url: string | null;
    washing_photo_url: string | null;
    ready_photo_url: string | null;
    media: MediaGroups;
};

type Workflow = {
    id: number;
    washing_at: string | null;
    cooking_at: string | null;
    ready_at: string | null;
    stage: string;
    stage_label: string;
    completed: boolean;
    notes: string | null;
    media: MediaGroups;
};

export default function KitchenShow({
    kitchen,
    workflow,
    reports,
    statuses,
    can,
}: {
    kitchen: KitchenData;
    workflow: Workflow | null;
    reports: ReportRow[];
    statuses: { value: string; label: string }[];
    can: { manage: boolean; report_mode?: boolean };
}) {
    const [statusOpen, setStatusOpen] = useState(false);
    const statusForm = useForm({ status: kitchen.status });

    const openStatus = () => {
        statusForm.setData('status', kitchen.status);
        statusForm.clearErrors();
        setStatusOpen(true);
    };

    const submitStatus = (e: FormEvent) => {
        e.preventDefault();
        statusForm.patch(route('kitchen.status', kitchen.id), {
            preserveScroll: true,
            onSuccess: () => setStatusOpen(false),
        });
    };

    const media = workflow?.media ?? { washing: [], cooking: [], ready: [] };
    const cancelled = kitchen.status === 'cancelled';
    const hasWashing = Boolean(workflow?.washing_at) || media.washing.length > 0;
    const hasCooking = Boolean(workflow?.cooking_at) || media.cooking.length > 0;
    const completed =
        kitchen.completed ||
        workflow?.completed === true ||
        Boolean(workflow?.ready_at) ||
        media.ready.length > 0;

    return (
        <AppLayout title={can.report_mode ? 'Laporan Kitchen' : 'Detail Tugas Kitchen'}>
            <Head title={`Kitchen - ${kitchen.event}`} />
            <PageHeader
                title={can.report_mode ? 'Laporan Kitchen' : 'Detail Tugas Kitchen'}
                subtitle={kitchen.event ?? '-'}
            >
                <Link href={route('kitchen.index')}>
                    <Button variant="outline">Kembali</Button>
                </Link>
                {can.manage && !can.report_mode && !completed && (
                    <Button variant="outline" onClick={openStatus}>
                        Ubah Status
                    </Button>
                )}
            </PageHeader>

            <Card className="mb-6 rounded-xl">
                <CardHeader className="flex flex-wrap items-center justify-between gap-3">
                    <CardTitle>Ringkasan</CardTitle>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={kitchen.status} label={kitchen.status_label} />
                        <span className="rounded-full bg-page px-2.5 py-0.5 text-xs font-medium text-muted">
                            {workflow?.stage_label ?? kitchen.stage_label}
                        </span>
                    </div>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        <Detail label="Event" value={kitchen.event} />
                        <Detail label="Tanggal Event" value={formatDate(kitchen.event_date)} />
                        <Detail label="Jumlah JUG" value={kitchen.jug_quantity} />
                        {!can.report_mode && (
                            <Detail label="Tanggal Persiapan" value={formatDate(kitchen.preparation_date)} />
                        )}
                        {!can.report_mode && <Detail label="Waktu Persiapan" value={kitchen.preparation_time} />}
                        {!can.report_mode && <Detail label="Penanggung Jawab" value={kitchen.assignee} />}
                        <Detail label="Catatan" value={kitchen.notes} className="col-span-2 sm:col-span-3" />
                    </dl>
                </CardContent>
            </Card>

            {completed && (
                <div className="mb-6 flex items-start gap-3 rounded-xl border border-success/30 bg-success/10 p-4 text-sm text-success">
                    <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p className="font-semibold">
                            {can.report_mode ? 'Laporan kitchen selesai' : 'Tugas kitchen selesai'}
                        </p>
                        <p className="text-success/90">
                            Semua JUG sudah disiapkan. Waktu selesai: {formatDateTime(workflow?.ready_at)}.
                        </p>
                    </div>
                </div>
            )}

            <Card className="mb-6 rounded-xl">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <ClipboardList className="h-4 w-4 text-muted" /> Alur Kerja Malam Ini
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <StageUploader
                        kitchenId={kitchen.id}
                        stage="washing"
                        field="washing_photos"
                        endpoint="kitchen.reports.washing"
                        step={1}
                        icon={<Droplets className="h-4 w-4" />}
                        title="Cuci JUG"
                        description="Unggah semua foto proses pencucian JUG. Tanggal dan jam dicatat otomatis oleh sistem."
                        timestamp={workflow?.washing_at ?? null}
                        timestampLabel="Waktu cuci"
                        media={media.washing}
                        enabled={can.manage && !cancelled && !completed}
                        completed={hasWashing}
                        locked={completed}
                        hint="Minimal 1 foto. Bisa lebih dari 3 foto sekaligus."
                    />

                    <StageUploader
                        kitchenId={kitchen.id}
                        stage="cooking"
                        field="cooking_photos"
                        endpoint="kitchen.reports.cooking"
                        step={2}
                        icon={<ChefHat className="h-4 w-4" />}
                        title="Masak Susu (SOP Nestlé)"
                        description="Unggah semua foto persiapan masak susu sesuai SOP. Foto digabung dalam laporan yang sama."
                        timestamp={workflow?.cooking_at ?? null}
                        timestampLabel="Waktu masak"
                        media={media.cooking}
                        enabled={can.manage && !cancelled && !completed && hasWashing}
                        completed={Boolean(workflow?.cooking_at) || media.cooking.length > 0}
                        locked={completed}
                        hint="Aktif setelah laporan cuci JUG tersimpan."
                    />

                    <StageUploader
                        kitchenId={kitchen.id}
                        stage="ready"
                        field="ready_photos"
                        endpoint="kitchen.reports.ready"
                        step={3}
                        icon={<PackageCheck className="h-4 w-4" />}
                        title="JUG Siap"
                        description={
                            can.report_mode
                                ? 'Unggah foto seluruh JUG yang sudah siap. Setelah tersimpan, laporan otomatis selesai.'
                                : 'Unggah foto seluruh JUG yang sudah siap. Setelah tersimpan, tugas kitchen otomatis selesai.'
                        }
                        timestamp={workflow?.ready_at ?? null}
                        timestampLabel="Waktu siap"
                        media={media.ready}
                        enabled={can.manage && !cancelled && !completed && hasCooking}
                        completed={Boolean(workflow?.ready_at) || media.ready.length > 0}
                        locked={completed}
                        hint="Aktif setelah laporan masak susu tersimpan."
                    />
                </CardContent>
            </Card>

            <Card className="rounded-xl">
                <CardHeader className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <ClipboardList className="h-4 w-4 text-muted" /> Riwayat Laporan Kitchen
                    </CardTitle>
                </CardHeader>
                <CardContent className={reports.length === 0 ? '' : 'p-0'}>
                    {reports.length === 0 ? (
                        <p className="text-sm text-muted">Belum ada laporan.</p>
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Cuci</TH>
                                    <TH>Masak</TH>
                                    <TH>Siap</TH>
                                    <TH>Oleh</TH>
                                    <TH>Catatan</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {reports.map((r) => (
                                    <TR key={r.id}>
                                        <TD>
                                            <StageCell time={r.washing_at} media={r.media.washing} />
                                        </TD>
                                        <TD>
                                            <StageCell time={r.cooking_at} media={r.media.cooking} />
                                        </TD>
                                        <TD>
                                            <StageCell time={r.ready_at} media={r.media.ready} />
                                        </TD>
                                        <TD>{r.prepared_by ?? '-'}</TD>
                                        <TD className="max-w-xs text-muted">{r.notes ?? '-'}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <ModalForm
                open={statusOpen}
                onClose={() => setStatusOpen(false)}
                title="Ubah Status Kitchen"
                description="Perbarui status pengerjaan tugas kitchen."
                onSubmit={submitStatus}
                submitLabel={statusForm.processing ? 'Menyimpan…' : 'Simpan'}
                submitDisabled={statusForm.processing}
                size="sm"
            >
                <Field label="Status">
                    <Select
                        value={statusForm.data.status}
                        onChange={(e) => statusForm.setData('status', e.target.value)}
                    >
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </Select>
                </Field>
            </ModalForm>
        </AppLayout>
    );
}

function StageUploader({
    kitchenId,
    stage,
    field,
    endpoint,
    step,
    icon,
    title,
    description,
    timestamp,
    timestampLabel,
    media,
    enabled,
    completed,
    locked,
    hint,
}: {
    kitchenId: number;
    stage: string;
    field: 'washing_photos' | 'cooking_photos' | 'ready_photos';
    endpoint: string;
    step: number;
    icon: ReactNode;
    title: string;
    description: string;
    timestamp: string | null;
    timestampLabel: string;
    media: MediaItem[];
    enabled: boolean;
    completed: boolean;
    locked: boolean;
    hint: string;
}) {
    const form = useForm<{ [key: string]: File[] | string }>({
        [field]: [],
        notes: '',
    });
    const inputRef = useRef<HTMLInputElement | null>(null);
    const [viewer, setViewer] = useState<MediaItem | null>(null);

    const files = useMemo(() => (form.data[field] as File[]) ?? [], [form.data, field]);
    const previews = useMemo(
        () => files.map((file) => ({ file, url: URL.createObjectURL(file) })),
        [files],
    );

    useEffect(
        () => () => previews.forEach((preview) => URL.revokeObjectURL(preview.url)),
        [previews],
    );

    const fieldErrors = form.errors as Record<string, string | undefined>;
    const error =
        fieldErrors[field] ??
        Object.entries(fieldErrors).find(([key]) => key.startsWith(`${field}.`))?.[1] ??
        form.errors.notes;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route(endpoint, kitchenId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                if (inputRef.current) inputRef.current.value = '';
            },
        });
    };

    const removeExisting = (id: number) => {
        router.delete(route('kitchen.reports.media.destroy', id), { preserveScroll: true });
    };

    return (
        <div
            className={cn(
                'rounded-xl border p-4 sm:p-5',
                completed
                    ? 'border-success/30 bg-success/5'
                    : enabled
                      ? 'border-border bg-surface'
                      : 'border-dashed border-border bg-page/50',
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    <span
                        className={cn(
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-semibold',
                            completed ? 'bg-success text-white' : 'bg-page text-muted',
                        )}
                    >
                        {completed ? <CheckCircle2 className="h-4 w-4" /> : step}
                    </span>
                    <div>
                        <p className="flex items-center gap-2 text-sm font-semibold text-text">
                            {icon} {title}
                        </p>
                        <p className="mt-1 max-w-2xl text-xs text-muted">{description}</p>
                    </div>
                </div>
                <div className="text-right">
                    <p className="text-[11px] uppercase tracking-wide text-muted">{timestampLabel}</p>
                    <p className="text-sm font-medium text-text">
                        {timestamp ? formatDateTime(timestamp) : 'Belum tercatat'}
                    </p>
                </div>
            </div>

            {media.length > 0 && (
                <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                    {media.map((item) => (
                        <div key={item.id} className="group relative overflow-hidden rounded-lg border border-border">
                            <button
                                type="button"
                                onClick={() => setViewer(item)}
                                className="block w-full"
                                title={item.original_name}
                            >
                                <img
                                    src={item.url}
                                    alt={item.original_name}
                                    className="h-24 w-full object-cover transition-transform group-hover:scale-105"
                                />
                            </button>
                            {!locked && (
                                <button
                                    type="button"
                                    onClick={() => removeExisting(item.id)}
                                    className="absolute right-1 top-1 rounded-md bg-text/70 p-1 text-white opacity-0 transition-opacity hover:bg-danger group-hover:opacity-100"
                                    title="Hapus foto"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {enabled && (
                <form onSubmit={submit} className="mt-4 space-y-3">
                    <Field label={`Tambah foto ${stage === 'washing' ? 'cuci' : stage === 'cooking' ? 'masak' : 'JUG siap'}`} error={error}>
                        <Input
                            ref={inputRef}
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            capture="environment"
                            onChange={(e) => form.setData(field, Array.from(e.target.files ?? []))}
                        />
                    </Field>

                    {previews.length > 0 && (
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                            {previews.map((preview, index) => (
                                <img
                                    key={`${preview.file.name}-${index}`}
                                    src={preview.url}
                                    alt={preview.file.name}
                                    className="h-24 w-full rounded-lg border border-border object-cover"
                                />
                            ))}
                        </div>
                    )}

                    <Field label="Catatan (opsional)" error={form.errors.notes}>
                        <Textarea
                            value={(form.data.notes as string) ?? ''}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Catatan tambahan untuk tahap ini"
                        />
                    </Field>

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-muted">{hint}</p>
                        <Button type="submit" variant="primary" disabled={form.processing || files.length === 0}>
                            <ImagePlus className="h-4 w-4" />
                            {form.processing ? 'Menyimpan…' : 'Simpan Laporan'}
                        </Button>
                    </div>
                </form>
            )}

            {!enabled && !completed && (
                <p className="mt-3 text-xs text-muted">{hint}</p>
            )}

            <Modal open={viewer !== null} onClose={() => setViewer(null)} size="xl" title={viewer?.original_name}>
                {viewer && (
                    <img
                        src={viewer.url}
                        alt={viewer.original_name}
                        className="max-h-[70vh] w-full rounded-lg object-contain"
                    />
                )}
            </Modal>
        </div>
    );
}

function StageCell({ time, media }: { time: string | null; media: MediaItem[] }) {
    if (!time && media.length === 0) {
        return <span className="text-muted">-</span>;
    }

    return (
        <div className="space-y-1">
            <p className="text-xs text-muted">{time ? formatDateTime(time) : '-'}</p>
            {media.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {media.map((item, index) => (
                        <a
                            key={item.id}
                            href={item.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-xs font-medium text-primary hover:underline"
                        >
                            Foto {index + 1}
                        </a>
                    ))}
                </div>
            )}
        </div>
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
