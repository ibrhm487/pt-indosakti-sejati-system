import { PageHeader } from '@/Components/AppBits';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Field,
    Input,
    Select,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
    Textarea,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDocumentNumberPreview } from '@/lib/document-number';
import { formatDate, formatNumber } from '@/lib/utils';
import PrintVoucher, { useMrvPdfDownload } from '@/Pages/Warehouse/Mrv/PrintVoucher';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ClipboardList, FileDown, Plus, Printer, Save, Trash2 } from 'lucide-react';
import { FormEvent, Fragment, useState } from 'react';

type LotOption = {
    lot_code: string;
    quantity: number;
    expired_date: string | null;
    is_expired: boolean;
};

type Product = {
    id: number;
    name: string;
    sku: string;
    unit: string;
    brand: string | null;
    brand_code: string | null;
    available: number;
    lots: LotOption[];
};

type TeamMember = {
    id: number;
    name: string;
};

type FjpEvent = {
    id: number;
    name: string;
    date: string | null;
    brand: string | null;
    brand_code: string | null;
    venue: string | null;
    city: string | null;
    members: TeamMember[];
};

type MaterialRow = {
    product_id: string;
    sku: string;
    lot_no: string;
    quantity_requested: string;
    cold: string;
    lbs: string;
    packing: string;
    on_the_spot: string;
    d1: string;
    d2: string;
    cups: string;
    spoon: string;
    notes: string;
};

type SupportingRow = {
    product_id: string;
    lot_no: string;
    qty: string;
    remarks: string;
};

type JugRow = {
    jug_number: string;
    temperature: string;
};

type MrvFormData = {
    event_id: string;
    mrv_number: string;
    mrv_number_manual: boolean;
    activity_type: string;
    activity_date: string;
    week_label: string;
    shift: string;
    brand_name: string;
    venue_name: string;
    venue_names: string[];
    team_member_id: string;
    team_member_name: string;
    prepared_by_name: string;
    approved_by_name: string;
    wet_received_by: string;
    dry_received_by: string;
    jug_quantity: string;
    jug_details: JugRow[];
    sampling_target_wet: string;
    sampling_target_dry: string;
    material_category: string;
    material_categories: string[];
    pickup_time: string;
    notes: string;
    items: MaterialRow[];
    supporting_items: SupportingRow[];
};

const MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

function isoWeek(date: Date): number {
    const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const day = target.getUTCDay() || 7;
    target.setUTCDate(target.getUTCDate() + 4 - day);
    const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));

    return Math.ceil(((target.getTime() - yearStart.getTime()) / 86400000 + 1) / 7);
}

function activityPeriod(value?: string | null): { date: string; week: string; label: string } {
    if (!value) {
        const today = new Date();
        const week = isoWeek(today);

        return {
            date: formatDate(today.toISOString()),
            week: `Minggu ${week}`,
            label: `${MONTHS[today.getMonth()]} - Minggu ${week}`,
        };
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return { date: formatDate(value), week: '-', label: '-' };
    }

    return {
        date: formatDate(value),
        week: `Minggu ${isoWeek(date)}`,
        label: `${MONTHS[date.getMonth()]} - Minggu ${isoWeek(date)}`,
    };
}

function blankMaterial(): MaterialRow {
    return {
        product_id: '',
        sku: '',
        lot_no: '',
        quantity_requested: '',
        cold: '',
        lbs: '',
        packing: '',
        on_the_spot: '',
        d1: '',
        d2: '',
        cups: '',
        spoon: '',
        notes: '',
    };
}

function blankSupporting(): SupportingRow {
    return { product_id: '', lot_no: '', qty: '', remarks: '' };
}

function blankJug(number: number): JugRow {
    return { jug_number: String(number), temperature: '' };
}

function usesJugConversion(sku?: string): boolean {
    return sku === 'MILO-ACTIV-GO' || sku === 'MILO-SACHET';
}

function VoucherMeta({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">
                {label}
            </p>
            <p className="mt-1 text-sm font-semibold text-text">{value}</p>
        </div>
    );
}

