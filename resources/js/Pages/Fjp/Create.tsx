import { PageHeader } from '@/Components/AppBits';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import FjpForm, { FjpFormOptions } from './Form';

export default function FjpCreate({ formOptions }: { formOptions?: FjpFormOptions }) {
    return (
        <AppLayout title="FJP Baru">
            <Head title="FJP Baru" />
            <PageHeader
                title="Form FJP Baru"
                subtitle="Buat Formulir Jadwal Pelaksanaan"
                breadcrumbs={[
                    { label: 'FJP', href: route('fjp.index') },
                    { label: 'FJP Baru' },
                ]}
            />
            <FjpForm
                action={route('fjp.store')}
                method="post"
                title="FJP Baru"
                options={formOptions}
            />
        </AppLayout>
    );
}
