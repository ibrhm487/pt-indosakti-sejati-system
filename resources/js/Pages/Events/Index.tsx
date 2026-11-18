import {
    ConfirmButton,
    EmptyState,
    FilterCard,
    PageHeader,
    SearchInput,
} from '@/Components/AppBits';
import EventForm, {
    ConfirmedFjpOption,
    EventFormData,
    EventOption,
    EventTypeOption,
} from '@/Components/EventForm';
import {
    EventBrandGroup,
    EventGroupView,
} from '@/Components/EventGroupView';
import {
    Button,
    Modal,
    Select,
    StatusBadge,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, List, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

const EMPTY_FORM: EventFormData = {
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
};

type CalendarEvent = {
    id: number;
    event_code: string;
    event_name: string;
    event_date: string | null;
    status: string;
    status_label: string;
};

const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

type View = 'list' | 'calendar';

export default function EventsIndex({
    eventGroups,
    calendarEvents,
    month,
    filters,
    statuses,
    canCreate,
    canUpdate,
    canDelete,
    brands,
    eventTypes,
    confirmedFjps,
    venues,
    pics,
}: {
    eventGroups: EventBrandGroup[];
    calendarEvents: CalendarEvent[];
    month: string;
    filters: { search?: string; status?: string; brand_id?: number | string; event_type_id?: number | string; venue_id?: number | string };
    statuses: { value: string; label: string }[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    brands: EventOption[];
    eventTypes: EventTypeOption[];
    confirmedFjps: ConfirmedFjpOption[];
    venues: EventOption[];
    pics: EventOption[];
}) {
    const { errors } = usePage().props as any;
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [brandId, setBrandId] = useState(filters.brand_id ? String(filters.brand_id) : '');
    const [eventTypeId, setEventTypeId] = useState(filters.event_type_id ? String(filters.event_type_id) : '');
    const [venueId, setVenueId] = useState(filters.venue_id ? String(filters.venue_id) : '');
    const [view, setView] = useState<View>('list');
    const [createOpen, setCreateOpen] = useState(false);
    const form = useForm<EventFormData>(EMPTY_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('events.index'),
            { search, status, brand_id: brandId || undefined, event_type_id: eventTypeId || undefined, venue_id: venueId || undefined },
            { preserveState: true },
        );
    };

    const goToMonth = (offset: number) => {
        const [year, monthNumber] = month.split('-').map(Number);
        const date = new Date(year, monthNumber - 1 + offset, 1);
        const next = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        router.get(
            route('events.index'),
            { search, status, brand_id: brandId || undefined, event_type_id: eventTypeId || undefined, venue_id: venueId || undefined, month: next },
            { preserveState: true, preserveScroll: true },
        );
    };

    const openCreate = () => {
        form.clearErrors();
        form.reset();
        setCreateOpen(true);
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('events.store'), {
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
            },
        });
    };

    const calendarCells = useMemo(() => {
        const [year, monthNumber] = month.split('-').map(Number);
        const first = new Date(year, monthNumber - 1, 1);
        const startWeekday = (first.getDay() + 6) % 7;
        const daysInMonth = new Date(year, monthNumber, 0).getDate();

        const byDay = new Map<number, CalendarEvent[]>();
        calendarEvents.forEach((event) => {
            if (!event.event_date) return;
            const day = Number(event.event_date.slice(8, 10));
            byDay.set(day, [...(byDay.get(day) ?? []), event]);
        });

        const cells: { day: number | null; events: CalendarEvent[] }[] = [];
        for (let i = 0; i < startWeekday; i++) {
            cells.push({ day: null, events: [] });
        }
        for (let day = 1; day <= daysInMonth; day++) {
            cells.push({ day, events: byDay.get(day) ?? [] });
        }
        while (cells.length % 7 !== 0) {
            cells.push({ day: null, events: [] });
        }

        return cells;
    }, [month, calendarEvents]);

    const monthLabel = useMemo(() => {
        const [year, monthNumber] = month.split('-').map(Number);
        return new Date(year, monthNumber - 1, 1).toLocaleDateString('id-ID', {
            month: 'long',
            year: 'numeric',
        });
    }, [month]);

    return (
        <AppLayout title="Event">
            <Head title="Event" />
            <PageHeader
                title="Event"
                subtitle="Daftar event dan status kesiapan"
            >
                {canCreate && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Event Baru
                    </Button>
                )}
            </PageHeader>

            {errors?.event && (
                <div className="mb-4 rounded-md border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                    {errors.event}
                </div>
            )}

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <SearchInput
                        placeholder="Cari nama atau kode event…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Select
                        className="sm:w-56"
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                    >
                        <option value="">Semua status</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </Select>
                    <Select
                        className="sm:w-48"
                        value={brandId}
                        onChange={(e) => setBrandId(e.target.value)}
                    >
                        <option value="">Semua brand</option>
                        {brands.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </Select>
                    <Select
                        className="sm:w-52"
                        value={eventTypeId}
                        onChange={(e) => setEventTypeId(e.target.value)}
                    >
                        <option value="">Semua jenis event</option>
                        {eventTypes
                            .filter(
                                (t) =>
                                    !brandId ||
                                    !t.brand_id ||
                                    String(t.brand_id) === brandId,
                            )
                            .map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                    </Select>
                    <Select
                        className="sm:w-48"
                        value={venueId}
                        onChange={(e) => setVenueId(e.target.value)}
                    >
                        <option value="">Semua lokasi</option>
                        {venues.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.name}
                            </option>
                        ))}
                    </Select>
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                    <div className="flex items-center gap-1 rounded-xl border border-border bg-page p-1">
                        <button
                            type="button"
                            onClick={() => setView('list')}
                            className={`inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium ${
                                view === 'list'
                                    ? 'bg-surface text-text shadow-sm ring-1 ring-border'
                                    : 'text-text hover:text-text'
                            }`}
                        >
                            <List className="h-4 w-4" /> Daftar
                        </button>
                        <button
                            type="button"
                            onClick={() => setView('calendar')}
                            className={`inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium ${
                                view === 'calendar'
                                    ? 'bg-surface text-text shadow-sm ring-1 ring-border'
                                    : 'text-text hover:text-text'
                            }`}
                        >
                            <CalendarDays className="h-4 w-4" /> Kalender
                        </button>
                    </div>
                </form>
            </FilterCard>

            {view === 'calendar' ? (
                <div className="rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <Button variant="ghost" size="sm" onClick={() => goToMonth(-1)}>
                            <ChevronLeft className="h-4 w-4" /> Sebelumnya
                        </Button>
                        <p className="text-sm font-semibold capitalize text-text">
                            {monthLabel}
                        </p>
                        <Button variant="ghost" size="sm" onClick={() => goToMonth(1)}>
                            Berikutnya <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                    <div className="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <div className="grid min-w-[640px] grid-cols-7 gap-1">
                        {WEEKDAYS.map((day) => (
                            <div
                                key={day}
                                className="pb-2 text-center text-xs font-semibold uppercase tracking-wide text-muted"
                            >
                                {day}
                            </div>
                        ))}
                        {calendarCells.map((cell, index) => (
                            <div
                                key={index}
                                className={`min-h-24 rounded-lg border p-1.5 ${
                                    cell.day
                                        ? 'border-border bg-surface'
                                        : 'border-transparent bg-page/40'
                                }`}
                            >
                                {cell.day && (
                                    <p className="mb-1 text-right text-xs font-medium text-muted">
                                        {cell.day}
                                    </p>
                                )}
                                <div className="space-y-1">
                                    {cell.events.map((event) => (
                                        <Link
                                            key={event.id}
                                            href={route('events.show', event.id)}
                                            className="block truncate rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary hover:bg-primary/10"
                                            title={event.event_name}
                                        >
                                            {event.event_name}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                    </div>
                </div>
            ) : (
                <EventGroupView
                    groups={eventGroups}
                    empty={
                        <EmptyState
                            title="Belum ada event"
                            description="Event yang sesuai filter akan tampil di sini."
                        />
                    }
                    renderTable={(rows, type) => (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Kode / Event</TH>
                                    <TH>{type.requires_school ? 'Sekolah' : 'Venue'}</TH>
                                    <TH>Kota</TH>
                                    <TH>PIC</TH>
                                    <TH>JUG</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {rows.map((event) => (
                                    <TR key={event.id}>
                                        <TD>
                                            <Link href={route('events.show', event.id)} className="font-medium text-text hover:text-primary">
                                                {event.event_name}
                                            </Link>
                                            <p className="font-mono text-xs text-muted">{event.event_code}</p>
                                        </TD>
                                        <TD>{event.venue ?? '-'}</TD>
                                        <TD>{event.venue_city ?? '-'}</TD>
                                        <TD>{event.pic ?? '-'}</TD>
                                        <TD>{event.jug_required ? 'Ya' : 'Tidak'}</TD>
                                        <TD><StatusBadge status={event.status} label={event.status_label} /></TD>
                                        <TD className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Link href={route('events.show', { event: event.id, tab: 'tim' })}>
                                                    <Button size="sm" variant="outline"><Users className="h-3.5 w-3.5" /> Tim</Button>
                                                </Link>
                                                {canUpdate && !['completed', 'cancelled'].includes(event.status) && (
                                                    <Link href={route('events.edit', event.id)}>
                                                        <Button size="sm" variant="outline"><Pencil className="h-3.5 w-3.5" /> Ubah</Button>
                                                    </Link>
                                                )}
                                                {canDelete && (
                                                    <ConfirmButton
                                                        title="Hapus Event"
                                                        description={`Hapus event ${event.event_name}? Data penugasan tim juga akan dihapus.`}
                                                        onConfirm={() => router.delete(route('events.destroy', event.id), { preserveScroll: true })}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </ConfirmButton>
                                                )}
                                            </div>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                />
            )}

            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                size="xl"
                title="Event Baru"
                description="Lengkapi informasi event sampling."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setCreateOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="event-create-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="event-create-form" onSubmit={submitCreate}>
                    <EventForm
                        form={form}
                        brands={brands}
                        eventTypes={eventTypes}
                        venues={venues}
                        confirmedFjps={confirmedFjps}
                        pics={pics}
                    />
                </form>
            </Modal>
        </AppLayout>
    );
}
