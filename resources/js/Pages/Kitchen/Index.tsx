import { CardTable, EmptyState, FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import {
    EMPTY_KITCHEN_FORM,
    KitchenEventOption,
    KitchenFormData,
    KitchenFormFields,
    KitchenStaffOption,
} from '@/Components/KitchenForm';
import { Button, Input, Modal, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function KitchenIndex({
    requests,
    filters,
    statuses,
    canCreate,
    events,
    staff,
    filterEvents,
}: {
    requests: any;
    filters: { status?: string; event_id?: string | number; date?: string };
    statuses: { value: string; label: string }[];
    canCreate: boolean;
    events: KitchenEventOption[];
    staff: KitchenStaffOption[];
    filterEvents: { id: number; event_name: string }[];
}) {
    const [status, setStatus] = useState(filters.status ?? '');
    const [eventId, setEventId] = useState(filters.event_id ? String(filters.event_id) : '');
    const [date, setDate] = useState(filters.date ?? '');
    const [open, setOpen] = useState(false);
    const form = useForm<KitchenFormData>(EMPTY_KITCHEN_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('kitchen.index'),
            { status: status || undefined, event_id: eventId || undefined, date: date || undefined },
            { preserveState: true },
        );
    };

    const reset = () => {
        setStatus('');
        setEventId('');
        setDate('');
        router.get(route('kitchen.index'), {}, { preserveState: true });
    };

    const openCreate = () => {
        form.setData(EMPTY_KITCHEN_FORM);
        form.clearErrors();
        setOpen(true);
    };

    const closeForm = () => {
        setOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('kitchen.store'), { onSuccess: closeForm });
    };

    return (
        <AppLayout title="Dapur & Higienitas">
            <Head title="Dapur & Higienitas" />
            <PageHeader title="Dapur & Higienitas" subtitle="Daftar tugas persiapan JUG dan laporan higienitas">
                {canCreate && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Tugas Kitchen
                    </Button>
                )}
            </PageHeader>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Event
                        <Select
                            aria-label="Filter event"
                            className="sm:w-56"
                            value={eventId}
                            onChange={(e) => setEventId(e.target.value)}
                        >
                            <option value="">Semua event</option>
                            {filterEvents.map((event) => (
                                <option key={event.id} value={event.id}>
                                    {event.event_name}
                                </option>
                            ))}
                        </Select>
                    </label>
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Tanggal
                        <Input
                            aria-label="Filter tanggal"
                            type="date"
                            className="sm:w-44"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted">
                        Status
                        <Select
                            aria-label="Filter status"
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
                    </label>
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                    <Button type="button" variant="ghost" className="h-11 rounded-xl px-5" onClick={reset}>
                        Reset
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {requests.data.length === 0 ? (
                    <EmptyState title="Belum ada tugas kitchen" description="Buat tugas kitchen baru untuk memulai." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Event</TH>
                                    <TH>Tanggal Event</TH>
                                    <TH className="text-right">Qty JUG</TH>
                                    <TH>Tanggal Persiapan</TH>
                                    <TH>Penanggung Jawab</TH>
                                    <TH>Status</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {requests.data.map((r: any) => (
                                    <TR key={r.id}>
                                        <TD>
                                            <Link
                                                href={route('kitchen.show', r.id)}
                                                className="font-medium text-text hover:text-primary"
                                            >
                                                {r.event}
                                            </Link>
                                        </TD>
                                        <TD>{formatDate(r.event_date)}</TD>
                                        <TD className="text-right">{r.jug_quantity}</TD>
                                        <TD>{formatDate(r.preparation_date)}</TD>
                                        <TD>{r.assignee ?? '-'}</TD>
                                        <TD>
                                            <StatusBadge status={r.status} label={r.status_label} />
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={requests} />
                    </>
                )}
            </CardTable>

            <Modal
                open={open}
                onClose={closeForm}
                size="lg"
                title="Buat Tugas Kitchen"
                description="Buat permintaan persiapan JUG untuk sebuah event."
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={closeForm}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="kitchen-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="kitchen-form" onSubmit={submitCreate}>
                    <KitchenFormFields form={form} events={events} staff={staff} />
                </form>
            </Modal>
        </AppLayout>
    );
}
