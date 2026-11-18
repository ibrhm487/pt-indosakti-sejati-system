import { CardTable, EmptyState, FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import {
    EMPTY_KITCHEN_FORM,
    KitchenEventOption,
    KitchenFormData,
    KitchenFormFields,
    KitchenStaffOption,
} from '@/Components/KitchenForm';
import { Badge, Button, Input, Modal, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

const STAGE_VARIANT: Record<string, 'outline' | 'warning' | 'info' | 'success'> = {
    washing: 'outline',
    cooking: 'warning',
    ready: 'info',
    done: 'success',
};

export default function KitchenIndex({
    requests,
    filters,
    statuses,
    canCreate,
    reportMode = false,
    events,
    staff,
    filterEvents,
}: {
    requests: any;
    filters: { status?: string; event_id?: string | number; date?: string };
    statuses: { value: string; label: string }[];
    canCreate: boolean;
    reportMode?: boolean;
    events: KitchenEventOption[];
    staff: KitchenStaffOption[];
    filterEvents: { id: number; event_name: string }[];
}) {
    const [status, setStatus] = useState(filters.status ?? '');
    const [eventId, setEventId] = useState(filters.event_id ? String(filters.event_id) : '');
    const [date, setDate] = useState(filters.date ?? '');
    const [open, setOpen] = useState(false);
    const form = useForm<KitchenFormData>(EMPTY_KITCHEN_FORM);

    const grouped = useMemo(() => {
        const buckets = new Map<string, any[]>();

        for (const row of requests.data ?? []) {
            const key = row.effective_date ?? row.event_date ?? 'unknown';
            if (!buckets.has(key)) buckets.set(key, []);
            buckets.get(key)!.push(row);
        }

        return Array.from(buckets.entries());
    }, [requests.data]);

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
            <PageHeader
                title="Dapur & Higienitas"
                subtitle={
                    reportMode
                        ? 'Laporan dokumentasi cuci JUG dan masak susu per event'
                        : 'Daftar tugas persiapan JUG dan laporan higienitas'
                }
            >
                {canCreate && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> {reportMode ? 'Buat Laporan' : 'Tugas Kitchen'}
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
                        Tanggal Malam
                        <Input
                            aria-label="Filter tanggal malam"
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
                    <EmptyState
                        title={reportMode ? 'Belum ada laporan kitchen' : 'Belum ada tugas kitchen'}
                        description={
                            reportMode
                                ? 'Buat laporan dokumentasi baru untuk memulai.'
                                : 'Buat tugas kitchen baru untuk memulai.'
                        }
                    />
                ) : (
                    <>
                        {grouped.map(([date, rows]) => (
                            <div key={date} className="border-b border-border last:border-b-0">
                                <div className="flex flex-wrap items-center justify-between gap-2 bg-page/70 px-4 py-2.5">
                                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted">
                                        <CalendarDays className="h-3.5 w-3.5" />
                                        Malam, {date === 'unknown' ? 'Tanpa tanggal' : formatDate(date)}
                                    </div>
                                    <Badge variant="outline">
                                        {rows.length} {reportMode ? 'laporan' : 'tugas'}
                                    </Badge>
                                </div>
                                <Table>
                                    <THead>
                                        <TR>
                                            <TH>Event</TH>
                                            <TH>Tanggal Event</TH>
                                            <TH className="text-right">Qty JUG</TH>
                                            {!reportMode && <TH>Tanggal Persiapan</TH>}
                                            {!reportMode && <TH>Penanggung Jawab</TH>}
                                            <TH>Progres</TH>
                                            <TH>Status</TH>
                                        </TR>
                                    </THead>
                                    <TBody>
                                        {rows.map((r: any) => (
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
                                                {!reportMode && <TD>{formatDate(r.preparation_date)}</TD>}
                                                {!reportMode && <TD>{r.assignee ?? '-'}</TD>}
                                                <TD>
                                                    <Badge variant={STAGE_VARIANT[r.stage] ?? 'outline'}>
                                                        {r.stage_label ?? '-'}
                                                    </Badge>
                                                </TD>
                                                <TD>
                                                    <StatusBadge status={r.status} label={r.status_label} />
                                                </TD>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            </div>
                        ))}
                        <Pagination paginator={requests} />
                    </>
                )}
            </CardTable>

            <Modal
                open={open}
                onClose={closeForm}
                size="lg"
                title={reportMode ? 'Buat Laporan Kitchen' : 'Buat Tugas Kitchen'}
                description={
                    reportMode
                        ? 'Pilih event lalu unggah foto dokumentasi cuci JUG, masak susu, dan JUG siap.'
                        : 'Buat permintaan persiapan JUG untuk sebuah event.'
                }
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
                            {form.processing ? 'Menyimpan…' : reportMode ? 'Buat Laporan' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="kitchen-form" onSubmit={submitCreate}>
                    <KitchenFormFields form={form} events={events} staff={staff} reportMode={reportMode} />
                </form>
            </Modal>
        </AppLayout>
    );
}
