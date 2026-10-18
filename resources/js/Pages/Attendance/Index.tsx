import { CardTable, EmptyState, FilterCard, ModalForm, PageHeader, Pagination } from '@/Components/AppBits';
import {
    Badge,
    Button,
    Field,
    Input,
    Select,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

type AttendanceData = {
    event_id: string;
    user_id: string;
    planned_start: string;
    planned_end: string;
};

const EMPTY_ATTENDANCE: AttendanceData = {
    event_id: '',
    user_id: '',
    planned_start: '',
    planned_end: '',
};

export default function AttendanceIndex({
    attendances,
    events,
    users,
    currentUser,
    filters,
    can,
}: {
    attendances: any;
    events: { id: number; event_name: string }[];
    users: { id: number; name: string }[];
    currentUser: { id: number; name: string };
    filters: { event_id?: string | number; status?: string };
    can: { create: boolean; manage: boolean; payroll: boolean };
}) {
    const [eventId, setEventId] = useState(filters.event_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [invalidateId, setInvalidateId] = useState<number | null>(null);

    const createForm = useForm<AttendanceData>(EMPTY_ATTENDANCE);
    const invalidateForm = useForm({ correction_note: '' });

    const canActOn = (row: any) =>
        can.manage || row.user_id === currentUser.id;

    const filter = () => {
        router.get(route('attendance.index'), { event_id: eventId, status }, { preserveState: true });
    };

    const checkIn = (id: number) => {
        router.post(route('attendance.check-in', id), {}, { preserveScroll: true });
    };

    const checkOut = (id: number) => {
        router.post(route('attendance.check-out', id), {}, { preserveScroll: true });
    };

    const openCreate = () => {
        createForm.setData({
            ...EMPTY_ATTENDANCE,
            user_id: can.manage ? '' : String(currentUser.id),
        });
        createForm.clearErrors();
        setCreateOpen(true);
    };

    const closeCreate = () => {
        setCreateOpen(false);
        createForm.reset();
        createForm.clearErrors();
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(route('attendance.store'), {
            preserveScroll: true,
            onSuccess: closeCreate,
        });
    };

    const openInvalidate = (id: number) => {
        invalidateForm.reset();
        invalidateForm.clearErrors();
        setInvalidateId(id);
    };

    const submitInvalidate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!invalidateId) return;
        invalidateForm.post(route('attendance.invalidate', invalidateId), {
            preserveScroll: true,
            onSuccess: () => {
                setInvalidateId(null);
                invalidateForm.reset();
            },
        });
    };

    return (
        <AppLayout title="Absensi">
            <Head title="Absensi" />
            <PageHeader title="Absensi" subtitle="Daftar kehadiran">
                {can.payroll && (
                    <Link href={route('attendance.payroll')}>
                        <Button variant="outline">Payroll</Button>
                    </Link>
                )}
                {can.create && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Absensi
                    </Button>
                )}
            </PageHeader>

            <FilterCard>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <Field label="Event" className="sm:w-56">
                        <Select value={eventId} onChange={(e) => setEventId(e.target.value)}>
                            <option value="">Semua Event</option>
                            {events.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.event_name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Status" className="sm:w-56">
                        <Select value={status} onChange={(e) => setStatus(e.target.value)}>
                            <option value="">Semua Status</option>
                            <option value="planned">Planned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="invalid">Invalid</option>
                        </Select>
                    </Field>
                    <Button onClick={filter} variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </div>
            </FilterCard>

            <CardTable>
                {attendances.data.length === 0 ? (
                    <EmptyState title="Belum ada absensi" description="Data absensi akan muncul di sini." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Event</TH>
                                    <TH>Tanggal</TH>
                                    <TH>Pengguna</TH>
                                    <TH>Check In</TH>
                                    <TH>Check Out</TH>
                                    <TH>Status</TH>
                                    <TH>Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {attendances.data.map((a: any) => (
                                    <TR key={a.id}>
                                        <TD>{a.event}</TD>
                                        <TD>{formatDateTime(a.event_date)}</TD>
                                        <TD>{a.user}</TD>
                                        <TD>{formatDateTime(a.check_in_at)}</TD>
                                        <TD>{formatDateTime(a.check_out_at)}</TD>
                                        <TD>
                                            <Badge
                                                variant={
                                                    a.status === 'completed'
                                                        ? 'success'
                                                        : a.status === 'invalid'
                                                          ? 'danger'
                                                          : a.status === 'in_progress'
                                                            ? 'info'
                                                            : 'default'
                                                }
                                            >
                                                {a.status}
                                            </Badge>
                                        </TD>
                                        <TD>
                                            <div className="flex flex-wrap gap-2">
                                                {can.create && canActOn(a) && !a.check_in_at && (
                                                    <Button size="sm" variant="outline" onClick={() => checkIn(a.id)}>
                                                        Check-in
                                                    </Button>
                                                )}
                                                {can.create && canActOn(a) && a.check_in_at && !a.check_out_at && (
                                                    <Button size="sm" variant="primary" onClick={() => checkOut(a.id)}>
                                                        Check-out
                                                    </Button>
                                                )}
                                                {can.manage && (
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => openInvalidate(a.id)}
                                                    >
                                                        Invalidate
                                                    </Button>
                                                )}
                                            </div>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={attendances} />
                    </>
                )}
            </CardTable>

            <ModalForm
                open={createOpen}
                onClose={closeCreate}
                title="Tambah Absensi Terencana"
                description="Rencanakan kehadiran pengguna pada sebuah event."
                onSubmit={submitCreate}
                submitLabel={createForm.processing ? 'Menyimpan…' : 'Tambah'}
                submitDisabled={createForm.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Event" error={createForm.errors.event_id} className="sm:col-span-2">
                        <Select
                            value={createForm.data.event_id}
                            onChange={(e) => createForm.setData('event_id', e.target.value)}
                        >
                            <option value="">Pilih Event</option>
                            {events.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.event_name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {can.manage ? (
                        <Field label="Pengguna" error={createForm.errors.user_id} className="sm:col-span-2">
                            <Select
                                value={createForm.data.user_id}
                                onChange={(e) => createForm.setData('user_id', e.target.value)}
                            >
                                <option value="">Pilih Pengguna</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    ) : (
                        <Field label="Pengguna" className="sm:col-span-2">
                            <Input value={currentUser.name} readOnly disabled />
                        </Field>
                    )}
                    <Field label="Rencana Mulai" error={createForm.errors.planned_start}>
                        <Input
                            type="datetime-local"
                            value={createForm.data.planned_start}
                            onChange={(e) => createForm.setData('planned_start', e.target.value)}
                        />
                    </Field>
                    <Field label="Rencana Selesai" error={createForm.errors.planned_end}>
                        <Input
                            type="datetime-local"
                            value={createForm.data.planned_end}
                            onChange={(e) => createForm.setData('planned_end', e.target.value)}
                        />
                    </Field>
                </div>
            </ModalForm>

            <ModalForm
                open={invalidateId !== null}
                onClose={() => setInvalidateId(null)}
                title="Invalidate Absensi"
                description="Absensi tidak akan dihitung untuk payroll."
                onSubmit={submitInvalidate}
                submitLabel={invalidateForm.processing ? 'Memproses…' : 'Invalidate'}
                submitVariant="destructive"
                submitDisabled={invalidateForm.processing}
                size="sm"
            >
                <Field label="Catatan Koreksi" error={invalidateForm.errors.correction_note}>
                    <Input
                        value={invalidateForm.data.correction_note}
                        onChange={(e) => invalidateForm.setData('correction_note', e.target.value)}
                    />
                </Field>
            </ModalForm>
        </AppLayout>
    );
}
