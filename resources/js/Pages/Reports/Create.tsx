import { PageHeader } from '@/Components/AppBits';
import ReportForm, {
    EMPTY_REPORT_FORM,
    ReportEventOption,
    ReportFormData,
} from '@/Components/ReportForm';
import { Button, Card } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function ReportsCreate({
    events,
}: {
    events: ReportEventOption[];
}) {
    const form = useForm<ReportFormData>(EMPTY_REPORT_FORM);
    const { post, processing } = form;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('reports.store'));
    };

    return (
        <AppLayout title="Buat Laporan">
            <Head title="Buat Laporan" />
            <PageHeader
                title="Buat Laporan"
                subtitle="Catat realisasi sampling untuk sebuah event"
            />

            <form onSubmit={submit} className="mx-auto max-w-3xl">
                <Card className="p-5 sm:p-6">
                    <ReportForm form={form} events={events} />
                </Card>

                <div className="mt-4 flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('reports.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
