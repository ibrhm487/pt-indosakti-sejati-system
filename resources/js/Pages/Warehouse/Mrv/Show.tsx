import { ConfirmButton, PageHeader } from '@/Components/AppBits';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Modal, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatNumber } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type MrvItem = {
    id: number;
    product: string | null;
    sku: string | null;
    unit: string | null;
    quantity_requested: number;
    quantity_issued: number;
    quantity_returned: number;
    quantity_damaged: number;
};

export default function MrvShow({
    mrv,
    items,
    returns,
    can,
}: {
    mrv: {
        id: number;
        mrv_number: string;
        event: string | null;
        event_id: number;
        requester: string | null;
        approver: string | null;
        status: string;
        status_label: string;
        notes: string | null;
        rejection_reason: string | null;
        approved_at: string | null;
        issued_at: string | null;
    };
    items: MrvItem[];
    returns: { id: number; return_number: string; status: string }[];
    can: { approve: boolean; issue: boolean; return: boolean };
}) {
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState('');

    const approve = () => router.post(route('warehouse.mrv.approve', mrv.id), {}, { preserveScroll: true });

    const issue = () => router.post(route('warehouse.mrv.issue', mrv.id), {}, { preserveScroll: true });

    const submitReject = (e: FormEvent) => {
        e.preventDefault();
        router.post(
            route('warehouse.mrv.reject', mrv.id),
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRejecting(false);
                    setReason('');
                },
            },
        );
    };

    return (
        <AppLayout title={mrv.mrv_number}>
            <Head title={mrv.mrv_number} />
            <PageHeader title={mrv.mrv_number} subtitle={mrv.event ?? '-'}>
                <Link href={route('warehouse.mrv.index')}>
                    <Button variant="outline">Kembali</Button>
                </Link>
                {can.approve && mrv.status === 'pending' && (
                    <>
                        <Button variant="success" onClick={approve}>
                            Setujui
                        </Button>
                        <Button variant="destructive" onClick={() => setRejecting(true)}>
                            Tolak
                        </Button>
                    </>
                )}
                {can.issue && mrv.status === 'approved' && (
                    <ConfirmButton
                        variant="primary"
                        title="Keluarkan stok"
                        description="Stok akan dikurangi tepat satu kali saat dikeluarkan."
                        onConfirm={issue}
                    >
                        Keluarkan Stok
                    </ConfirmButton>
                )}
                {can.return && mrv.status === 'issued' && (
                    <Link href={route('warehouse.returns.create', { stock_request_id: mrv.id })}>
                        <Button variant="primary">Catat Pengembalian</Button>
                    </Link>
                )}
            </PageHeader>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Barang</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Produk</TH>
                                    <TH className="text-right">Diminta</TH>
                                    <TH className="text-right">Dikeluarkan</TH>
                                    <TH className="text-right">Kembali</TH>
                                    <TH className="text-right">Rusak</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {items.map((item) => (
                                    <TR key={item.id}>
                                        <TD>
                                            <p className="font-medium text-slate-800">{item.product ?? '-'}</p>
                                            <p className="text-xs text-slate-400">{item.sku}</p>
                                        </TD>
                                        <TD className="text-right">
                                            {formatNumber(item.quantity_requested)} {item.unit}
                                        </TD>
                                        <TD className="text-right">{formatNumber(item.quantity_issued)}</TD>
                                        <TD className="text-right">{formatNumber(item.quantity_returned)}</TD>
                                        <TD className="text-right">{formatNumber(item.quantity_damaged)}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Ringkasan</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-slate-500">Status</span>
                            <StatusBadge status={mrv.status} label={mrv.status_label} />
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-slate-500">Pemohon</span>
                            <span>{mrv.requester ?? '-'}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-slate-500">Penyetuju</span>
                            <span>{mrv.approver ?? '-'}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-slate-500">Disetujui</span>
                            <span>{formatDateTime(mrv.approved_at)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-slate-500">Dikeluarkan</span>
                            <span>{formatDateTime(mrv.issued_at)}</span>
                        </div>
                        {mrv.notes && (
                            <div>
                                <p className="text-slate-500">Catatan</p>
                                <p className="mt-1">{mrv.notes}</p>
                            </div>
                        )}
                        {mrv.rejection_reason && (
                            <div>
                                <p className="text-slate-500">Alasan penolakan</p>
                                <p className="mt-1 text-red-600">{mrv.rejection_reason}</p>
                            </div>
                        )}

                        {returns.length > 0 && (
                            <div className="border-t border-slate-100 pt-3">
                                <p className="mb-2 text-slate-500">Pengembalian</p>
                                <div className="flex flex-wrap gap-1">
                                    {returns.map((r) => (
                                        <Badge key={r.id} variant="outline">
                                            {r.return_number}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Modal
                open={rejecting}
                onClose={() => setRejecting(false)}
                title="Tolak MRV"
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setRejecting(false)}>
                            Batal
                        </Button>
                        <Button type="button" variant="destructive" size="sm" onClick={submitReject}>
                            Tolak
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitReject}>
                    <Field label="Alasan penolakan">
                        <Input value={reason} onChange={(e) => setReason(e.target.value)} />
                    </Field>
                </form>
            </Modal>
        </AppLayout>
    );
}
