import { PageHeader } from '@/Components/AppBits';
import AppLayout from '@/Layouts/AppLayout';
import FjpForm from './Form';

export default function FjpCreate() {
    return (
        <AppLayout title="FJP Baru">
            <PageHeader title="Form FJP Baru" subtitle="Buat Formulir Jadwal Pelaksanaan" />
            <FjpForm
                action={route('fjp.store')}
                method="post"
                title="FJP Baru"
            />
        </AppLayout>
    );
}
