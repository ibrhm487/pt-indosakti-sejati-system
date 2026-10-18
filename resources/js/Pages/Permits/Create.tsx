import { PageHeader } from '@/Components/AppBits';
import PermitForm, {
    EMPTY_PERMIT_FORM,
    PermitFormData,
    PermitOption,
} from '@/Components/PermitForm';
import { Button, Card } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function PermitsCreate({
    events,
    venues,
    assignees,
}: {
    events: PermitOption[];
    venues: PermitOption[];
    assignees: PermitOption[];
}) {
    const form = useForm<PermitFormData>(EMPTY_PERMIT_FORM);
    const { post, processing } = form;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('permits.store'));
    };

    return (
        <AppLayout title="Perizinan Baru">
            <Head title="Perizinan Baru" />
            <PageHeader
                title="Perizinan Baru"
                subtitle="Ajukan permohonan perizinan untuk sebuah event"
            />

            <form onSubmit={submit} className="mx-auto max-w-3xl">
                <Card className="p-5 sm:p-6">
                    <PermitForm
                        form={form}
                        events={events}
                        venues={venues}
                        assignees={assignees}
                    />
                </Card>

                <div className="mt-4 flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('permits.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
