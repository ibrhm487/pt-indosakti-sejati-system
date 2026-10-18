import { CardTable, EmptyState, FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import {
    EMPTY_KITCHEN_FORM,
    KitchenEventOption,
    KitchenFormData,
    KitchenFormFields,
    KitchenStaffOption,
} from '@/Components/KitchenForm';
import { Button, Modal, Select, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
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
}: {
    requests: any;
    filters: { status?: string };
    statuses: { value: string; label: string }[];
    canCreate: boolean;
    events: KitchenEventOption[];
    staff: KitchenStaffOption[];
}) {
    const [status, setStatus] = useState(filters.status ?? '');
    const [open, setOpen] = useState(false);
    const form = useForm<KitchenFormData>(EMPTY_KITCHEN_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('kitchen.index'), { status }, { preserveState: true });
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
        <AppLayout title="Kitchen">
            <Head title="Kitchen" />
            <PageHeader title="Tugas Kitchen" subtitle="Daftar permintaan JUG">
                {canCreate && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Tugas Kitchen
                    </Button>
                )}
            </PageHeader>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <Select className="sm:w-56" value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="">Semua status</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </Select>
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
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
                                                className="font-medium text-slate-800 hover:text-indigo-600"
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