export default function MrvForm({
    activityDate,
    events,
    initialEventId,
    defaultMrvNumber,
    defaultWeek,
    products,
    materialCategories,
    teamMembers,
}: {
    activityDate: string;
    events: FjpEvent[];
    initialEventId: number | null;
    defaultMrvNumber: string;
    defaultWeek: string;
    products: Product[];
    materialCategories: { value: string; label: string }[];
    teamMembers: TeamMember[];
}) {
    const defaultCategory = materialCategories[0]?.value ?? 'wet_sample';
    const initialEvent = events.find((event) => event.id === initialEventId);
    const { data, setData, post, processing, errors } = useForm<MrvFormData>({
        event_id: initialEvent ? String(initialEvent.id) : '',
        mrv_number: defaultMrvNumber,
        mrv_number_manual: false,
        activity_type: 'Sampling',
        activity_date: initialEvent?.date ?? activityDate,
        week_label: initialEvent?.date
            ? activityPeriod(initialEvent.date).week
            : defaultWeek,
        shift: 'Pagi',
        brand_name: initialEvent?.brand ?? '',
        venue_name: initialEvent?.venue ?? '',
        venue_names: initialEvent?.venue ? [initialEvent.venue] : ['', ''],
        team_member_id:
            initialEvent?.members.length === 1
                ? String(initialEvent.members[0].id)
                : '',
        team_member_name: '',
        prepared_by_name: '',
        approved_by_name: '',
        wet_received_by: '',
        dry_received_by: '',
        jug_quantity: '1',
        jug_details: [blankJug(1)],
        sampling_target_wet: defaultCategory === 'wet_sample' ? '250' : '0',
        sampling_target_dry: defaultCategory === 'dry_sample' ? '250' : '0',
        material_category: defaultCategory,
        material_categories: [defaultCategory],
        pickup_time: '',
        notes: '',
        items: [blankMaterial()],
        supporting_items: Array.from({ length: 10 }, blankSupporting),
    });
    const { downloadPdf, downloadingPdf } = useMrvPdfDownload(data.mrv_number);
    const [weekManuallyEdited, setWeekManuallyEdited] = useState(false);
    const user = usePage().props.auth.user;
    const requesterName = user?.name ?? '-';
    const requesterRole = user?.active_role?.name ?? user?.roles?.[0]?.name ?? 'Pemohon';
    const period = activityPeriod(data.activity_date);
    const rowErrors = errors as Record<string, string>;
    const estimatedActivGoSachets = (Number(data.jug_quantity) || 0) * 250;
    const selectedEvent = events.find((event) => String(event.id) === data.event_id);
    const availableTeamMembers = selectedEvent?.members ?? teamMembers;

    const selectEvent = (eventId: string) => {
        const event = events.find((candidate) => String(candidate.id) === eventId);
        const nextDate = event?.date ?? data.activity_date;

        setData({
            ...data,
            event_id: eventId,
            mrv_number: data.mrv_number_manual
                ? data.mrv_number
                : formatDocumentNumberPreview(
                    data.mrv_number,
                    nextDate,
                    event?.brand_code
                        ?? products.find((product) => String(product.id) === data.items[0]?.product_id)?.brand_code
                        ?? null,
                ),
            activity_type: event ? 'Sampling' : data.activity_type,
            activity_date: nextDate,
            week_label: event?.date && !weekManuallyEdited
                ? activityPeriod(event.date).week
                : data.week_label,
            brand_name: event?.brand ?? (eventId ? data.brand_name : ''),
            venue_name: event?.venue ?? (eventId ? data.venue_name : ''),
            venue_names: event?.venue ? [event.venue] : eventId ? data.venue_names : ['', ''],
            team_member_id:
                event?.members.length === 1 ? String(event.members[0].id) : '',
            team_member_name: '',
        });
    };

    const updateMaterial = (index: number, patch: Partial<MaterialRow>) =>
        setData(
            'items',
            data.items.map((item, rowIndex) =>
                rowIndex === index ? { ...item, ...patch } : item,
            ),
        );

    const updateJugRows = (jugDetails: JugRow[]) => {
        setData('jug_details', jugDetails);
        setData('jug_quantity', String(jugDetails.length));

        const sachetQuantity = jugDetails.length * 250;
        setData(
            'items',
            data.items.map((item) => {
                return usesJugConversion(item.sku)
                    ? {
                          ...item,
                          quantity_requested: sachetQuantity
                              ? String(sachetQuantity)
                              : '',
                          cups: sachetQuantity ? String(sachetQuantity) : '',
                      }
                    : item;
            }),
        );
    };

    const selectProduct = (index: number, productId: string) => {
        const previousProduct = products.find(
            (product) => String(product.id) === data.items[index].product_id,
        );
        const selectedProduct = products.find(
            (product) => String(product.id) === productId,
        );
        const defaultLot = selectedProduct?.lots[0]?.lot_code ?? '';

        if (index === 0 && !data.mrv_number_manual) {
            setData(
                'mrv_number',
                formatDocumentNumberPreview(
                    data.mrv_number,
                    data.activity_date,
                    selectedProduct?.brand_code ?? null,
                ),
            );
        }

        if (usesJugConversion(selectedProduct?.sku)) {
            const sachetQuantity = estimatedActivGoSachets;
            updateMaterial(index, {
                product_id: productId,
                sku: selectedProduct?.sku ?? '',
                lot_no: defaultLot,
                quantity_requested: sachetQuantity ? String(sachetQuantity) : '',
                cups: sachetQuantity ? String(sachetQuantity) : '',
            });

            return;
        }

        updateMaterial(index, {
            product_id: productId,
            sku: selectedProduct?.sku ?? '',
            lot_no: defaultLot,
            ...(usesJugConversion(previousProduct?.sku)
                ? { quantity_requested: '', cups: '' }
                : {}),
        });
    };

    const updateSku = (index: number, sku: string) => {
        const matchingProduct = products.find((product) => product.sku === sku.trim());
        const previousSku = data.items[index].sku;
        const sachetQuantity = estimatedActivGoSachets;

        updateMaterial(index, {
            sku,
            ...(matchingProduct ? { product_id: String(matchingProduct.id) } : {}),
            ...(matchingProduct?.lots[0]
                ? { lot_no: matchingProduct.lots[0].lot_code }
                : {}),
            ...(usesJugConversion(sku)
                ? {
                      quantity_requested: sachetQuantity ? String(sachetQuantity) : '',
                      cups: sachetQuantity ? String(sachetQuantity) : '',
                  }
                : usesJugConversion(previousSku)
                  ? { quantity_requested: '', cups: '' }
                  : {}),
        });
    };

    const addMaterial = () => setData('items', [...data.items, blankMaterial()]);

    const removeMaterial = (index: number) => {
        if (data.items.length <= 1) {
            return;
        }

        setData(
            'items',
            data.items.filter((_, rowIndex) => rowIndex !== index),
        );
    };

    const updateSupporting = (index: number, patch: Partial<SupportingRow>) =>
        setData(
            'supporting_items',
            data.supporting_items.map((item, rowIndex) =>
                rowIndex === index ? { ...item, ...patch } : item,
            ),
        );

    const addSupporting = () =>
        setData('supporting_items', [...data.supporting_items, blankSupporting()]);

    const removeSupporting = (index: number) => {
        if (data.supporting_items.length <= 1) {
            return;
        }

        setData(
            'supporting_items',
            data.supporting_items.filter((_, rowIndex) => rowIndex !== index),
        );
    };

    const toggleCategory = (category: string) => {
        const categories = data.material_categories.includes(category)
            ? data.material_categories.filter((item) => item !== category)
            : [...data.material_categories, category];

        if (categories.length === 0) {
            return;
        }

        const estimatedTarget = String((Number(data.jug_quantity) || 0) * 250);
        setData({
            ...data,
            material_category: categories[0],
            material_categories: categories,
            sampling_target_wet:
                categories.includes('wet_sample')
                    ? data.sampling_target_wet === '0'
                        ? estimatedTarget
                        : data.sampling_target_wet
                    : '0',
            sampling_target_dry:
                categories.includes('dry_sample')
                    ? data.sampling_target_dry === '0'
                        ? estimatedTarget
                        : data.sampling_target_dry
                    : '0',
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('warehouse.mrv.store'));
    };

    return (
        <AppLayout title="Buat MRV">
            <Head title="Buat MRV" />
            <div className="print:hidden">
                <PageHeader
                    title="Material Requisition Voucher"
                    subtitle="Pengajuan material dan pencatatan anggota tim untuk kegiatan sampling."
                    breadcrumbs={[
                        { label: 'MRV', href: route('warehouse.mrv.index') },
                        { label: 'Buat' },
                    ]}
                />
            </div>

            <form
                onSubmit={submit}
                className="mrv-screen-content space-y-5 print:hidden"
            >
                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-4 border-b border-border bg-surface px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <img
                            src="/images/indosakti-logo.png"
                            alt="PT Indosakti Sejati"
                            className="h-10 w-auto"
                        />
                        <div className="sm:text-right">
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-primary">
                                Material Requisition Voucher
                            </p>
                            <p className="mt-1 text-lg font-bold text-text">
                                PT Indosakti Sejati
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-4 bg-page/50 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Field label="No. MRV" required error={errors.mrv_number} className="mb-0">
                            <Input
                                value={data.mrv_number}
                                onChange={(event) => {
                                    setData({
                                        ...data,
                                        mrv_number: event.target.value,
                                        mrv_number_manual: true,
                                    });
                                }}
                            />
                            {data.mrv_number_manual && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="mt-1"
                                    onClick={() => {
                                        setData({
                                            ...data,
                                            mrv_number_manual: false,
                                            mrv_number: formatDocumentNumberPreview(
                                                data.mrv_number,
                                                data.activity_date,
                                                selectedEvent?.brand_code ?? products.find(
                                                    (product) => String(product.id) === data.items[0]?.product_id,
                                                )?.brand_code ?? null,
                                            ),
                                        });
                                    }}
                                >
                                    Gunakan nomor otomatis
                                </Button>
                            )}
                            <p className="mt-1 text-xs text-muted">
                                Format: JKT/DDMM/KODEBRAND/NN. Nomor otomatis diverifikasi saat disimpan.
                            </p>
                        </Field>
                        <VoucherMeta label="Activity Date" value={period.date} />
                        <Field label="Week" required error={errors.week_label} className="mb-0">
                            <Input
                                value={data.week_label}
                                onChange={(event) => {
                                    setWeekManuallyEdited(true);
                                    setData('week_label', event.target.value);
                                }}
                                placeholder="Contoh: Minggu 45"
                            />
                        </Field>
                        <VoucherMeta label="Status" value="Draft" />
                    </div>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ClipboardList className="h-4 w-4 text-primary" />
                            Deskripsi Kegiatan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <Field label="FJP / Sekolah" error={errors.event_id}>
                            <Select
                                value={data.event_id}
                                onChange={(event) => selectEvent(event.target.value)}
                            >
                                <option value="">Pilih sekolah dari FJP (opsional)</option>
                                {events.map((event) => (
                                    <option key={event.id} value={event.id}>
                                        {event.name} · {formatDate(event.date)}
                                    </option>
                                ))}
                            </Select>
                            <p className="mt-1 text-xs text-muted">
                                Memilih FJP akan mengisi tanggal, brand, sekolah, dan roster tim.
                            </p>
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Field
                                label="Activity Type"
                                required
                                error={errors.activity_type}
                                className="mb-0"
                            >
                                <Input
                                    value={data.activity_type}
                                    onChange={(event) =>
                                        setData('activity_type', event.target.value)
                                    }
                                    readOnly={Boolean(selectedEvent)}
                                    placeholder="Contoh: Sampling"
                                    list="mrv-activity-types"
                                />
                                <datalist id="mrv-activity-types">
                                    <option value="Sampling" />
                                </datalist>
                            </Field>
                            <Field
                                label="Activity Date"
                                required
                                error={errors.activity_date}
                                className="mb-0"
                            >
                                <Input
                                    type="date"
                                    value={data.activity_date}
                                    onChange={(event) => {
                                        const activityDate = event.target.value;
                                        setData('activity_date', activityDate);
                                        if (!data.mrv_number_manual) {
                                            setData(
                                                'mrv_number',
                                                formatDocumentNumberPreview(
                                                    data.mrv_number,
                                                    activityDate,
                                                    selectedEvent?.brand_code ?? products.find(
                                                        (product) => String(product.id) === data.items[0]?.product_id,
                                                    )?.brand_code ?? null,
                                                ),
                                            );
                                        }

                                        if (!weekManuallyEdited) {
                                            setData(
                                                'week_label',
                                                activityPeriod(activityDate).week,
                                            );
                                        }
                                    }}
                                    readOnly={Boolean(selectedEvent)}
                                />
                            </Field>
                            <Field label="Jumlah JUG" className="mb-0">
                                <Input value={data.jug_quantity} readOnly />
                            </Field>
                        </div>

                        <div className="grid gap-4 border-y border-border py-5 sm:grid-cols-2 lg:grid-cols-4">
                            <Field
                                label="Brand"
                                error={errors.brand_name}
                                className="mb-0"
                            >
                                <Input
                                    value={data.brand_name}
                                    onChange={(event) =>
                                        setData('brand_name', event.target.value)
                                    }
                                    readOnly={Boolean(selectedEvent)}
                                    placeholder="Contoh: MILO"
                                />
                            </Field>
                            <Field label="Shift" error={errors.shift} className="mb-0">
                                <Select
                                    value={data.shift}
                                    onChange={(event) => setData('shift', event.target.value)}
                                >
                                    <option value="Pagi">Pagi</option>
                                    <option value="Siang">Siang</option>
                                    <option value="Sore">Sore</option>
                                </Select>
                            </Field>
                            <Field
                                label="Team member"
                                error={errors.team_member_id}
                                className="mb-0"
                            >
                                <Select
                                    value={data.team_member_id}
                                        onChange={(event) =>
                                            setData({
                                                ...data,
                                                team_member_id: event.target.value,
                                                team_member_name: '',
                                            })
                                        }
                                >
                                    <option value="">Pilih anggota tim</option>
                                    {availableTeamMembers.map((member) => (
                                        <option key={member.id} value={member.id}>
                                            {member.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            {!selectedEvent && (
                                <Field
                                    label="Nama manual (opsional)"
                                    error={errors.team_member_name}
                                    className="mb-0"
                                >
                                    <Input
                                        value={data.team_member_name}
                                        onChange={(event) =>
                                            setData({
                                                ...data,
                                                team_member_name: event.target.value,
                                                team_member_id: event.target.value
                                                    ? ''
                                                    : data.team_member_id,
                                            })
                                        }
                                        placeholder="Isi jika anggota tidak ada di daftar"
                                    />
                                </Field>
                            )}
                        </div>

                        <div>
                            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm font-medium text-text">
                                    Daftar Venue
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setData('venue_names', [
                                            ...data.venue_names,
                                            '',
                                        ])
                                    }
                                    disabled={Boolean(selectedEvent) || data.venue_names.length >= 5}
                                >
                                    <Plus className="h-4 w-4" /> Tambah Venue
                                </Button>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {data.venue_names.map((venue, index) => (
                                    <div key={index} className="flex items-center gap-2">
                                        <Input
                                            value={venue}
                                            readOnly={Boolean(selectedEvent)}
                                            onChange={(event) =>
                                                setData(
                                                    'venue_names',
                                                    data.venue_names.map(
                                                        (name, venueIndex) =>
                                                            venueIndex === index
                                                                ? event.target.value
                                                                : name,
                                                    ),
                                                )
                                            }
                                            placeholder={`Venue ${index + 1}`}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            aria-label={`Hapus venue ${index + 1}`}
                                            onClick={() =>
                                                setData(
                                                    'venue_names',
                                                    data.venue_names.filter(
                                                        (_, venueIndex) =>
                                                            venueIndex !== index,
                                                    ),
                                                )
                                            }
                                            disabled={data.venue_names.length <= 1}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                            {errors.venue_names && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.venue_names}
                                </p>
                            )}
                        </div>

                        <div>
                            <p className="mb-2 text-sm font-medium text-text">
                                Material category
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-lg border border-border p-3">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                                        As a component
                                    </p>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {materialCategories
                                            .filter((category) =>
                                                ['wet_sample', 'dry_sample'].includes(
                                                    category.value,
                                                ),
                                            )
                                            .map((category) => (
                                                <label
                                                    key={category.value}
                                                    className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2.5 text-sm transition-colors ${
                                                        data.material_categories.includes(
                                                            category.value,
                                                        )
                                                            ? 'border-primary bg-brand-soft text-primary'
                                                            : 'border-border text-text hover:bg-page'
                                                    }`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="material_categories"
                                                        value={category.value}
                                                        checked={
                                                            data.material_categories.includes(
                                                                category.value,
                                                            )
                                                        }
                                                        onChange={() =>
                                                            toggleCategory(
                                                                category.value,
                                                            )
                                                        }
                                                        className="h-4 w-4 accent-primary"
                                                    />
                                                    {category.label.replace(
                                                        'Others',
                                                        'Other',
                                                    )}
                                                </label>
                                            ))}
                                    </div>
                                </div>
                                <div className="rounded-lg border border-border p-3">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                                        As a ready to use
                                    </p>
                                    {materialCategories
                                        .filter(
                                            (category) =>
                                                category.value === 'others',
                                        )
                                        .map((category) => (
                                            <label
                                                key={category.value}
                                                className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2.5 text-sm transition-colors ${
                                                    data.material_categories.includes(
                                                        category.value,
                                                    )
                                                        ? 'border-primary bg-brand-soft text-primary'
                                                        : 'border-border text-text hover:bg-page'
                                                }`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="material_categories"
                                                    value={category.value}
                                                    checked={
                                                        data.material_categories.includes(
                                                            category.value,
                                                        )
                                                    }
                                                    onChange={() =>
                                                        toggleCategory(
                                                            category.value,
                                                        )
                                                    }
                                                    className="h-4 w-4 accent-primary"
                                                />
                                                Other
                                            </label>
                                        ))}
                                </div>
                            </div>
                            {errors.material_categories && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.material_categories}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Material Type</CardTitle>
                            <p className="mt-1 text-xs text-muted">
                                Pilih barang dari master; SKU dapat diedit, tetapi harus cocok
                                dengan SKU barang aktif agar stok yang benar digunakan.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="print:hidden"
                            onClick={addMaterial}
                        >
                            <Plus className="h-4 w-4" /> Tambah Material
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[900px]">
                                <THead>
                                    <TR>
                                        <TH className="w-10">No.</TH>
                                        <TH className="w-32">SKU</TH>
                                        <TH className="w-36">Lot Code</TH>
                                        <TH className="min-w-64">Item Name</TH>
                                        <TH className="w-28 text-right">Qty</TH>
                                        <TH className="w-24">UOM</TH>
                                        <TH className="min-w-56">Remark</TH>
                                        <TH className="w-12"></TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {data.items.map((item, index) => {
                                        const selectedProduct = products.find(
                                            (product) =>
                                                String(product.id) === item.product_id,
                                        );
                                        const prefix = `items.${index}`;

                                        return (
                                            <Fragment key={index}>
                                                <TR>
                                                    <TD className="text-muted">
                                                        {index + 1}
                                                    </TD>
                                                    <TD>
                                                        <Input
                                                            className="h-9 min-w-32 font-mono text-xs"
                                                            value={item.sku}
                                                            onChange={(event) =>
                                                                updateSku(
                                                                    index,
                                                                    event.target.value,
                                                                )
                                                            }
                                                            placeholder="SKU barang"
                                                        />
                                                        {rowErrors[`${prefix}.sku`] && (
                                                            <p className="mt-1 text-xs text-danger">
                                                                {rowErrors[`${prefix}.sku`]}
                                                            </p>
                                                        )}
                                                    </TD>
                                                    <TD>
                                                        <Select
                                                            className="h-9 min-w-28"
                                                            value={item.lot_no}
                                                            onChange={(event) =>
                                                                updateMaterial(index, {
                                                                    lot_no: event.target.value,
                                                                })
                                                            }
                                                        >
                                                            <option value="">Pilih lot</option>
                                                            {selectedProduct?.lots.map((lot) => (
                                                                <option
                                                                    key={lot.lot_code}
                                                                    value={lot.lot_code}
                                                                >
                                                                    {lot.lot_code} · sisa {lot.quantity}
                                                                    {lot.is_expired ? ' · kedaluwarsa' : ''}
                                                                </option>
                                                            ))}
                                                            {item.lot_no &&
                                                                !selectedProduct?.lots.some(
                                                                    (lot) =>
                                                                        lot.lot_code ===
                                                                        item.lot_no,
                                                                ) && (
                                                                    <option value={item.lot_no}>
                                                                        {item.lot_no}
                                                                    </option>
                                                                )}
                                                        </Select>
                                                    </TD>
                                                    <TD>
                                                        <Select
                                                            className="h-9 min-w-56"
                                                            value={item.product_id}
                                                            onChange={(event) =>
                                                                selectProduct(
                                                                    index,
                                                                    event.target.value,
                                                                )
                                                            }
                                                        >
                                                            <option value="">
                                                                Pilih barang
                                                            </option>
                                                            {products.map((product) => (
                                                                <option
                                                                    key={product.id}
                                                                    value={product.id}
                                                                >
                                                                    {product.name}
                                                                </option>
                                                            ))}
                                                        </Select>
                                                        {rowErrors[
                                                            `${prefix}.product_id`
                                                        ] && (
                                                            <p className="mt-1 text-xs text-danger">
                                                                {
                                                                    rowErrors[
                                                                        `${prefix}.product_id`
                                                                    ]
                                                                }
                                                            </p>
                                                        )}
                                                    </TD>
                                                    <TD>
                                                        <Input
                                                            type="number"
                                                            min={1}
                                                            className="h-9 min-w-20 text-right"
                                                            value={
                                                                item.quantity_requested
                                                            }
                                                            disabled={usesJugConversion(item.sku)}
                                                            onChange={(event) =>
                                                                updateMaterial(index, {
                                                                    quantity_requested:
                                                                        event.target
                                                                            .value,
                                                                })
                                                            }
                                                        />
                                                        {usesJugConversion(item.sku) && (
                                                            <p className="mt-1 text-[10px] text-muted">
                                                                {data.jug_quantity} JUG
                                                                × 250
                                                            </p>
                                                        )}
                                                        {rowErrors[
                                                            `${prefix}.quantity_requested`
                                                        ] && (
                                                            <p className="mt-1 text-xs text-danger">
                                                                {
                                                                    rowErrors[
                                                                        `${prefix}.quantity_requested`
                                                                    ]
                                                                }
                                                            </p>
                                                        )}
                                                    </TD>
                                                    <TD className="text-sm text-muted">
                                                        {selectedProduct?.unit ?? '-'}
                                                    </TD>
                                                    <TD>
                                                        <Input
                                                            className="h-9 min-w-52"
                                                            value={item.notes}
                                                            onChange={(event) =>
                                                                updateMaterial(index, {
                                                                    notes: event.target
                                                                        .value,
                                                                })
                                                            }
                                                            placeholder="Keterangan"
                                                        />
                                                    </TD>
                                                    <TD className="text-right">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="print:hidden"
                                                            onClick={() =>
                                                                removeMaterial(index)
                                                            }
                                                            disabled={
                                                                data.items.length <= 1
                                                            }
                                                            title="Hapus baris material"
                                                            aria-label={`Hapus material ${index + 1}`}
                                                        >
                                                            <Trash2 className="h-4 w-4 text-danger" />
                                                        </Button>
                                                    </TD>
                                                </TR>
                                                <TR>
                                                    <TD
                                                        colSpan={8}
                                                        className="bg-page/30 px-4 py-2"
                                                    >
                                                        <details>
                                                            <summary className="cursor-pointer text-xs font-medium text-muted hover:text-text">
                                                                Detail operasional
                                                                tambahan
                                                            </summary>
                                                            <div className="grid gap-3 py-3 sm:grid-cols-2 lg:grid-cols-4">
                                                                {(
                                                                    [
                                                                        [
                                                                            'cold',
                                                                            'Cold',
                                                                        ],
                                                                        ['lbs', 'Lbs'],
                                                                        [
                                                                            'packing',
                                                                            'Packing',
                                                                        ],
                                                                        [
                                                                            'on_the_spot',
                                                                            'On the spot',
                                                                        ],
                                                                        ['d1', 'D1'],
                                                                        ['d2', 'D2'],
                                                                        ['cups', 'Cups'],
                                                                        ['spoon', 'Spoon'],
                                                                    ] as const
                                                                ).map(
                                                                    ([field, label]) => (
                                                                        <Field
                                                                            key={field}
                                                                            label={label}
                                                                            className="mb-0"
                                                                        >
                                                                            <Input
                                                                                type={
                                                                                    field ===
                                                                                    'packing'
                                                                                        ? 'text'
                                                                                        : 'number'
                                                                                }
                                                                                min={
                                                                                    field ===
                                                                                    'packing'
                                                                                        ? undefined
                                                                                        : 0
                                                                                }
                                                                                step={
                                                                                    field ===
                                                                                    'packing'
                                                                                        ? undefined
                                                                                        : '0.01'
                                                                                }
                                                                                value={
                                                                                    item[
                                                                                        field
                                                                                    ]
                                                                                }
                                                                                onChange={(
                                                                                    event,
                                                                                ) =>
                                                                                    updateMaterial(
                                                                                        index,
                                                                                        {
                                                                                            [field]:
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            />
                                                                        </Field>
                                                                    ),
                                                                )}
                                                            </div>
                                                        </details>
                                                    </TD>
                                                </TR>
                                            </Fragment>
                                        );
                                    })}
                                </TBody>
                            </Table>
                        </div>
                        {errors.items && (
                            <p className="px-4 py-3 text-xs text-danger">
                                {errors.items}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Supporting Items</CardTitle>
                            <p className="mt-1 text-xs text-muted">
                                Barang pendukung kegiatan.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="print:hidden"
                            onClick={addSupporting}
                        >
                            <Plus className="h-4 w-4" /> Tambah Supporting
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[900px]">
                                <THead>
                                    <TR>
                                        <TH className="w-10">No.</TH>
                                        <TH>Supporting</TH>
                                        <TH className="w-36">Lot Code</TH>
                                        <TH className="w-28 text-right">Qty</TH>
                                        <TH className="w-28">UOM</TH>
                                        <TH>Remark</TH>
                                        <TH className="w-12"></TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {data.supporting_items.map((item, index) => (
                                        <TR key={index}>
                                            <TD className="text-muted">
                                                {index + 1}
                                            </TD>
                                            <TD>
                                                <Select
                                                    className="h-9 min-w-56"
                                                    value={item.product_id}
                                                    onChange={(event) => {
                                                        const product = products.find(
                                                            (candidate) => String(candidate.id) === event.target.value,
                                                        );
                                                        updateSupporting(index, {
                                                            product_id: event.target.value,
                                                            lot_no: product?.lots[0]?.lot_code ?? '',
                                                            qty: '',
                                                        });
                                                    }}
                                                >
                                                    <option value="">Pilih produk gudang</option>
                                                    {products.map((product) => (
                                                        <option key={product.id} value={product.id}>
                                                            {product.name} ({product.sku})
                                                        </option>
                                                    ))}
                                                </Select>
                                                {rowErrors[`supporting_items.${index}.product_id`] && (
                                                    <p className="mt-1 text-xs text-danger">
                                                        {rowErrors[`supporting_items.${index}.product_id`]}
                                                    </p>
                                                )}
                                            </TD>
                                            <TD>
                                                <Select
                                                    className="h-9 min-w-32"
                                                    value={item.lot_no}
                                                    disabled={!item.product_id}
                                                    onChange={(event) =>
                                                        updateSupporting(index, { lot_no: event.target.value })
                                                    }
                                                >
                                                    <option value="">Tanpa lot</option>
                                                    {products.find(
                                                        (product) => String(product.id) === item.product_id,
                                                    )?.lots.map((lot) => (
                                                        <option key={lot.lot_code} value={lot.lot_code}>
                                                            {lot.lot_code} · sisa {lot.quantity}
                                                        </option>
                                                    ))}
                                                </Select>
                                            </TD>
                                            <TD>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    step={1}
                                                    className="h-9 min-w-20 text-right"
                                                    value={item.qty}
                                                    disabled={!item.product_id}
                                                    onChange={(event) =>
                                                        updateSupporting(index, {
                                                            qty: event.target.value,
                                                        })
                                                    }
                                                />
                                                {rowErrors[`supporting_items.${index}.qty`] && (
                                                <p className="mt-1 text-xs text-danger">
                                                    {rowErrors[`supporting_items.${index}.qty`]}
                                                </p>
                                                )}
                                            </TD>
                                            <TD className="text-sm text-muted">
                                                {products.find(
                                                (product) => String(product.id) === item.product_id,
                                                )?.unit ?? '-'}
                                            </TD>
                                            <TD>
                                                <Input
                                                    className="h-9 min-w-44"
                                                    value={item.remarks}
                                                    onChange={(event) =>
                                                        updateSupporting(index, {
                                                            remarks:
                                                                event.target.value,
                                                        })
                                                    }
                                                    placeholder="Keterangan"
                                                />
                                            </TD>
                                            <TD className="text-right">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="print:hidden"
                                                    onClick={() =>
                                                        removeSupporting(index)
                                                    }
                                                    disabled={
                                                        data.supporting_items.length <=
                                                        1
                                                    }
                                                    title="Hapus baris supporting item"
                                                    aria-label={`Hapus supporting item ${index + 1}`}
                                                >
                                                    <Trash2 className="h-4 w-4 text-danger" />
                                                </Button>
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="grid gap-6 p-4 sm:p-5 lg:grid-cols-[1.2fr_0.8fr]">
                        <div>
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <CardTitle>JUG & Temperature (°C)</CardTitle>
                                    <p className="mt-1 text-xs text-muted">
                                        Jumlah JUG mengikuti banyaknya baris.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="print:hidden"
                                    onClick={() =>
                                        updateJugRows([
                                            ...data.jug_details,
                                            blankJug(data.jug_details.length + 1),
                                        ])
                                    }
                                >
                                    <Plus className="h-4 w-4" /> Tambah JUG
                                </Button>
                            </div>
                            <div className="overflow-x-auto rounded-lg border border-border">
                                <Table className="min-w-[420px]">
                                    <THead>
                                        <TR>
                                            <TH className="w-12">No.</TH>
                                            <TH>Jug Number</TH>
                                            <TH>Temperature (°C)</TH>
                                            <TH className="w-12"></TH>
                                        </TR>
                                    </THead>
                                    <TBody>
                                        {data.jug_details.map((jug, index) => (
                                            <TR key={index}>
                                                <TD className="text-muted">
                                                    {index + 1}
                                                </TD>
                                                <TD>
                                                    <Input
                                                        className="h-9 min-w-28"
                                                        value={jug.jug_number}
                                                        onChange={(event) =>
                                                            updateJugRows(
                                                                data.jug_details.map(
                                                                    (row, rowIndex) =>
                                                                        rowIndex ===
                                                                        index
                                                                            ? {
                                                                                  ...row,
                                                                                  jug_number:
                                                                                      event
                                                                                          .target
                                                                                          .value,
                                                                              }
                                                                            : row,
                                                                ),
                                                            )
                                                        }
                                                        placeholder="Nomor JUG"
                                                    />
                                                    {rowErrors[
                                                        `jug_details.${index}.jug_number`
                                                    ] && (
                                                        <p className="mt-1 text-xs text-danger">
                                                            {
                                                                rowErrors[
                                                                    `jug_details.${index}.jug_number`
                                                                ]
                                                            }
                                                        </p>
                                                    )}
                                                </TD>
                                                <TD>
                                                    <Input
                                                        type="number"
                                                        min={-50}
                                                        max={100}
                                                        step="0.1"
                                                        className="h-9 min-w-32"
                                                        value={jug.temperature}
                                                        onChange={(event) =>
                                                            updateJugRows(
                                                                data.jug_details.map(
                                                                    (row, rowIndex) =>
                                                                        rowIndex ===
                                                                        index
                                                                            ? {
                                                                                  ...row,
                                                                                  temperature:
                                                                                      event
                                                                                          .target
                                                                                          .value,
                                                                              }
                                                                            : row,
                                                                ),
                                                            )
                                                        }
                                                        placeholder="°C"
                                                    />
                                                    {rowErrors[
                                                        `jug_details.${index}.temperature`
                                                    ] && (
                                                        <p className="mt-1 text-xs text-danger">
                                                            {
                                                                rowErrors[
                                                                    `jug_details.${index}.temperature`
                                                                ]
                                                            }
                                                        </p>
                                                    )}
                                                </TD>
                                                <TD className="text-right">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="print:hidden"
                                                        onClick={() =>
                                                            updateJugRows(
                                                                data.jug_details.filter(
                                                                    (_, rowIndex) =>
                                                                        rowIndex !==
                                                                        index,
                                                                ),
                                                            )
                                                        }
                                                        disabled={
                                                            data.jug_details.length <= 1
                                                        }
                                                        title="Hapus JUG"
                                                        aria-label={`Hapus JUG ${index + 1}`}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-danger" />
                                                    </Button>
                                                </TD>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            </div>
                            {errors.jug_details && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.jug_details}
                                </p>
                            )}
                        </div>

                        <div>
                            <CardTitle className="mb-3">Sampling Target</CardTitle>
                            <div className="overflow-hidden rounded-lg border border-border">
                                <div className="grid grid-cols-[1fr_1fr_1fr] border-b border-border bg-page/60 text-xs font-semibold text-muted">
                                    <div className="px-3 py-2"></div>
                                    <div className="px-3 py-2 text-center">Wet</div>
                                    <div className="px-3 py-2 text-center">Dry</div>
                                </div>
                                <div className="grid grid-cols-[1fr_1fr_1fr] items-center border-b border-border">
                                    <p className="px-3 py-2 text-sm font-medium">
                                        Target
                                    </p>
                                    <Input
                                        type="number"
                                        min={0}
                                        className="h-9 rounded-none border-x-0 border-y-0 text-right"
                                        value={data.sampling_target_wet}
                                        disabled={
                                            !data.material_categories.includes(
                                                'wet_sample',
                                            )
                                        }
                                        onChange={(event) =>
                                            setData(
                                                'sampling_target_wet',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Input
                                        type="number"
                                        min={0}
                                        className="h-9 rounded-none border-x-0 border-y-0 text-right"
                                        value={data.sampling_target_dry}
                                        disabled={
                                            !data.material_categories.includes(
                                                'dry_sample',
                                            )
                                        }
                                        onChange={(event) =>
                                            setData(
                                                'sampling_target_dry',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid grid-cols-[1fr_2fr] items-center border-b border-border">
                                    <p className="px-3 py-2 text-sm font-medium">
                                        UOM
                                    </p>
                                    <p className="px-3 py-2 text-right text-sm text-muted">
                                        Cups
                                    </p>
                                </div>
                                <div className="grid grid-cols-[1fr_2fr] items-center">
                                    <p className="px-3 py-2 text-sm font-medium">
                                        Pickup Time
                                    </p>
                                    <Input
                                        type="time"
                                        className="h-9 rounded-none border-0 text-right"
                                        value={data.pickup_time}
                                        onChange={(event) =>
                                            setData(
                                                'pickup_time',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            {errors.sampling_target_wet && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.sampling_target_wet}
                                </p>
                            )}
                            {errors.sampling_target_dry && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.sampling_target_dry}
                                </p>
                            )}
                            {errors.pickup_time && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.pickup_time}
                                </p>
                            )}
                            <p className="mt-2 text-xs leading-5 text-muted">
                                Estimasi sachet MILO: {formatNumber(estimatedActivGoSachets)} pcs
                                ({data.jug_quantity} JUG × 250). Target dapat disesuaikan.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Execution & Tanda Tangan</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Field label="Approved By — PIC" error={errors.approved_by_name} className="mb-0">
                            <Input
                                value={data.approved_by_name}
                                onChange={(event) =>
                                    setData('approved_by_name', event.target.value)
                                }
                                placeholder="Nama PIC"
                            />
                        </Field>
                        <Field label="Prepared By — Warehouse" error={errors.prepared_by_name} className="mb-0">
                            <Input
                                value={data.prepared_by_name}
                                onChange={(event) =>
                                    setData('prepared_by_name', event.target.value)
                                }
                                placeholder="Nama petugas gudang"
                            />
                        </Field>
                        <Field label="All Material Receiver By — Cooler" error={errors.wet_received_by} className="mb-0">
                            <Input
                                value={data.wet_received_by}
                                onChange={(event) =>
                                    setData('wet_received_by', event.target.value)
                                }
                                placeholder="Nama penerima material"
                            />
                        </Field>
                        <Field label="All Material Received By — Sampling Team" error={errors.dry_received_by} className="mb-0">
                            <Input
                                value={data.dry_received_by}
                                onChange={(event) =>
                                    setData('dry_received_by', event.target.value)
                                }
                                placeholder="Nama penerima material"
                            />
                        </Field>
                    </CardContent>
                </Card>

                <Field
                    label="Catatan"
                    error={errors.notes}
                    className="mb-0"
                >
                    <Textarea
                        rows={2}
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        placeholder="Instruksi pengambilan atau informasi tambahan"
                    />
                </Field>

                <p className="text-xs text-muted">
                    Disiapkan oleh {requesterName} · {requesterRole}
                </p>

                <div className="sticky bottom-4 z-10 flex flex-wrap items-center gap-2 rounded-xl border border-border bg-surface/95 p-3 shadow-sm backdrop-blur print:hidden">
                    <Button type="submit" variant="primary" disabled={processing}>
                        <Save className="h-4 w-4" />
                        {processing ? 'Menyimpan...' : 'Ajukan MRV'}
                    </Button>
                    <Link href={route('warehouse.mrv.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer className="h-4 w-4" />
                            Cetak Form
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            onClick={downloadPdf}
                            disabled={downloadingPdf}
                        >
                            <FileDown className="h-4 w-4" />
                            {downloadingPdf ? 'Menyiapkan PDF...' : 'Unduh PDF'}
                        </Button>
                    </div>
                </div>
            </form>

            <PrintVoucher
                mrvNumber={data.mrv_number}
                activityType={data.activity_type}
                activityDate={data.activity_date ? formatDate(data.activity_date) : null}
                week={data.week_label}
                shift={data.shift || null}
                teamMember={
                    data.team_member_name ||
                    teamMembers.find(
                        (member) => String(member.id) === data.team_member_id,
                    )?.name ||
                    null
                }
                brand={data.brand_name}
                venues={data.venue_names.filter((venue) => venue.trim() !== '')}
                categories={data.material_categories}
                materials={data.items.map((item) => {
                    const product =
                        products.find((option) => option.sku === item.sku) ??
                        products.find(
                            (option) => String(option.id) === item.product_id,
                        );

                    return {
                        sku: item.sku,
                        lotCode: item.lot_no,
                        name: product?.name ?? null,
                        quantity: item.quantity_requested,
                        unit: product?.unit ?? null,
                        remark: item.notes,
                    };
                })}
                supportingItems={data.supporting_items
                    .filter((item) => item.product_id !== '')
                    .map((item) => {
                        const product = products.find(
                            (option) => String(option.id) === item.product_id,
                        );

                        return {
                            name: product?.name ?? '',
                            quantity: item.qty,
                            unit: product?.unit ?? null,
                            remark: item.remarks,
                        };
                    })}
                jugs={data.jug_details.map((jug) => ({
                    number: jug.jug_number,
                    temperature: jug.temperature,
                }))}
                wetTarget={data.sampling_target_wet}
                dryTarget={data.sampling_target_dry}
                pickupTime={data.pickup_time}
                preparedBy={data.prepared_by_name}
                approvedBy={data.approved_by_name}
                wetReceivedBy={data.wet_received_by}
                dryReceivedBy={data.dry_received_by}
            />
        </AppLayout>
    );
}
