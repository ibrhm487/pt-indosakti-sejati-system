import { ModalForm, PageHeader } from '@/Components/AppBits';
import { Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Select, StatusBadge, Table, TBody, TD, Textarea, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList, ImageOff, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';

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
};

export default function KitchenShow({
    kitchen,
    reports,
    statuses,
    can,
}: {
    kitchen: KitchenData;
    reports: {
        id: number;
        prepared_quantity: number;
        condition: string | null;
        notes: string | null;
        prepared_by: string | null;
        prepared_at: string | null;
        photo_url: string | null;
        washing_photo_url: string | null;
    }[];
    statuses: { value: string; label: string }[];
    can: { manage: boolean };
}) {
    const [statusOpen, setStatusOpen] = useState(false);
    const [reportOpen, setReportOpen] = useState(false);

    const statusForm = useForm({ status: kitchen.status });
    const reportForm = useForm<{ prepared_quantity: string; condition: string; notes: string; photo: File | null; washing_photo: File | null }>({
        prepared_quantity: '',
        condition: '',
        notes: '',
        photo: null,
        washing_photo: null,
    });

    const openStatus = () => {
        statusForm.setData('status', kitchen.status);
        statusForm.clearErrors();
        setStatusOpen(true);
    };

    const photoRef = useRef<HTMLInputElement | null>(null);
    const washingRef = useRef<HTMLInputElement | null>(null);

    const photoPreview = useMemo(
        () => (reportForm.data.photo ? URL.createObjectURL(reportForm.data.photo) : null),
        [reportForm.data.photo],
    );
    const washingPreview = useMemo(
        () => (reportForm.data.washing_photo ? URL.createObjectURL(reportForm.data.washing_photo) : null),
        [reportForm.data.washing_photo],
    );

    useEffect(
        () => () => {
            if (photoPreview) URL.revokeObjectURL(photoPreview);
        },
        [photoPreview],
    );
    useEffect(
        () => () => {
            if (washingPreview) URL.revokeObjectURL(washingPreview);
        },
        [washingPreview],
    );

    const clearPhoto = () => {
        reportForm.setData('photo', null);
        if (photoRef.current) photoRef.current.value = '';
    };

    const clearWashingPhoto = () => {
        reportForm.setData('washing_photo', null);
        if (washingRef.current) washingRef.current.value = '';
    };

    const submitStatus = (e: FormEvent) => {
        e.preventDefault();
        statusForm.patch(route('kitchen.status', kitchen.id), {
            preserveScroll: true,
            onSuccess: () => setStatusOpen(false),
        });
    };

    const openReport = () => {
        reportForm.reset();
        reportForm.clearErrors();
        setReportOpen(true);
    };

    const submitReport = (e: FormEvent) => {
        e.preventDefault();
        reportForm.post(route('kitchen.reports.store', kitchen.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setReportOpen(false);
                reportForm.reset();
            },
        });
    };

    return (
        <AppLayout title="Detail Tugas Kitchen">
            <Head title={`Kitchen - ${kitchen.event}`} />
            <PageHeader title="Detail Tugas Kitchen" subtitle={kitchen.event ?? '-'}>
                <Link href={route('kitchen.index')}>
                    <Button variant="outline">Kembali</Button>
                </Link>
                {can.manage && (
                    <>
                        <Button variant="outline" onClick={openStatus}>
                            Ubah Status
                        </Button>
                        <Button variant="primary" onClick={openReport}>
                            <Plus className="h-4 w-4" /> Laporan
                        </Button>
                    </>
                )}
            </PageHeader>

            <Card className="mb-6 rounded-xl">
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Ringkasan</CardTitle>
                    <StatusBadge status={kitchen.status} label={kitchen.status_label} />
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        <Detail label="Event" value={kitchen.event} />
                        <Detail label="Tanggal Event" value={formatDate(kitchen.event_date)} />
                        <Detail label="Jumlah JUG" value={kitchen.jug_quantity} />
                        <Detail label="Tanggal Persiapan" value={formatDate(kitchen.preparation_date)} />
                        <Detail label="Waktu Persiapan" value={kitchen.preparation_time} />
                        <Detail label="Penanggung Jawab" value={kitchen.assignee} />
                        <Detail label="Catatan" value={kitchen.notes} className="col-span-2 sm:col-span-3" />
                    </dl>
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
                                    <TH>Jumlah</TH>
                                    <TH>Kondisi</TH>
                                    <TH>Catatan</TH>
                                    <TH>Foto</TH>
                                    <TH>Foto Cuci JUG</TH>
                                    <TH>Disiapkan Oleh</TH>
                                    <TH>Waktu</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {reports.map((r) => (
                                    <TR key={r.id}>
                                        <TD>{r.prepared_quantity}</TD>
                                        <TD>{r.condition ?? '-'}</TD>
                                        <TD>{r.notes ?? '-'}</TD>
                                        <TD>
                                            {r.photo_url ? (
                                                <a
                                                    href={r.photo_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-xs text-primary hover:underline"
                                                >
                                                    Lihat
                                                </a>
                                            ) : (
                                                '-'
                                            )}
                                        </TD>
                                        <TD>
                                            {r.washing_photo_url ? (
                                                <a
                                                    href={r.washing_photo_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-xs text-primary hover:underline"
                                                >
                                                    Lihat
                                                </a>
                                            ) : (
                                                '-'
                                            )}
                                        </TD>
                                        <TD>{r.prepared_by ?? '-'}</TD>
                                        <TD>{formatDateTime(r.prepared_at)}</TD>
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

            <ModalForm
                open={reportOpen}
                onClose={() => setReportOpen(false)}
                title="Tambah Laporan Kitchen"
                description="Catat jumlah yang dipersiapkan beserta kondisinya."
                onSubmit={submitReport}
                submitLabel={reportForm.processing ? 'Menyimpan…' : 'Simpan Laporan'}
                submitDisabled={reportForm.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Jumlah Dipersiapkan" error={reportForm.errors.prepared_quantity}>
                        <Input
                            type="number"
                            min={0}
                            value={reportForm.data.prepared_quantity}
                            onChange={(e) => reportForm.setData('prepared_quantity', e.target.value)}
                        />
                    </Field>
                    <Field label="Kondisi" error={reportForm.errors.condition}>
                        <Input
                            value={reportForm.data.condition}
                            onChange={(e) => reportForm.setData('condition', e.target.value)}
                        />
                    </Field>
                    <Field label="Catatan" error={reportForm.errors.notes} className="sm:col-span-2">
                        <Textarea
                            value={reportForm.data.notes}
                            onChange={(e) => reportForm.setData('notes', e.target.value)}
                        />
                    </Field>
                    <Field label="Foto Persiapan" error={reportForm.errors.photo} className="sm:col-span-2">
                        <Input
                            ref={photoRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            onChange={(e) => reportForm.setData('photo', e.target.files?.[0] ?? null)}
                        />
                        <PhotoPreview
                            url={photoPreview}
                            label="Preview foto persiapan"
                            onClear={clearPhoto}
                        />
                    </Field>
                    <Field label="Foto Cuci JUG" error={reportForm.errors.washing_photo} className="sm:col-span-2">
                        <Input
                            ref={washingRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            onChange={(e) => reportForm.setData('washing_photo', e.target.files?.[0] ?? null)}
                        />
                        <PhotoPreview
                            url={washingPreview}
                            label="Preview foto cuci JUG"
                            onClear={clearWashingPhoto}
                        />
                    </Field>
                </div>
            </ModalForm>
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

function PhotoPreview({
    url,
    label,
    onClear,
}: {
    url: string | null;
    label: string;
    onClear: () => void;
}) {
    if (!url) {
        return (
            <p className="mt-2 flex items-center gap-1.5 text-xs text-muted">
                <ImageOff className="h-3.5 w-3.5" /> Belum ada foto dipilih
            </p>
        );
    }

    return (
        <div className="mt-2 space-y-2">
            <img
                src={url}
                alt={label}
                className="h-36 w-full rounded-lg border border-border bg-page object-contain"
            />
            <Button type="button" variant="outline" size="sm" onClick={onClear}>
                <Trash2 className="h-3.5 w-3.5" /> Hapus Foto
            </Button>
        </div>
    );
}
