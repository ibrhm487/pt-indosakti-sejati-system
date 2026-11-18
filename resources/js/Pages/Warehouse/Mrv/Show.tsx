import { PageHeader } from '@/Components/AppBits';
import EditableDocumentNumber from '@/Components/EditableDocumentNumber';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Modal, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime, formatNumber } from '@/lib/utils';
import PrintVoucher, { useMrvPdfDownload } from '@/Pages/Warehouse/Mrv/PrintVoucher';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileDown, Printer } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

type MrvItem = {
    id: number;
    product: string | null;
    sku: string | null;
    unit: string | null;
    lot_no: string | null;
    quantity_requested: number;
    cold: string | number | null;
    lbs: string | number | null;
    packing: string | null;
    on_the_spot: string | number | null;
    d1: string | number | null;
    d2: string | number | null;
    cups: string | number | null;
    spoon: string | number | null;
    notes: string | null;
    quantity_issued: number;
    quantity_returned: number;
    quantity_damaged: number;
};

type SupportingItem = {
    item: string | null;
    qty: string | number | null;
    uom: string | null;
    remarks: string | null;
};

function VoucherMeta({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">{label}</p>
            <div className="mt-1 text-sm font-semibold text-text">{value}</div>
        </div>
    );
}

function SignatureBlock({ title, name, meta }: { title: string; name: string | null; meta: string }) {
    return (
        <div className="text-center">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted">{title}</p>
            <div className="mrv-signature-gap mt-10 border-t border-dashed border-border pt-2">
                <p className="text-sm font-semibold text-text">Name: {name || '\u00a0'}</p>
                <p className="text-xs text-muted">{meta}</p>
            </div>
        </div>
    );
}

