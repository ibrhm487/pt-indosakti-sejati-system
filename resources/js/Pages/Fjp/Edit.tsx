import { PageHeader } from '@/Components/AppBits';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import FjpForm, { FjpFormOptions } from './Form';

export default function FjpEdit({
    fjp,
    formOptions,
}: {
    fjp: any;
    formOptions?: FjpFormOptions;
}) {
    const initial = {
        school_name: fjp.school_name ?? '',
        npsn: fjp.npsn ?? '',
        address: fjp.address ?? '',
        city: fjp.city ?? '',
        province: fjp.province ?? '',
        kecamatan: fjp.kecamatan ?? '',
        kelurahan: fjp.kelurahan ?? '',
        maps_url: fjp.maps_url ?? '',
        jumlah_siswa_sekolah: fjp.jumlah_siswa_sekolah ?? '',
        jumlah_siswa_dapodik: fjp.jumlah_siswa_dapodik ?? '',
        dapodik_source: fjp.dapodik_source
            ?? (fjp.jumlah_siswa_dapodik != null ? 'Manual' : ''),
        notes: fjp.notes ?? '',
        brand_id: fjp.brand_id != null ? String(fjp.brand_id) : '',
        event_type_id: fjp.event_type_id != null ? String(fjp.event_type_id) : '',
        event_date: fjp.event_date ?? '',
        start_time: fjp.start_time ?? '',
        sampling_target:
            fjp.sampling_target != null
                ? String(fjp.sampling_target)
                : fjp.jumlah_siswa_sekolah != null
                  ? String(fjp.jumlah_siswa_sekolah)
                  : '',
        jug_required: Boolean(fjp.jug_required),
        permitter_total_outlet_canteen_sell_drink:
            fjp.permitter_total_outlet_canteen_sell_drink != null
                ? String(fjp.permitter_total_outlet_canteen_sell_drink)
                : '',
        permitter_quantity_with_milo:
            fjp.permitter_quantity_with_milo != null
                ? String(fjp.permitter_quantity_with_milo)
                : '',
        permitter_status: fjp.permitter_status ?? '',
        permitter_quantity_without_milo:
            fjp.permitter_quantity_without_milo != null
                ? String(fjp.permitter_quantity_without_milo)
                : '',
        team_sampling_quantity_with_milo:
            fjp.team_sampling_quantity_with_milo != null
                ? String(fjp.team_sampling_quantity_with_milo)
                : '',
        team_sampling_quantity_without_milo:
            fjp.team_sampling_quantity_without_milo != null
                ? String(fjp.team_sampling_quantity_without_milo)
                : '',
    };

    return (
        <AppLayout title="Ubah FJP">
            <Head title="Ubah FJP" />
            <PageHeader
                title="Ubah FJP"
                breadcrumbs={[
                    { label: 'FJP', href: route('fjp.index') },
                    { label: 'Ubah FJP' },
                ]}
            />
            <FjpForm
                initial={initial}
                action={route('fjp.update', fjp.id)}
                method="put"
                title="Ubah FJP"
                options={formOptions}
            />
        </AppLayout>
    );
}
