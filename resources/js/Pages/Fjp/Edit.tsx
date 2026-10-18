import { PageHeader } from '@/Components/AppBits';
import AppLayout from '@/Layouts/AppLayout';
import FjpForm from './Form';

export default function FjpEdit({ fjp }: { fjp: any }) {
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
    };

    return (
        <AppLayout title="Ubah FJP">
            <PageHeader title={`Ubah FJP ${fjp.fjp_number ?? ''}`} />
            <FjpForm
                initial={initial}
                action={route('fjp.update', fjp.id)}
                method="put"
                title="Ubah FJP"
            />
        </AppLayout>
    );
}
