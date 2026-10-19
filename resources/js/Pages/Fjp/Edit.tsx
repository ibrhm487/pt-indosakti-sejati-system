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
        maps_url: fjp.maps_url ?? '',
        jumlah_siswa_sekolah: fjp.jumlah_siswa_sekolah ?? '',
        jumlah_siswa_dapodik: fjp.jumlah_siswa_dapodik ?? '',
        dapodik_source: fjp.dapodik_source
            ?? (fjp.jumlah_siswa_dapodik != null ? 'Manual' : ''),
        notes: fjp.notes ?? '',
        brand_id: fjp.brand_id != null ? String(fjp.brand_id) : '',
        event_type_id: fjp.event_type_id != null ? String(fjp.event_type_id) : '',
        venue_id: fjp.venue_id != null ? String(fjp.venue_id) : '',
        event_date: fjp.event_date ?? '',
        start_time: fjp.start_time ?? '',
        end_time: fjp.end_time ?? '',
        sampling_target: fjp.sampling_target != null ? String(fjp.sampling_target) : '',
        jug_required: Boolean(fjp.jug_required),
    };

    return (
        <AppLayout title="Ubah FJP">
            <Head title={`Ubah FJP ${fjp.fjp_number ?? ''}`} />
            <PageHeader
                title={`Ubah FJP ${fjp.fjp_number ?? ''}`}
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
