import {
    CardTable,
    ConfirmButton,
    EmptyState,
    FilterCard,
    ModalForm,
    PageHeader,
    Pagination,
    SearchInput,
    Tabs,
} from '@/Components/AppBits';
import {
    Button,
    Input,
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
import { cn, formatNumber } from '@/lib/utils';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MapPin, Paperclip, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
import { EMPTY_FJP_FORM, FjpFormData, FjpFormFields, FjpFormOptions } from './Form';

type FjpRow = {
    id: number;
    attachments_count: number;
    fjp_number: string;
    event_name: string;
    brand: string | null;
    brand_id: number | null;
    event_type_id: number | null;
    venue_id: number | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    sampling_target: number | null;
    jug_required: boolean;
    school_name: string | null;
    npsn: string | null;
    address: string | null;
    maps_url: string | null;
    sekolah: number | null;
    dapodik: number | null;
    difference: number | null;
    jumlah_siswa_sekolah: number | null;
    jumlah_siswa_dapodik: number | null;
    dapodik_source: string | null;
    status: string;
    status_label: string;
    notes: string | null;
};

function InlineText({
    fjpId,
    field,
    value,
    disabled,
    placeholder,
    className,
}: {
    fjpId: number;
    field: 'notes' | 'address' | 'maps_url';
    value: string | null;
    disabled: boolean;
    placeholder?: string;
    className?: string;
}) {
    const [text, setText] = useState(value ?? '');

    useEffect(() => setText(value ?? ''), [value]);

    const save = () => {
        if ((value ?? '') === text) return;
        router.patch(
            route('fjp.inline', fjpId),
            { [field]: text },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <Input
            value={text}
            disabled={disabled}
            placeholder={placeholder}
            className={className}
            onChange={(e) => setText(e.target.value)}
            onBlur={save}
            onKeyDown={(e) => {
                if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
            }}
        />
    );
}

export default function FjpIndex({
    fjps,
    filters,
    permitStatuses,
    statusCounts,
    totalCount,
    statusOptions,
    formOptions,
}: {
    fjps: { data: FjpRow[] };
    filters: { search?: string; permit_status?: string };
    permitStatuses: { value: string; label: string }[];
    statusCounts: Record<string, number>;
    totalCount: number;
    statusOptions: { value: string; label: string }[];
    formOptions?: FjpFormOptions;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.permit_status ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const permissions = usePage().props.auth.permissions;
    const canCreate = permissions.includes('fjp.create');
    const canUpdate = permissions.includes('fjp.update');
    const canDelete = permissions.includes('fjp.delete');

    const form = useForm<FjpFormData>(EMPTY_FJP_FORM);

    const applyFilter = (permitStatus: string) => {
        const params: Record<string, string> = {};
        if (search) params.search = search;
        if (permitStatus) params.permit_status = permitStatus;
        router.get(route('fjp.index'), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        applyFilter(status);
    };

    const changeStatus = (fjpId: number, value: string) => {
        router.patch(
            route('fjp.inline', fjpId),
            { status: value },
            { preserveScroll: true, preserveState: true },
        );
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(EMPTY_FJP_FORM);
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (f: FjpRow) => {
        setEditingId(f.id);
        form.setData({
            school_name: f.school_name ?? '',
            npsn: f.npsn ?? '',
            address: f.address ?? '',
            maps_url: f.maps_url ?? '',
            jumlah_siswa_sekolah:
                f.jumlah_siswa_sekolah != null ? String(f.jumlah_siswa_sekolah) : '',
            jumlah_siswa_dapodik:
                f.jumlah_siswa_dapodik != null ? String(f.jumlah_siswa_dapodik) : '',
            dapodik_source: f.dapodik_source ?? '',
            notes: f.notes ?? '',
            brand_id: f.brand_id != null ? String(f.brand_id) : '',
            event_type_id: f.event_type_id != null ? String(f.event_type_id) : '',
            venue_id: f.venue_id != null ? String(f.venue_id) : '',
            event_date: f.event_date ?? '',
            start_time: f.start_time ?? '',
            end_time: f.end_time ?? '',
            sampling_target: f.sampling_target != null ? String(f.sampling_target) : '',
            jug_required: Boolean(f.jug_required),
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setEditingId(null);
    };

    const submitForm = (e: FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => closeForm(),
        };
        if (editingId) {
            form.put(route('fjp.update', editingId), options);
        } else {
            form.post(route('fjp.store'), options);
        }
    };

    const tabs = [
        { key: '', label: 'Semua', count: totalCount },
        ...permitStatuses.map((s) => ({
            key: s.value,
            label: s.label,
            count: statusCounts[s.value] ?? 0,
        })),
    ];

    return (
        <AppLayout title="FJP">
            <Head title="FJP" />
            <PageHeader
                title="Formulir Jadwal Pelaksanaan"
                subtitle="Daftar FJP dan status perizinan"
            >
                {canCreate && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> FJP Baru
                    </Button>
                )}
            </PageHeader>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        placeholder="Cari nama event, nomor FJP, atau sekolah…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button
                        type="submit"
                        variant="default"
                        className="h-11 rounded-xl px-6"
                    >
                        Cari
                    </Button>
                </form>
            </FilterCard>

            <div className="my-4">
                <Tabs
                    items={tabs}
                    active={status}
                    onChange={(key) => {
                        setStatus(key);
                        applyFilter(key);
                    }}
                />
            </div>

            <CardTable>
                {fjps.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada FJP"
                        description="Buat FJP baru untuk memulai."
                    />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Sekolah</TH>
                                    <TH className="text-right">
                                        Siswa (Sekolah / Dapodik)
                                    </TH>
                                    <TH>Status</TH>
                                    <TH>Keterangan</TH>
                                    <TH>Alamat</TH>
                                    <TH>Link Lokasi</TH>
                                    <TH>Lampiran</TH>
                                    <TH className="text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {fjps.data.map((f) => (
                                    <TR key={f.id}>
                                        <TD>
                                            <Link
                                                href={route('fjp.show', f.id)}
                                                className="font-medium text-slate-800 hover:text-indigo-600"
                                            >
                                                {f.school_name || f.event_name}
                                            </Link>
                                            <p className="font-mono text-xs text-slate-400">
                                                NPSN {f.npsn || '-'} · {f.fjp_number}
                                            </p>
                                            {f.brand && (
                                                <p className="text-xs text-slate-400">
                                                    {f.brand}
                                                </p>
                                            )}
                                        </TD>
                                        <TD className="text-right">
                                            <div className="space-y-0.5 font-mono text-sm">
                                                <p>
                                                    <span className="mr-1 text-[11px] text-slate-400">
                                                        Sekolah
                                                    </span>
                                                    {formatNumber(f.sekolah)}
                                                </p>
                                                <p>
                                                    <span className="mr-1 text-[11px] text-slate-400">
                                                        Dapodik
                                                    </span>
                                                    {formatNumber(f.dapodik)}
                                                </p>
                                                {f.difference !== null &&
                                                    f.difference !== 0 && (
                                                        <p
                                                            className={cn(
                                                                'text-[11px]',
                                                                f.difference > 0
                                                                    ? 'text-amber-600'
                                                                    : 'text-sky-600',
                                                            )}
                                                        >
                                                            selisih{' '}
                                                            {f.difference > 0 ? '+' : ''}
                                                            {formatNumber(f.difference)}
                                                        </p>
                                                    )}
                                            </div>
                                        </TD>
                                        <TD>
                                            {canUpdate ? (
                                                <Select
                                                    className="w-36"
                                                    value={f.status}
                                                    onChange={(e) =>
                                                        changeStatus(f.id, e.target.value)
                                                    }
                                                >
                                                    {statusOptions.map((o) => (
                                                        <option key={o.value} value={o.value}>
                                                            {o.label}
                                                        </option>
                                                    ))}
                                                </Select>
                                            ) : (
                                                <StatusBadge
                                                    status={f.status}
                                                    label={f.status_label}
                                                />
                                            )}
                                        </TD>
                                        <TD>
                                            <InlineText
                                                fjpId={f.id}
                                                field="notes"
                                                value={f.notes}
                                                disabled={!canUpdate}
                                                placeholder="Tambah keterangan…"
                                                className="min-w-56"
                                            />
                                        </TD>
                                        <TD>
                                            <InlineText
                                                fjpId={f.id}
                                                field="address"
                                                value={f.address}
                                                disabled={!canUpdate}
                                                placeholder="Alamat lokasi…"
                                                className="min-w-56"
                                            />
                                        </TD>
                                        <TD>
                                            <div className="flex items-center gap-1">
                                                <InlineText
                                                    fjpId={f.id}
                                                    field="maps_url"
                                                    value={f.maps_url}
                                                    disabled={!canUpdate}
                                                    placeholder="https://maps.app.goo.gl/…"
                                                    className="min-w-56"
                                                />
                                                {f.maps_url && (
                                                    <a
                                                        href={f.maps_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        title="Buka di Google Maps"
                                                        className="text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        <MapPin className="h-4 w-4" />
                                                    </a>
                                                )}
                                            </div>
                                        </TD>
                                        <TD>
                                            {f.attachments_count > 0 ? (
                                                <Link
                                                    href={route('fjp.show', f.id)}
                                                    className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-indigo-50 hover:text-indigo-700"
                                                    title="Buka lampiran surat izin"
                                                >
                                                    <Paperclip className="h-3.5 w-3.5" />
                                                    {f.attachments_count} berkas
                                                </Link>
                                            ) : (
                                                <span className="text-xs text-slate-300">—</span>
                                            )}
                                        </TD>
                                        <TD className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {canUpdate && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Edit"
                                                        onClick={() => openEdit(f)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <ConfirmButton
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Hapus FJP"
                                                        description={`Hapus FJP ${f.school_name || f.event_name}?`}
                                                        confirmLabel="Hapus"
                                                        onConfirm={() =>
                                                            router.delete(
                                                                route('fjp.destroy', f.id),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4 text-red-600" />
                                                    </ConfirmButton>
                                                )}
                                            </div>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={fjps} />
                    </>
                )}
            </CardTable>

            <ModalForm
                open={formOpen}
                onClose={closeForm}
                size="xl"
                title={editingId ? 'Ubah FJP' : 'FJP Baru'}
                description={
                    editingId
                        ? 'Perbarui data sekolah dan jumlah siswa.'
                        : 'Cari sekolah lalu lengkapi data FJP.'
                }
                onSubmit={submitForm}
                submitDisabled={form.processing}
            >
                <FjpFormFields form={form} options={formOptions} />
            </ModalForm>
        </AppLayout>
    );
}
