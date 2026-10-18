import { CardTable, EmptyState, PageHeader, Pagination } from '@/Components/AppBits';
import {
    ReceiptFormData,
    ReceiptFormFields,
    ReceiptProduct,
    emptyReceiptForm,
} from '@/Components/ReceiptForm';
import { Button, Modal, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatNumber } from '@/lib/utils';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type ReceiptRow = {
    id: number;
    receipt_number: string;
    product: string | null;
    sku: string | null;
    quantity: number;
    supplier: string | null;
    received_date: string | null;
    recorded_by: string | null;
    photo_url: string | null;
};

export default function ReceiptsIndex({
    receipts,
    products,
}: {
    receipts: { data: ReceiptRow[]; links: any[]; [k: string]: any };
    products: ReceiptProduct[];
}) {
    const permissions = usePage().props.auth.permissions;
    const canManage = permissions.includes('warehouse.manage');

    const [open, setOpen] = useState(false);
    const form = useForm<ReceiptFormData>(emptyReceiptForm());

    const openCreate = () => {
        form.setData(emptyReceiptForm());
        form.clearErrors();
        setOpen(true);
    };

    const closeForm = () => {
        setOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('warehouse.receipts.store'), { forceFormData: true, onSuccess: closeForm });
    };

    return (
        <AppLayout title="Barang Masuk">
            <Head title="Barang Masuk" />
            <PageHeader title="Barang Masuk" subtitle="Riwayat penerimaan stok ke gudang">
                {canManage && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Barang Masuk
                    </Button>
                )}
            </PageHeader>

            <CardTable>
                {receipts.data.length === 0 ? (
                    <EmptyState title="Belum ada penerimaan" description="Catat barang masuk untuk menambah stok gudang." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>No. Terima</TH>
                                    <TH>Produk</TH>
                                    <TH className="text-right">Jumlah</TH>
                                    <TH>Supplier</TH>
                                    <TH>Tanggal Terima</TH>
                                    <TH>Dicatat Oleh</TH>
                                    <TH>Foto</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {receipts.data.map((r) => (
                                    <TR key={r.id}>
                                        <TD className="font-medium text-slate-800">{r.receipt_number}</TD>
                                        <TD>
                                            <p className="font-medium text-slate-800">{r.product ?? '-'}</p>
                                            <p className="text-xs text-slate-400">{r.sku}</p>
                                        </TD>
                                        <TD className="text-right">{formatNumber(r.quantity)}</TD>
                                        <TD>{r.supplier ?? '-'}</TD>
                                        <TD>{formatDate(r.received_date)}</TD>
                                        <TD>{r.recorded_by ?? '-'}</TD>
                                        <TD>
                                            {r.photo_url ? (
                                                <a href={r.photo_url} target="_blank" rel="noreferrer" className="text-sm font-medium text-blue-600 hover:underline">
                                                    Lihat Foto
                                                </a>
                                            ) : (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={receipts} />
                    </>
                )}
            </CardTable>

            <Modal
                open={open}
                onClose={closeForm}
                size="lg"
                title="Catat Barang Masuk"
                description="Stok akan bertambah saat penerimaan disimpan."
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={closeForm}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="receipt-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="receipt-form" onSubmit={submit}>
                    <ReceiptFormFields form={form} products={products} />
                </form>
            </Modal>
        </AppLayout>
    );
}
