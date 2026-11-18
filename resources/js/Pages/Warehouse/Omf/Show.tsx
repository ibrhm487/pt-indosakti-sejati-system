import { PageHeader } from '@/Components/AppBits';
import EditableDocumentNumber from '@/Components/EditableDocumentNumber';
import { Button } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import PrintVoucher, { OmfDocument, useOmfPdfDownload } from '@/Pages/Warehouse/Omf/PrintVoucher';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';

export default function OmfShow({ omf, can }: { omf: OmfDocument; can: { manage: boolean } }) {
    const { downloadPdf, downloadingPdf } = useOmfPdfDownload(omf.omf_number);

    return (
        <AppLayout title={omf.omf_number}>
            <Head title={omf.omf_number} />
            <div className="mrs-screen-content">
                <PageHeader
                    title={omf.omf_number}
                    subtitle="Out Material Form tersimpan"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <EditableDocumentNumber
                            value={omf.omf_number}
                            field="omf_number"
                            url={route('warehouse.omf.number', omf.id)}
                            canEdit={can.manage}
                        />
                        <Link href={route('warehouse.omf.index')}>
                            <Button variant="outline">
                                <ArrowLeft className="h-4 w-4" /> Kembali
                            </Button>
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
                    {omf.items.length} baris barang · total keluar{' '}
                    {omf.items
                        .reduce((total, item) => total + item.quantity, 0)
                        .toLocaleString('id-ID')}
                </p>
                <h2 className="mb-3 text-sm font-semibold text-text">Pratinjau lembar cetak</h2>
            </div>
            <PrintVoucher omf={omf} />
        </AppLayout>
    );
}
