import Checkbox from '@/Components/Checkbox';
import SchoolPicker, { DapoSchoolDetail } from '@/Components/SchoolPicker';
import { Button, Card, Field, Input, Select, Textarea } from '@/Components/ui';
import { Head, InertiaFormProps, Link, useForm } from '@inertiajs/react';
import { Building2, CalendarClock, ClipboardCheck, MapPinned, StickyNote } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';

export type FormOption = { id: number; brand_id?: number | null; name: string; slug?: string };

export type FjpFormOptions = {
    brands?: FormOption[];
    eventTypes?: FormOption[];
    cities?: string[];
    provinces?: string[];
};

export type FjpFormData = {
    school_name: string;
    npsn: string;
    address: string;
    city: string;
    province: string;
    kecamatan: string;
    kelurahan: string;
    maps_url: string;
    jumlah_siswa_sekolah: string;
    jumlah_siswa_dapodik: string;
    dapodik_source: string;
    notes: string;
    brand_id: string;
    event_type_id: string;
    event_date: string;
    start_time: string;
    sampling_target: string;
    jug_required: boolean;
    permitter_total_outlet_canteen_sell_drink: string;
    permitter_quantity_with_milo: string;
    permitter_status: string;
    permitter_quantity_without_milo: string;
    team_sampling_quantity_with_milo: string;
    team_sampling_quantity_without_milo: string;
};

export const EMPTY_FJP_FORM: FjpFormData = {
    school_name: '',
    npsn: '',
    address: '',
    city: '',
    province: '',
    kecamatan: '',
    kelurahan: '',
    maps_url: '',
    jumlah_siswa_sekolah: '',
    jumlah_siswa_dapodik: '',
    dapodik_source: '',
    notes: '',
    brand_id: '',
    event_type_id: '',
    event_date: '',
    start_time: '',
    sampling_target: '',
    jug_required: false,
    permitter_total_outlet_canteen_sell_drink: '',
    permitter_quantity_with_milo: '',
    permitter_status: '',
    permitter_quantity_without_milo: '',
    team_sampling_quantity_with_milo: '',
    team_sampling_quantity_without_milo: '',
};

const DAPODIK_SOURCE = 'Dapodik (dapo.kemendikdasmen.go.id)';

function formatSamplingPercentage(quantity: string, total: number): string {
    const parsedQuantity = Number(quantity);

    if (
        quantity === '' ||
        !Number.isFinite(parsedQuantity) ||
        !Number.isFinite(total) ||
        total <= 0
    ) {
        return '—';
    }

    return `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format((parsedQuantity / total) * 100)}%`;
}

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

