import { PageHeader } from '@/Components/AppBits';
import EventForm, {
    ConfirmedFjpOption,
    EventFormData,
    EventOption,
    EventTypeOption,
    VenueOption,
} from '@/Components/EventForm';
import { Button, Card } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function EventsCreate({
    brands,
    eventTypes,
    venues,
    confirmedFjps,
    pics,
}: {
    brands: EventOption[];
    eventTypes: EventTypeOption[];
    venues: VenueOption[];
    confirmedFjps: ConfirmedFjpOption[];
    pics: EventOption[];
}) {
    const form = useForm<EventFormData>({
        event_name: '',
        fjp_id: '',
        brand_id: '',
        event_type_id: '',
        venue_id: '',
        event_date: '',
        start_time: '',
        end_time: '',
        sampling_target: '',
        notes: '',
        jug_required: false,
        pic_id: '',
    });

    const { post, processing } = form;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('events.store'));
    };

    return (
        <AppLayout title="Event Baru">
            <Head title="Event Baru" />
            <PageHeader title="Event Baru" subtitle="Buat event sampling baru" />

            <form onSubmit={submit} className="mx-auto max-w-3xl">
                <Card className="p-5 sm:p-6">
                    <EventForm
                        form={form}
                        brands={brands}
                        eventTypes={eventTypes}
                        venues={venues}
                        confirmedFjps={confirmedFjps}
                        pics={pics}
                    />
                </Card>

                <div className="mt-4 flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('events.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
