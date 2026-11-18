import { PageHeader } from '@/Components/AppBits';
import EditableDocumentNumber from '@/Components/EditableDocumentNumber';
import { Button } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import PrintVoucher, { ReceiptSlip, useReceiptSlipPdfDownload } from '@/Pages/Warehouse/Receipts/PrintVoucher';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';

export default function ReceiptSlipShow({ slip, can }: { slip: ReceiptSlip; can: { manage: boolean } }) {
    const { downloadPdf, downloadingPdf } = useReceiptSlipPdfDownload(slip.mrs_number);

    return (
        <AppLayout title={slip.mrs_number}>
            <Head title={slip.mrs_number} />
            <div className="mrs-screen-content">
                <PageHeader title={slip.mrs_number} subtitle="Material Receipt Slip tersimpan">
                    <div className="flex flex-wrap items-center gap-2">
                        <EditableDocumentNumber
                            value={slip.mrs_number}
                            field="mrs_number"
                            url={route('warehouse.receipts.number', slip.id)}
                            canEdit={can.manage}
                        />
                        <Link href={route('warehouse.receipts.index')}>
                            <Button variant="outline"><ArrowLeft className="h-4 w-4" /> Kembali</Button>
                        </Link>
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer className="h-4 w-4" /> Cetak
                        </Button>
                        <Button onClick={downloadPdf} disabled={downloadingPdf}>
                            <Download className="h-4 w-4" />
                            {downloadingPdf ? 'Menyiapkan PDF…' : 'Unduh PDF'}
                        </Button>
                    </div>
                </PageHeader>
                <p className="mb-4 text-sm text-muted">
                    {slip.items.length} baris barang · total diterima{' '}
                    {slip.items.reduce((total, item) => total + item.received_quantity, 0).toLocaleString('id-ID')}
                </p>
                <h2 className="mb-3 text-sm font-semibold text-text">Pratinjau lembar cetak</h2>
            </div>
            <PrintVoucher slip={slip} />
        </AppLayout>
    );
}
