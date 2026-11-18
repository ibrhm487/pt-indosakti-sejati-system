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

export default function EventsEdit({
    event,
    brands,
    eventTypes,
    venues,
    confirmedFjps,
    pics,
}: {
    event: any;
    brands: EventOption[];
    eventTypes: EventTypeOption[];
    venues: VenueOption[];
    confirmedFjps: ConfirmedFjpOption[];
    pics: EventOption[];
}) {
    const form = useForm<EventFormData>({
        event_name: event.event_name ?? '',
        fjp_id: event.fjp_id != null ? String(event.fjp_id) : '',
        brand_id: event.brand_id != null ? String(event.brand_id) : '',
        event_type_id: event.event_type_id != null ? String(event.event_type_id) : '',
        venue_id: event.venue_id != null ? String(event.venue_id) : '',
        event_date: event.event_date ?? '',
        start_time: event.start_time ?? '',
        end_time: event.end_time ?? '',
        sampling_target:
            event.sampling_target != null ? String(event.sampling_target) : '',
        notes: event.notes ?? '',
        jug_required: !!event.jug_required,
        pic_id: event.pic_id != null ? String(event.pic_id) : '',
    });

    const { put, processing } = form;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('events.update', event.id));
    };

    return (
        <AppLayout title="Ubah Event">
            <Head title={`Ubah ${event.event_name}`} />
            <PageHeader
                title="Ubah Event"
                subtitle={`${event.event_code}${event.is_terminal ? ' · Event terkunci' : ''}`}
            />

            {event.is_terminal && (
                <div className="mb-4 rounded-md border border-warning/20 bg-warning/10 px-4 py-3 text-sm text-warning">
                    Event yang sudah selesai atau dibatalkan tidak dapat diubah.
                </div>
            )}

            <form onSubmit={submit} className="mx-auto max-w-3xl">
                <Card className="p-5 sm:p-6">
                    <EventForm
                        form={form}
                        brands={brands}
                        eventTypes={eventTypes}
                        venues={venues}
                        confirmedFjps={confirmedFjps}
                        currentVenue={event.venue}
                        pics={pics}
                    />
                </Card>

                <div className="mt-4 flex items-center gap-2">
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={processing || event.is_terminal}
                    >
                        {processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('events.show', event.id)}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
