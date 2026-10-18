import {
    CardTable,
    EmptyState,
    FilterCard,
    PageHeader,
    Pagination,
    SearchInput,
} from '@/Components/AppBits';
import EventForm, {
    EventFormData,
    EventOption,
    EventTypeOption,
} from '@/Components/EventForm';
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
import { formatDate } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, List, Plus } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

const EMPTY_FORM: EventFormData = {
    event_name: '',
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
    events,
    calendarEvents,
    month,
    filters,
    statuses,
    canCreate,
    brands,
    eventTypes,
    venues,
    pics,
}: {
    events: any;
    calendarEvents: CalendarEvent[];
    month: string;
    filters: { search?: string; status?: string; brand_id?: number | string; venue_id?: number | string };
    statuses: { value: string; label: string }[];
    canCreate: boolean;
    brands: EventOption[];
    eventTypes: EventTypeOption[];
    venues: EventOption[];
    pics: EventOption[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [brandId, setBrandId] = useState(filters.brand_id ? String(filters.brand_id) : '');
    const [venueId, setVenueId] = useState(filters.venue_id ? String(filters.venue_id) : '');
    const [view, setView] = useState<View>('list');
    const [createOpen, setCreateOpen] = useState(false);
    const form = useForm<EventFormData>(EMPTY_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('events.index'),
            { search, status, brand_id: brandId || undefined, venue_id: venueId || undefined },
            { preserveState: true },
        );
    };

    const goToMonth = (offset: number) => {
        const [year, monthNumber] = month.split('-').map(Number);
        const date = new Date(year, monthNumber - 1 + offset, 1);
        const next = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        router.get(
            route('events.index'),
            { search, status, brand_id: brandId || undefined, venue_id: venueId || undefined, month: next },
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

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
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
                    <div className="flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
                        <button
                            type="button"
                            onClick={() => setView('list')}
                            className={`inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium ${
                                view === 'list'
                                    ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/70'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            <List className="h-4 w-4" /> Daftar
                        </button>
                        <button
                            type="button"
                            onClick={() => setView('calendar')}
                            className={`inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium ${
                                view === 'calendar'
                                    ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/70'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            <CalendarDays className="h-4 w-4" /> Kalender
                        </button>
                    </div>
                </form>
            </FilterCard>

            {view === 'calendar' ? (
                <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <Button variant="ghost" size="sm" onClick={() => goToMonth(-1)}>
                            <ChevronLeft className="h-4 w-4" /> Sebelumnya
                        </Button>
                        <p className="text-sm font-semibold capitalize text-slate-800">
                            {monthLabel}
                        </p>
                        <Button variant="ghost" size="sm" onClick={() => goToMonth(1)}>
                            Berikutnya <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                    <div className="grid grid-cols-7 gap-1">
                        {WEEKDAYS.map((day) => (
                            <div
                                key={day}
                                className="pb-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400"
                            >
                                {day}
                            </div>
                        ))}
                        {calendarCells.map((cell, index) => (
                            <div
                                key={index}
                                className={`min-h-24 rounded-lg border p-1.5 ${
                                    cell.day
                                        ? 'border-slate-100 bg-white'
                                        : 'border-transparent bg-slate-50/40'
                                }`}
                            >
                                {cell.day && (
                                    <p className="mb-1 text-right text-xs font-medium text-slate-400">
                                        {cell.day}
                                    </p>
                                )}
                                <div className="space-y-1">
                                    {cell.events.map((event) => (
                                        <Link
                                            key={event.id}
                                            href={route('events.show', event.id)}
                                            className="block truncate rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
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
            ) : (
                <CardTable>
                    {events.data.length === 0 ? (
                        <EmptyState
                            title="Belum ada event"
                            description="Buat event baru untuk memulai."
                        />
                    ) : (
                        <>
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Kode / Event</TH>
                                        <TH>Brand</TH>
                                        <TH>Lokasi</TH>
                                        <TH>Tanggal</TH>
                                        <TH>PIC</TH>
                                        <TH>JUG</TH>
                                        <TH>Status</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {events.data.map((e: any) => (
                                        <TR key={e.id}>
                                            <TD>
                                                <Link
                                                    href={route('events.show', e.id)}
                                                    className="font-medium text-slate-800 hover:text-indigo-600"
                                                >
                                                    {e.event_name}
                                                </Link>
                                                <p className="font-mono text-xs text-slate-400">
                                                    {e.event_code}
                                                </p>
                                            </TD>
                                            <TD>{e.brand ?? '-'}</TD>
                                            <TD>{e.venue ?? '-'}</TD>
                                            <TD>{formatDate(e.event_date)}</TD>
                                            <TD>{e.pic ?? '-'}</TD>
                                            <TD>{e.jug_required ? 'Ya' : 'Tidak'}</TD>
                                            <TD>
                                                <StatusBadge
                                                    status={e.status}
                                                    label={e.status_label}
                                                />
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                            <Pagination paginator={events} />
                        </>
                    )}
                </CardTable>
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
                        pics={pics}
                    />
                </form>
            </Modal>
        </AppLayout>
    );
}