export function FjpFormFields({
    form,
    options,
}: {
    form: InertiaFormProps<FjpFormData>;
    options?: FjpFormOptions;
}) {
    const { data, setData, errors } = form;
    const permitterTotal = data.permitter_total_outlet_canteen_sell_drink === ''
        ? null
        : Number(data.permitter_total_outlet_canteen_sell_drink);
    const permitterPercentage = permitterTotal === null
        ? '—'
        : formatSamplingPercentage(data.permitter_quantity_with_milo, permitterTotal);
    const teamSamplingComplete =
        data.team_sampling_quantity_with_milo !== '' &&
        data.team_sampling_quantity_without_milo !== '';
    const teamSamplingTotal = teamSamplingComplete
        ? Number(data.team_sampling_quantity_with_milo) +
          Number(data.team_sampling_quantity_without_milo)
        : null;
    const teamSamplingPercentage =
        teamSamplingTotal === null
            ? '—'
            : formatSamplingPercentage(
                  data.team_sampling_quantity_with_milo,
                  teamSamplingTotal,
              );

    const typeOptionsFor = (brandId: string) => {
        if (brandId === '') {
            return (options?.eventTypes ?? []).filter((t) => !t.brand_id);
        }

        const scoped = (options?.eventTypes ?? []).filter(
            (t) => String(t.brand_id) === brandId,
        );

        return scoped.length
            ? scoped
            : (options?.eventTypes ?? []).filter((t) => !t.brand_id);
    };

    const typeOptions = (() => {
        const types = typeOptionsFor(data.brand_id);
        const current = (options?.eventTypes ?? []).find(
            (t) => String(t.id) === data.event_type_id,
        );

        return current && !types.some((t) => t.id === current.id)
            ? [...types, current]
            : types;
    })();

    const changeBrand = (brandId: string) => {
        const types = typeOptionsFor(brandId);
        const typeId = types.some((t) => String(t.id) === data.event_type_id)
            ? data.event_type_id
            : '';

        setData({ ...data, brand_id: brandId, event_type_id: typeId });
    };

    const applyDapodik = (detail: DapoSchoolDetail) => {
        setData({
            ...data,
            school_name: detail.school_name || data.school_name,
            npsn: detail.npsn || data.npsn,
            address: detail.address || data.address,
            city: detail.kabupaten || data.city,
            province: detail.provinsi || data.province,
            kecamatan: detail.kecamatan || data.kecamatan,
            kelurahan: detail.kelurahan || data.kelurahan,
            maps_url: detail.maps_url || data.maps_url,
            jumlah_siswa_dapodik:
                detail.jumlah_siswa != null
                    ? String(detail.jumlah_siswa)
                    : data.jumlah_siswa_dapodik,
            dapodik_source:
                detail.jumlah_siswa != null
                    ? DAPODIK_SOURCE
                    : data.jumlah_siswa_dapodik
                      ? 'Manual'
                      : '',
        });
    };

    return (
        <div className="space-y-6">
            <Section icon={<Building2 className="h-3.5 w-3.5" />} title="Data Sekolah">
                <Field label="Cari Sekolah (Dapodik)" className="mb-4">
                    <SchoolPicker onPick={applyDapodik} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama Sekolah" error={errors.school_name}>
                        <Input
                            value={data.school_name}
                            onChange={(e) => setData('school_name', e.target.value)}
                        />
                    </Field>
                    <Field label="NPSN" error={errors.npsn}>
                        <Input
                            value={data.npsn}
                            onChange={(e) =>
                                setData({
                                    ...data,
                                    npsn: e.target.value,
                                    dapodik_source: data.jumlah_siswa_dapodik
                                        ? 'Manual'
                                        : '',
                                })
                            }
                            placeholder="8 digit"
                        />
                    </Field>
                    <Field label="Jumlah Siswa (versi Sekolah)" error={errors.jumlah_siswa_sekolah}>
                        <Input
                            type="number"
                            min={0}
                            value={data.jumlah_siswa_sekolah}
                            onChange={(e) =>
                                setData({
                                    ...data,
                                    jumlah_siswa_sekolah: e.target.value,
                                    sampling_target: e.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field label="Jumlah Siswa (versi Dapodik)" error={errors.jumlah_siswa_dapodik}>
                        <Input
                            type="number"
                            min={0}
                            value={data.jumlah_siswa_dapodik}
                            onChange={(e) =>
                                setData({
                                    ...data,
                                    jumlah_siswa_dapodik: e.target.value,
                                    dapodik_source: e.target.value ? 'Manual' : '',
                                })
                            }
                        />
                    </Field>
                </div>
                <p className="text-xs text-muted">
                    Pilih sekolah dari Dapodik untuk mengisi NPSN, alamat, lokasi,
                    dan jumlah siswa otomatis. Dua nilai disimpan terpisah;
                    selisih dihitung sebagai Sekolah − Dapodik.
                </p>
                <p className="text-xs text-muted">
                    Sumber jumlah siswa:{' '}
                    {data.jumlah_siswa_dapodik !== ''
                        ? data.dapodik_source || 'belum tersedia'
                        : 'belum tersedia'}
                    .
                </p>
            </Section>

            <Section icon={<MapPinned className="h-3.5 w-3.5" />} title="Lokasi">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Alamat" error={errors.address} className="sm:col-span-2">
                        <Textarea
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            placeholder="QR4H+4FM, RT.7/RW.5, Mampang Prpt., …"
                        />
                    </Field>
                    <Field label="Kota/Kabupaten" error={errors.city}>
                        <Input
                            list="fjp-city-options"
                            value={data.city}
                            onChange={(e) => setData('city', e.target.value)}
                            placeholder="Mis. Kota Jakarta Pusat"
                        />
                    </Field>
                    <Field label="Kecamatan" error={errors.kecamatan}>
                        <Input
                            value={data.kecamatan}
                            onChange={(e) => setData('kecamatan', e.target.value)}
                        />
                    </Field>
                    <Field label="Kelurahan" error={errors.kelurahan}>
                        <Input
                            value={data.kelurahan}
                            onChange={(e) => setData('kelurahan', e.target.value)}
                        />
                    </Field>
                    <Field label="Provinsi" error={errors.province}>
                        <Input
                            list="fjp-province-options"
                            value={data.province}
                            onChange={(e) => setData('province', e.target.value)}
                            placeholder="Mis. DKI Jakarta"
                        />
                    </Field>
                    <Field label="Link Google Maps" error={errors.maps_url} className="sm:col-span-2">
                        <Input
                            value={data.maps_url}
                            onChange={(e) => setData('maps_url', e.target.value)}
                            placeholder="https://maps.app.goo.gl/…"
                        />
                    </Field>
                </div>
                <datalist id="fjp-city-options">
                    {(options?.cities ?? []).map((city) => (
                        <option key={city} value={city} />
                    ))}
                </datalist>
                <datalist id="fjp-province-options">
                    {(options?.provinces ?? []).map((province) => (
                        <option key={province} value={province} />
                    ))}
                </datalist>
                <p className="text-xs text-muted">
                    Kota/Kabupaten dan Provinsi terisi otomatis dari Dapodik saat
                    sekolah dipilih. Saran diambil dari lokasi venue dan FJP yang
                    sudah ada; nilai tetap dapat diubah manual.
                </p>
            </Section>

            <Section icon={<CalendarClock className="h-3.5 w-3.5" />} title="Jadwal & Target">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Brand" error={errors.brand_id}>
                        <Select
                            value={data.brand_id}
                            onChange={(e) => changeBrand(e.target.value)}
                        >
                            <option value="">- Pilih brand -</option>
                            {(options?.brands ?? []).map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Jenis Event" error={errors.event_type_id}>
                        <Select
                            value={data.event_type_id}
                            onChange={(e) => setData('event_type_id', e.target.value)}
                        >
                            <option value="">- Pilih jenis -</option>
                            {typeOptions.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Tanggal Pelaksanaan" error={errors.event_date}>
                        <Input
                            type="date"
                            value={data.event_date}
                            onChange={(e) => setData('event_date', e.target.value)}
                        />
                    </Field>
                    <Field label="Jam Mulai" error={errors.start_time}>
                        <Input
                            type="time"
                            value={data.start_time}
                            onChange={(e) => setData('start_time', e.target.value)}
                        />
                    </Field>
                    <Field label="Target Sampling (cup)" error={errors.sampling_target}>
                        <Input
                            type="number"
                            min={0}
                            value={data.sampling_target}
                            onChange={(e) => setData('sampling_target', e.target.value)}
                        />
                    </Field>
                    <label className="flex items-center gap-2 text-sm text-text">
                        <Checkbox
                            checked={data.jug_required}
                            onChange={(e) => setData('jug_required', e.target.checked)}
                        />
                        Butuh JUG (Just Use Gel)
                    </label>
                </div>
                <p className="text-xs text-muted">
                    Target sampling otomatis mengikuti Jumlah Siswa versi Sekolah. Nilainya
                    tetap dapat disesuaikan dan akan diwariskan ke Quick Report.
                </p>
            </Section>

            <Section icon={<StickyNote className="h-3.5 w-3.5" />} title="Catatan">
                <Field error={errors.notes}>
                    <Textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="Catatan tambahan (opsional)"
                    />
                </Field>
            </Section>

            <Section icon={<ClipboardCheck className="h-3.5 w-3.5" />} title="Validasi Outlet & Sampling">
                <div className="overflow-x-auto rounded-xl border border-border">
                    <table className="min-w-[900px] w-full border-collapse text-xs">
                        <caption className="sr-only">
                            Validasi Permitter sebelum sampling dan validasi tim saat sampling
                        </caption>
                        <thead>
                            <tr className="bg-emerald-800 text-white">
                                <th colSpan={5} className="border border-emerald-900/30 px-3 py-2 text-center font-semibold">
                                    Validasi Permitter (pre-sampling)
                                </th>
                                <th colSpan={3} className="border border-emerald-900/30 px-3 py-2 text-center font-semibold">
                                    Validasi Team Sampling (during sampling)
                                </th>
                            </tr>
                            <tr className="bg-emerald-700 text-white">
                                {[
                                    'Total Outlet Canteen (Sell Drink)',
                                    'Kuantitas with MILO',
                                    'Status',
                                    'Kuantitas without MILO',
                                    '%',
                                    'Kuantitas with MILO',
                                    'Kuantitas without MILO',
                                    '%',
                                ].map((label, index) => (
                                    <th
                                        key={`${label}-${index}`}
                                        className="border border-emerald-900/30 px-3 py-2 text-left font-semibold"
                                    >
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="bg-surface">
                                <td className="border border-border p-2">
                                    <Field error={errors.permitter_total_outlet_canteen_sell_drink}>
                                        <Input
                                            aria-label="Total Outlet Canteen (Sell Drink)"
                                            type="number"
                                            min={0}
                                            value={data.permitter_total_outlet_canteen_sell_drink}
                                            onChange={(e) => setData('permitter_total_outlet_canteen_sell_drink', e.target.value)}
                                        />
                                    </Field>
                                </td>
                                <td className="border border-border p-2">
                                    <Field error={errors.permitter_quantity_with_milo}>
                                        <Input
                                            aria-label="Kuantitas with MILO sebelum sampling"
                                            type="number"
                                            min={0}
                                            value={data.permitter_quantity_with_milo}
                                            onChange={(e) => setData('permitter_quantity_with_milo', e.target.value)}
                                        />
                                    </Field>
                                </td>
                                <td className="border border-border p-2">
                                    <Field error={errors.permitter_status}>
                                        <Select
                                            aria-label="Status validasi Permitter"
                                            value={data.permitter_status}
                                            onChange={(e) => setData('permitter_status', e.target.value)}
                                        >
                                            <option value="">Pilih status</option>
                                            <option value="MILO SACHET">MILO SACHET</option>
                                            <option value="MILO UHT">MILO UHT</option>
                                            <option value="NO MILO">NO MILO</option>
                                        </Select>
                                    </Field>
                                </td>
                                <td className="border border-border p-2">
                                    <Field error={errors.permitter_quantity_without_milo}>
                                        <Input
                                            aria-label="Kuantitas without MILO sebelum sampling"
                                            type="number"
                                            min={0}
                                            value={data.permitter_quantity_without_milo}
                                            onChange={(e) => setData('permitter_quantity_without_milo', e.target.value)}
                                        />
                                    </Field>
                                </td>
                                <td className="border border-border p-2 text-center font-semibold text-text">
                                    <output aria-label="Persentase with MILO sebelum sampling">
                                        {permitterPercentage}
                                    </output>
                                </td>
                                <td className="border border-border p-2">
                                    <Field error={errors.team_sampling_quantity_with_milo}>
                                        <Input
                                            aria-label="Kuantitas with MILO saat sampling"
                                            type="number"
                                            min={0}
                                            value={data.team_sampling_quantity_with_milo}
                                            onChange={(e) => setData('team_sampling_quantity_with_milo', e.target.value)}
                                        />
                                    </Field>
                                </td>
                                <td className="border border-border p-2">
                                    <Field error={errors.team_sampling_quantity_without_milo}>
                                        <Input
                                            aria-label="Kuantitas without MILO saat sampling"
                                            type="number"
                                            min={0}
                                            value={data.team_sampling_quantity_without_milo}
                                            onChange={(e) => setData('team_sampling_quantity_without_milo', e.target.value)}
                                        />
                                    </Field>
                                </td>
                                <td className="border border-border p-2 text-center font-semibold text-text">
                                    <output aria-label="Persentase with MILO saat sampling">
                                        {teamSamplingPercentage}
                                    </output>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p className="text-xs text-muted">
                    Persentase dihitung otomatis: Permitter = kuantitas with MILO ÷ total outlet; tim sampling = kuantitas with MILO ÷ total kuantitas sampling.
                </p>
            </Section>
        </div>
    );
}

export default function FjpForm({
    initial,
    action,
    method,
    title,
    options,
}: {
    initial?: Partial<FjpFormData>;
    action: string;
    method: 'post' | 'put';
    title: string;
    options?: FjpFormOptions;
}) {
    const form = useForm<FjpFormData>({
        ...EMPTY_FJP_FORM,
        ...initial,
        dapodik_source:
            initial?.dapodik_source ??
            (initial?.jumlah_siswa_dapodik ? 'Manual' : ''),
    });

    const { post, put, processing } = form;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (method === 'put') {
            put(action);
        } else {
            post(action);
        }
    };

    return (
        <div className="mx-auto max-w-3xl">
            <Head title={title} />
            <form onSubmit={submit}>
                <Card className="p-5 sm:p-6">
                    <FjpFormFields form={form} options={options} />
                </Card>

                <div className="mt-4 flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('fjp.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </div>
    );
}
