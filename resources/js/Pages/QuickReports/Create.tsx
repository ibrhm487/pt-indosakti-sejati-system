import { PageHeader } from '@/Components/AppBits';
import QuickReportForm, {
    EMPTY_QUICK_REPORT_FORM,
    QuickReportEventOption,
    QuickReportFormData,
} from '@/Components/QuickReportForm';
import { Button, Card } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function QuickReportsCreate({
    events,
}: {
    events: QuickReportEventOption[];
}) {
    const form = useForm<QuickReportFormData>(EMPTY_QUICK_REPORT_FORM);
    const { post, processing } = form;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('quick-reports.store'), { forceFormData: true });
    };

    return (
        <AppLayout title="Buat Quick Report">
            <Head title="Buat Quick Report" />
            <PageHeader
                title="Buat Quick Report"
                subtitle="Kirim catatan singkat kegiatan lapangan"
            />

            <form onSubmit={submit} className="mx-auto max-w-3xl">
                <Card className="p-5 sm:p-6">
                    <QuickReportForm form={form} events={events} />
                </Card>

                <div className="mt-4 flex items-center gap-2">
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={processing || events.length === 0}
                    >
                        {processing ? 'Mengirim…' : 'Kirim Quick Report'}
                    </Button>
                    <Link href={route('quick-reports.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
