import { EmptyState, PageHeader, Pagination } from '@/Components/AppBits';
import {
    Button,
    Card,
    Field,
    Input,
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
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, MapPin, XCircle } from 'lucide-react';
import { FormEvent, useState } from 'react';

type VerificationItem = {
    id: number;
    type: 'venue';
    name: string;
    code: string | null;
    venue: string | null;
    status: string;
    date: string | null;
    url: string | null;
    can_approve_venue: boolean;
};

export default function VerificationIndex({
    items,
    status,
    selectedDate,
}: {
    items: {
        data: VerificationItem[];
        links?: { url: string | null; label: string; active: boolean }[];
        total?: number;
        from?: number | null;
        to?: number | null;
    };
    status: 'pending' | 'approved';
    selectedDate: string;
}) {
    const [rejectingItem, setRejectingItem] = useState<VerificationItem | null>(null);
    const [assigningItem, setAssigningItem] = useState<VerificationItem | null>(null);
    const rejectForm = useForm({ action: 'reject', reason: '' });
    const assignmentForm = useForm({});

    const changeFilter = (nextStatus: string, date = selectedDate) => {
        router.get(
            route('verifications.index'),
            { status: nextStatus, date },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openAssignment = (item: VerificationItem) => {
        assignmentForm.reset();
        setAssigningItem(item);
    };

    const submitAssignment = (event: FormEvent) => {
        event.preventDefault();

        if (!assigningItem) {
            return;
        }

        assignmentForm.post(route('venues.approve', assigningItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                assignmentForm.reset();
                setAssigningItem(null);
            },
        });
    };

    const rejectItem = (event: FormEvent) => {
        event.preventDefault();

        if (!rejectingItem) {
            return;
        }

        rejectForm.post(route('venues.reject', rejectingItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                rejectForm.reset();
                setRejectingItem(null);
            },
        });
    };

    return (
        <AppLayout title="Verifikasi">
            <Head title="Verifikasi" />
            <PageHeader
                title="Verifikasi"
                subtitle="Verifikasi venue berdasarkan tanggal jadwal. Status umum FJP dikelola melalui menu FJP."
            />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <Field label="Tanggal jadwal" className="w-full sm:w-64">
                    <Input
                        type="date"
                        value={selectedDate}
                        onChange={(event) => changeFilter(status, event.target.value)}
                    />
                </Field>
                <Field label="Status venue" className="w-full sm:w-64">
                    <Select
                        value={status}
                        onChange={(event) => changeFilter(event.target.value)}
                    >
                        <option value="pending">Belum disetujui</option>
                        <option value="approved">Disetujui</option>
                    </Select>
                </Field>
            </div>

            {status === 'pending' && (
                <p className="mb-4 text-sm text-muted">
                    Menampilkan item yang menunggu verifikasi. Draft, ditolak, dan
                    dibatalkan tidak masuk antrean ini.
                </p>
            )}

            <Card className="overflow-hidden p-0">
                {items.data.length === 0 ? (
                    <EmptyState
                        title={
                            status === 'pending'
                                ? 'Tidak ada yang menunggu verifikasi'
                                : 'Belum ada data yang disetujui'
                        }
                        description={
                            `Venue dengan status yang dipilih pada ${selectedDate} akan tampil di sini.`
                        }
                    />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Venue</TH>
                                    <TH>Tanggal jadwal</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {items.data.map((item) => (
                                    <TR key={`${item.type}-${item.id}`}>
                                        <TD>
                                            <div className="font-medium text-text">
                                                {item.name}
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted">
                                                <MapPin className="h-3 w-3" />
                                                {item.code ?? item.venue ?? '-'}
                                            </div>
                                        </TD>
                                        <TD className="text-sm text-muted">{item.date ?? selectedDate}</TD>
                                        <TD>
                                            <StatusBadge
                                                status={status === 'approved' ? 'approved' : 'pending'}
                                                label={item.status}
                                            />
                                        </TD>
                                        <TD className="text-right">
                                            <div className="flex justify-end gap-2">
                                                {item.can_approve_venue && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="success"
                                                        onClick={() => openAssignment(item)}
                                                    >
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        Setujui venue
                                                    </Button>
                                                )}
                                                {status === 'pending' && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => setRejectingItem(item)}
                                                    >
                                                        <XCircle className="h-3.5 w-3.5" />
                                                        Tolak
                                                    </Button>
                                                )}
                                                {status === 'approved' && (
                                                    <span className="text-xs text-muted">Selesai</span>
                                                )}
                                            </div>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={items} />
                    </>
                )}
            </Card>

            <Modal
                open={assigningItem !== null}
                onClose={() => {
                    setAssigningItem(null);
                    assignmentForm.reset();
                }}
                title="Setujui venue"
                description="Persetujuan venue tidak mengubah status FJP atau membuat penugasan tim."
                footer={
                    <>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                setAssigningItem(null);
                                assignmentForm.reset();
                            }}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="approve-and-assign-form"
                            size="sm"
                            disabled={assignmentForm.processing}
                        >
                            {assignmentForm.processing ? 'Menyimpan…' : 'Setujui venue'}
                        </Button>
                    </>
                }
            >
                <form id="approve-and-assign-form" onSubmit={submitAssignment} className="space-y-4">
                    {Object.keys(assignmentForm.errors).length > 0 && (
                        <div
                            role="alert"
                            className="rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger"
                        >
                            <ul className="list-inside list-disc space-y-1">
                                {Object.entries(assignmentForm.errors).map(([field, message]) => (
                                    <li key={field}>{String(message)}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <p className="text-sm text-muted">
                        Pembagian tim dan jadwal Event dilakukan melalui generator jadwal pada menu FJP.
                    </p>
                </form>
            </Modal>

            <Modal
                open={rejectingItem !== null}
                onClose={() => {
                    setRejectingItem(null);
                    rejectForm.reset();
                }}
                title="Tolak venue"
                description={`Masukkan alasan penolakan untuk ${rejectingItem?.name ?? 'item ini'}.`}
                footer={
                    <>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                setRejectingItem(null);
                                rejectForm.reset();
                            }}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="reject-fjp-form"
                            size="sm"
                            variant="destructive"
                            disabled={rejectForm.processing}
                        >
                            {rejectForm.processing ? 'Menyimpan…' : 'Tolak venue'}
                        </Button>
                    </>
                }
            >
                <form id="reject-fjp-form" onSubmit={rejectItem}>
                    <Field label="Alasan penolakan" error={rejectForm.errors.reason}>
                        <Input
                            value={rejectForm.data.reason}
                            onChange={(event) =>
                                rejectForm.setData('reason', event.target.value)
                            }
                            placeholder="Jelaskan alasan penolakan"
                        />
                    </Field>
                </form>
            </Modal>
        </AppLayout>
    );
}
