import SchoolPicker, { DapoSchoolDetail } from '@/Components/SchoolPicker';
import { Button, Card, Field, Input, Textarea } from '@/Components/ui';
import { Head, InertiaFormProps, Link, useForm } from '@inertiajs/react';
import { Building2, MapPinned, StickyNote } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';

export type FjpFormData = {
    school_name: string;
    npsn: string;
    address: string;
    maps_url: string;
    jumlah_siswa_sekolah: string;
    jumlah_siswa_dapodik: string;
    dapodik_source: string;
    notes: string;
};

export const EMPTY_FJP_FORM: FjpFormData = {
    school_name: '',
    npsn: '',
    address: '',
    maps_url: '',
    jumlah_siswa_sekolah: '',
    jumlah_siswa_dapodik: '',
    dapodik_source: '',
    notes: '',
};

const DAPODIK_SOURCE = 'Dapodik (dapo.kemendikdasmen.go.id)';

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
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    {icon}
                </span>
                {title}
            </div>
            {children}
        </div>
    );
}

export function FjpFormFields({ form }: { form: InertiaFormProps<FjpFormData> }) {
    const { data, setData, errors } = form;

    const applyDapodik = (detail: DapoSchoolDetail) => {
        setData({
            ...data,
            school_name: detail.school_name || data.school_name,
            npsn: detail.npsn || data.npsn,
            address: detail.address || data.address,
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
                            onChange={(e) => setData('jumlah_siswa_sekolah', e.target.value)}
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
                <p className="text-xs text-slate-400">
                    Pilih sekolah dari Dapodik untuk mengisi NPSN, alamat, lokasi,
                    dan jumlah siswa otomatis. Dua nilai disimpan terpisah;
                    selisih dihitung sebagai Sekolah − Dapodik.
                </p>
                <p className="text-xs text-slate-500">
                    Sumber jumlah siswa:{' '}
                    {data.jumlah_siswa_dapodik !== ''
                        ? data.dapodik_source || 'belum tersedia'
                        : 'belum tersedia'}
                    .
                </p>
            </Section>

            <Section icon={<MapPinned className="h-3.5 w-3.5" />} title="Lokasi">
                <div className="grid gap-4">
                    <Field label="Alamat" error={errors.address}>
                        <Textarea
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            placeholder="QR4H+4FM, RT.7/RW.5, Mampang Prpt., …"
                        />
                    </Field>
                    <Field label="Link Google Maps" error={errors.maps_url}>
                        <Input
                            value={data.maps_url}
                            onChange={(e) => setData('maps_url', e.target.value)}
                            placeholder="https://maps.app.goo.gl/…"
                        />
                    </Field>
                </div>
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
        </div>
    );
}

export default function FjpForm({
    initial,
    action,
    method,
    title,
}: {
    initial?: Partial<FjpFormData>;
    action: string;
    method: 'post' | 'put';
    title: string;
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
                    <FjpFormFields form={form} />
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
