import {
    CardTable,
    EmptyState,
    FilterCard,
    PageHeader,
    Pagination,
    SearchInput,
} from '@/Components/AppBits';
import PermitForm, {
    EMPTY_PERMIT_FORM,
    PermitFormData,
    PermitOption,
} from '@/Components/PermitForm';
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
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function PermitsIndex({
    permits,
    filters,
    statuses,
    canCreate,
    events,
    venues,
    assignees,
}: {
    permits: any;
    filters: { status?: string };
    statuses: { value: string; label: string }[];
    canCreate: boolean;
    events: PermitOption[];
    venues: PermitOption[];
    assignees: PermitOption[];
}) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState(filters.status ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const form = useForm<PermitFormData>(EMPTY_PERMIT_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('permits.index'),
            { search, status },
            { preserveState: true },
        );
    };

    const openCreate = () => {
        form.setData(EMPTY_PERMIT_FORM);
        form.clearErrors();
        setCreateOpen(true);
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('permits.store'), {
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
            },
        });
    };

    return (
        <AppLayout title="Perizinan">
            <Head title="Perizinan" />
            <PageHeader
                title="Perizinan"
                subtitle="Daftar permohonan perizinan event"
            >
                {canCreate && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Perizinan Baru
                    </Button>
                )}
            </PageHeader>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        placeholder="Cari nomor izin atau event…"
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
                    <Button
                        type="submit"
                        variant="default"
                        className="h-11 rounded-xl px-5"
                    >
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {permits.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada perizinan"
                        description="Buat perizinan baru untuk memulai."
                    />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nomor / Event</TH>
                                    <TH>Lokasi</TH>
                                    <TH>Tenggat</TH>
                                    <TH>Penanggung Jawab</TH>
                                    <TH>Status</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {permits.data.map((p: any) => (
                                    <TR key={p.id}>
                                        <TD>
                                            <Link
                                                href={route('permits.show', p.id)}
                                                className="font-medium text-text hover:text-primary"
                                            >
                                                {p.event ?? '-'}
                                            </Link>
                                            <p className="font-mono text-xs text-muted">
                                                {p.permit_number}
                                            </p>
                                        </TD>
                                        <TD>{p.venue ?? '-'}</TD>
                                        <TD>{formatDate(p.deadline)}</TD>
                                        <TD>{p.assignee ?? '-'}</TD>
                                        <TD>
                                            <StatusBadge
                                                status={p.status}
                                                label={p.status_label}
                                            />
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={permits} />
                    </>
                )}
            </CardTable>

            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                size="lg"
                title="Perizinan Baru"
                description="Ajukan permohonan perizinan untuk sebuah event."
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
                            form="permit-create-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="permit-create-form" onSubmit={submitCreate}>
                    <PermitForm
                        form={form}
                        events={events}
                        venues={venues}
                        assignees={assignees}
                    />
                </form>
            </Modal>
        </AppLayout>
    );
}