function total(items: MrvItem[], field: keyof MrvItem): number {
    return items.reduce((sum, item) => sum + (Number(item[field]) || 0), 0);
}

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
        event_id: number | null;
        activity_type: string | null;
        activity_date: string | null;
        jug_quantity: number | null;
        shift: string | null;
        jug_details: { jug_number: string; temperature: number | string | null }[];
        sampling_target_wet: number | null;
        sampling_target_dry: number | null;
        event_code: string | null;
        event_date: string | null;
        venue: string | null;
        venues: string[];
        brand: string | null;
        pic: string | null;
        team_member: string | null;
        team_no: string | null;
        material_category: string | null;
        material_categories: string[];
        material_category_label: string | null;
        pickup_time: string | null;
        supporting_items: SupportingItem[];
        material_prepared_by: string | null;
        material_received_by: string | null;
        prepared_by_name: string | null;
        approved_by_name: string | null;
        wet_received_by: string | null;
        dry_received_by: string | null;
        period_month: string;
        period_week: number;
        period_week_label: string;
        period_label: string;
        requester: string | null;
        approver: string | null;
        status: string;
        status_label: string;
        notes: string | null;
        rejection_reason: string | null;
        recipient: string | null;
        photo_url: string | null;
        created_at: string | null;
        approved_at: string | null;
        issued_at: string | null;
    };
    items: MrvItem[];
    returns: { id: number; return_number: string; status: string }[];
    can: { approve: boolean; issue: boolean; return: boolean; manage: boolean };
}) {
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState('');
    const [issuing, setIssuing] = useState(false);
    const { downloadPdf, downloadingPdf } = useMrvPdfDownload(mrv.mrv_number);

    const issueForm = useForm<{ recipient: string; photo: File | null }>({ recipient: '', photo: null });

    const approve = () => router.post(route('warehouse.mrv.approve', mrv.id), {}, { preserveScroll: true });

    const supportingRows: SupportingItem[] = mrv.supporting_items.filter((item) =>
        [item.item, item.qty, item.uom, item.remarks].some((value) => value !== null && value !== ''),
    );

    if (supportingRows.length === 0) {
        supportingRows.push({ item: '', qty: '', uom: '', remarks: '' });
    }

    const submitIssue = (e: FormEvent) => {
        e.preventDefault();
        issueForm.post(route('warehouse.mrv.issue', mrv.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIssuing(false);
                issueForm.reset();
            },
        });
    };

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
            <div className="print:hidden">
                <PageHeader title={mrv.mrv_number} subtitle={mrv.activity_type ?? mrv.event ?? '-'}>
                    <EditableDocumentNumber
                        value={mrv.mrv_number}
                        field="mrv_number"
                        url={route('warehouse.mrv.number', mrv.id)}
                        canEdit={can.manage}
                    />
                    <Link href={route('warehouse.mrv.index')}>
                        <Button variant="outline">Kembali</Button>
                    </Link>
                    <Button variant="outline" onClick={() => window.print()}>
                        <Printer className="h-4 w-4" /> Cetak
                    </Button>
                    <Button
                        variant="primary"
                        onClick={downloadPdf}
                        disabled={downloadingPdf}
                    >
                        <FileDown className="h-4 w-4" />
                        {downloadingPdf ? 'Menyiapkan PDF...' : 'Unduh PDF'}
                    </Button>
                    {can.approve && mrv.status === 'pending' && (
                        <>
                            <Button variant="success" onClick={approve}>
                                Setujui & Keluarkan Stok
                            </Button>
                            <Button variant="destructive" onClick={() => setRejecting(true)}>
                                Tolak
                            </Button>
                        </>
                    )}
                    {can.issue && mrv.status === 'approved' && (
                        <Button variant="primary" onClick={() => setIssuing(true)}>
                            Keluarkan Stok
                        </Button>
                    )}
                    {can.return && mrv.status === 'issued' && (
                        <Link href={route('warehouse.returns.create', { stock_request_id: mrv.id })}>
                            <Button variant="primary">Catat Pengembalian</Button>
                        </Link>
                    )}
                </PageHeader>
            </div>

            <div className="mrv-screen-content space-y-5 print:hidden">
                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-4 border-b-2 border-primary bg-surface px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <img src="/images/indosakti-logo.png" alt="Indosakti" className="h-10 w-auto" />
                        <div className="sm:text-right">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary">
                                Material Requisition Voucher
                            </p>
                            <p className="text-xl font-bold tracking-tight text-text">{mrv.mrv_number}</p>
                            <div className="mt-1 flex sm:justify-end">
                                <StatusBadge status={mrv.status} label={mrv.status_label} />
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4 border-b border-border bg-page/60 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                        <VoucherMeta label="No. MRV" value={mrv.mrv_number} />
                        <VoucherMeta label="Activity Date" value={formatDate(mrv.activity_date ?? mrv.event_date)} />
                        <VoucherMeta label="Week" value={mrv.period_week_label} />
                        <VoucherMeta label="Status" value={mrv.status_label} />
                    </div>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Kegiatan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <VoucherMeta label="Jenis Kegiatan" value={mrv.activity_type ?? mrv.event ?? '-'} />
                            <VoucherMeta label="Jumlah JUG" value={mrv.jug_quantity ?? '-'} />
                            <VoucherMeta label="Team member" value={mrv.team_member ?? mrv.team_no ?? '-'} />
                            <VoucherMeta label="Venue" value={mrv.venues.length > 0 ? mrv.venues.join(', ') : (mrv.venue ?? '-')} />
                            <VoucherMeta label="Brand" value={mrv.brand ?? '-'} />
                            <VoucherMeta label="Kategori Material" value={mrv.material_category_label ?? '-'} />
                            <VoucherMeta label="Activity Date" value={formatDate(mrv.activity_date ?? mrv.event_date)} />
                            <VoucherMeta label="Week" value={mrv.period_week_label} />
                            <VoucherMeta label="Shift" value={mrv.shift ?? '-'} />
                            <VoucherMeta label="Pick Up Time" value={mrv.pickup_time ?? '-'} />
                            {mrv.pic && <VoucherMeta label="PIC" value={mrv.pic} />}
                        </div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardContent className="grid gap-6 p-4 sm:p-5 lg:grid-cols-[1.2fr_0.8fr]">
                        <div>
                            <CardTitle className="mb-3">JUG & Temperature (°C)</CardTitle>
                            <Table className="min-w-[360px]">
                                <THead>
                                    <TR>
                                        <TH className="w-12">No.</TH>
                                        <TH>Jug Number</TH>
                                        <TH>Temperature (°C)</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {mrv.jug_details.length > 0 ? (
                                        mrv.jug_details.map((jug, index) => (
                                            <TR key={`${jug.jug_number}-${index}`}>
                                                <TD>{index + 1}</TD>
                                                <TD>{jug.jug_number}</TD>
                                                <TD>{jug.temperature ?? '-'}</TD>
                                            </TR>
                                        ))
                                    ) : (
                                        <TR>
                                            <TD colSpan={3} className="text-muted">
                                                {mrv.jug_quantity
                                                    ? `${mrv.jug_quantity} JUG`
                                                    : '-'}
                                            </TD>
                                        </TR>
                                    )}
                                </TBody>
                            </Table>
                        </div>
                        <div>
                            <CardTitle className="mb-3">Sampling Target</CardTitle>
                            <Table className="min-w-[260px]">
                                <THead>
                                    <TR>
                                        <TH></TH>
                                        <TH className="text-right">Wet</TH>
                                        <TH className="text-right">Dry</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    <TR>
                                        <TD className="font-medium">Target</TD>
                                        <TD className="text-right">
                                            {formatNumber(mrv.sampling_target_wet)}
                                        </TD>
                                        <TD className="text-right">
                                            {formatNumber(mrv.sampling_target_dry)}
                                        </TD>
                                    </TR>
                                    <TR>
                                        <TD className="font-medium">UOM</TD>
                                        <TD colSpan={2} className="text-right text-muted">
                                            Cups
                                        </TD>
                                    </TR>
                                    <TR>
                                        <TD className="font-medium">Pickup Time</TD>
                                        <TD colSpan={2} className="text-right">
                                            {mrv.pickup_time ?? '-'}
                                        </TD>
                                    </TR>
                                </TBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader>
                        <CardTitle>Material Type</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table className="min-w-[1050px] print:min-w-full print:text-[8px]">
                            <THead>
                                <TR>
                                    <TH className="w-8">No</TH>
                                    <TH>SKU</TH>
                                    <TH>Lot No.</TH>
                                    <TH>Material Type</TH>
                                    <TH className="text-right">Cold</TH>
                                    <TH className="text-right">Qty</TH>
                                    <TH className="text-right">Lbs</TH>
                                    <TH>Packing</TH>
                                    <TH className="text-right">On The Spot</TH>
                                    <TH className="text-right">D1</TH>
                                    <TH className="text-right">D2</TH>
                                    <TH className="text-right">Cups</TH>
                                    <TH className="text-right">Spoon</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {items.map((item, index) => (
                                    <TR key={item.id}>
                                        <TD className="text-muted">{index + 1}</TD>
                                        <TD className="font-medium">{item.sku ?? '-'}</TD>
                                        <TD>{item.lot_no ?? '-'}</TD>
                                        <TD>
                                            <p className="font-medium text-text">{item.product ?? '-'}</p>
                                            <p className="text-xs text-muted">
                                                {[item.unit, item.notes].filter(Boolean).join(' - ')}
                                            </p>
                                        </TD>
                                        <TD className="text-right">{formatNumber(item.cold)}</TD>
                                        <TD className="text-right font-semibold">{formatNumber(item.quantity_requested)}</TD>
                                        <TD className="text-right">{formatNumber(item.lbs)}</TD>
                                        <TD>{item.packing ?? '-'}</TD>
                                        <TD className="text-right">{formatNumber(item.on_the_spot)}</TD>
                                        <TD className="text-right">{formatNumber(item.d1)}</TD>
                                        <TD className="text-right">{formatNumber(item.d2)}</TD>
                                        <TD className="text-right">{formatNumber(item.cups)}</TD>
                                        <TD className="text-right">{formatNumber(item.spoon)}</TD>
                                    </TR>
                                ))}
                            </TBody>
                            <tfoot className="border-t border-border bg-page/60">
                                <TR>
                                    <TD colSpan={4} className="text-xs font-semibold uppercase tracking-wide text-muted">
                                        Total
                                    </TD>
                                    <TD className="text-right text-sm font-semibold">{formatNumber(total(items, 'cold'))}</TD>
                                    <TD className="text-right text-sm font-semibold">{formatNumber(total(items, 'quantity_requested'))}</TD>
                                    <TD className="text-right text-sm font-semibold">{formatNumber(total(items, 'lbs'))}</TD>
                                    <TD></TD>
                                    <TD className="text-right text-sm font-semibold">{formatNumber(total(items, 'on_the_spot'))}</TD>
                                    <TD className="text-right text-sm font-semibold">{formatNumber(total(items, 'd1'))}</TD>
                                    <TD className="text-right text-sm font-semibold">{formatNumber(total(items, 'd2'))}</TD>
                                    <TD className="text-right text-sm font-semibold">{formatNumber(total(items, 'cups'))}</TD>
                                    <TD className="text-right text-sm font-semibold">{formatNumber(total(items, 'spoon'))}</TD>
                                </TR>
                            </tfoot>
                        </Table>

                        {(mrv.notes || mrv.rejection_reason) && (
                            <div className="space-y-3 border-t border-border px-4 py-4 text-sm">
                                {mrv.notes && (
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-muted">Catatan</p>
                                        <p className="mt-1 text-text">{mrv.notes}</p>
                                    </div>
                                )}
                                {mrv.rejection_reason && (
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-muted">
                                            Alasan penolakan
                                        </p>
                                        <p className="mt-1 text-danger">{mrv.rejection_reason}</p>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader>
                        <CardTitle>Supporting Items</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table className="min-w-[700px] print:min-w-full print:text-[9px]">
                            <THead>
                                <TR>
                                    <TH className="w-8">No</TH>
                                    <TH>Item</TH>
                                    <TH className="w-24 text-right">Qty</TH>
                                    <TH className="w-24">UOM</TH>
                                    <TH>Remarks</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {supportingRows.map((item, index) => (
                                    <TR key={index}>
                                        <TD className="text-muted">{index + 1}</TD>
                                        <TD>{item.item || '\u00a0'}</TD>
                                        <TD className="text-right">{formatNumber(item.qty)}</TD>
                                        <TD>{item.uom || '\u00a0'}</TD>
                                        <TD>{item.remarks || '\u00a0'}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Requisition Validator</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <SignatureBlock
                                title="Approved By"
                                name={mrv.approved_by_name ?? mrv.approver}
                                meta="PIC"
                            />
                            <SignatureBlock
                                title="Prepared By"
                                name={mrv.prepared_by_name ?? mrv.requester}
                                meta="Warehouse"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Material Provider & Receiver</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <SignatureBlock
                                title="All Material Receiver By"
                                name={mrv.wet_received_by}
                                meta="Cooler"
                            />
                            <SignatureBlock
                                title="All Material Received By"
                                name={mrv.dry_received_by}
                                meta="Sampling Team"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="print:hidden">
                    <CardHeader>
                        <CardTitle>Status & Bukti</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted">Status</span>
                            <StatusBadge status={mrv.status} label={mrv.status_label} />
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted">Disetujui</span>
                            <span>{formatDateTime(mrv.approved_at)}</span>
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted">Dikeluarkan</span>
                            <span>{formatDateTime(mrv.issued_at)}</span>
                        </div>
                        {mrv.photo_url && (
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted">Foto Bukti</span>
                                <a href={mrv.photo_url} target="_blank" rel="noreferrer" className="text-sm font-medium text-primary hover:underline">
                                    Lihat Foto
                                </a>
                            </div>
                        )}
                        {returns.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                                <span className="text-muted">Pengembalian</span>
                                {returns.map((r) => (
                                    <Badge key={r.id} variant="outline">
                                        {r.return_number}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <PrintVoucher
                mrvNumber={mrv.mrv_number}
                activityType={mrv.activity_type ?? mrv.event}
                activityDate={formatDate(mrv.activity_date ?? mrv.event_date)}
                week={mrv.period_week_label}
                shift={mrv.shift}
                brand={mrv.brand}
                teamMember={mrv.team_member ?? mrv.team_no}
                venues={mrv.venues ?? []}
                categories={
                    mrv.material_categories.length > 0
                        ? mrv.material_categories
                        : mrv.material_category
                          ? [mrv.material_category]
                          : []
                }
                materials={items.map((item) => ({
                    sku: item.sku,
                    lotCode: item.lot_no,
                    name: item.product,
                    quantity: item.quantity_requested,
                    unit: item.unit,
                    remark: item.notes,
                }))}
                supportingItems={mrv.supporting_items
                    .filter((item) =>
                        [item.item, item.qty, item.uom, item.remarks].some(
                            (value) => value !== null && value !== '',
                        ),
                    )
                    .map((item) => ({
                        name: item.item,
                        quantity: item.qty,
                        unit: item.uom,
                        remark: item.remarks,
                    }))}
                jugs={mrv.jug_details.map((jug) => ({
                    number: jug.jug_number,
                    temperature: jug.temperature,
                }))}
                wetTarget={mrv.sampling_target_wet}
                dryTarget={mrv.sampling_target_dry}
                pickupTime={mrv.pickup_time}
                preparedBy={mrv.prepared_by_name ?? mrv.requester}
                approvedBy={mrv.approved_by_name ?? mrv.approver}
                wetReceivedBy={mrv.wet_received_by}
                dryReceivedBy={mrv.dry_received_by}
            />

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

            <Modal
                open={issuing}
                onClose={() => setIssuing(false)}
                title="Keluarkan Stok"
                description="Stok akan dikurangi tepat satu kali. Sertakan foto bukti barang keluar bila tersedia."
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setIssuing(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="issue-form" variant="primary" size="sm" disabled={issueForm.processing}>
                            {issueForm.processing ? 'Memproses...' : 'Keluarkan Stok'}
                        </Button>
                    </>
                }
            >
                <form id="issue-form" onSubmit={submitIssue}>
                    <div className="space-y-4">
                        <Field label="Penerima (opsional)" error={issueForm.errors.recipient}>
                            <Input
                                value={issueForm.data.recipient}
                                onChange={(e) => issueForm.setData('recipient', e.target.value)}
                                placeholder="Nama penerima barang"
                            />
                        </Field>
                        <Field label="Foto Bukti Barang Keluar (opsional)" error={issueForm.errors.photo}>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(e) => issueForm.setData('photo', e.target.files?.[0] ?? null)}
                                className="block w-full cursor-pointer rounded-lg border border-border bg-surface text-sm text-text file:mr-3 file:rounded-lg file:border-0 file:bg-page file:px-3 file:py-2 file:text-text"
                            />
                        </Field>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
