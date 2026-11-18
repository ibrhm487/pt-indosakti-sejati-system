import { pushToast } from '@/lib/toast';
import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';
import { useState } from 'react';

export type OmfItem = {
    id: number;
    sku: string;
    item_name: string;
    lot_code: string | null;
    expired_date: string | null;
    quantity: number;
    uom: string;
    remark: string | null;
};

export type OmfDocument = {
    id: number;
    omf_number: string;
    issued_date: string | null;
    brand: string | null;
    brand_code: string | null;
    department: string | null;
    recipient: string | null;
    notes: string | null;
    received_by_name: string | null;
    received_by_role: string | null;
    approved_by_name: string | null;
    approved_by_role: string | null;
    operational_manager_by_name: string | null;
    operational_manager_by_role: string | null;
    recorded_by: string | null;
    recorded_at: string | null;
    items: OmfItem[];
};

function display(value: string | number | null | undefined): string {
    return value === null || value === undefined || value === '' ? '-' : String(value);
}

function Signature({
    name,
    role,
    recordedAt,
}: {
    name: string | null;
    role: string | null;
    recordedAt: string | null;
}) {
    return (
        <div className="mrs-signature">
            <div className="mrs-signature-space" aria-hidden="true" />
            <div className="mrs-signature-name">{name || '\u00a0'}</div>
            <div className="mrs-signature-role">{role || '\u00a0'}</div>
            {name && recordedAt && <div className="mrs-signature-timestamp">{recordedAt}</div>}
        </div>
    );
}

export function useOmfPdfDownload(omfNumber: string) {
    const [downloadingPdf, setDownloadingPdf] = useState(false);

    const downloadPdf = async () => {
        if (downloadingPdf) {
            return;
        }

        setDownloadingPdf(true);

        try {
            const voucher = document.getElementById('omf-print-voucher');

            if (!(voucher instanceof HTMLElement)) {
                throw new Error('Elemen OMF untuk ekspor PDF tidak ditemukan.');
            }

            voucher.classList.add('mrs-pdf-exporting');
            const logo = voucher.querySelector<HTMLImageElement>('img');

            if (logo) {
                if (!logo.complete) {
                    await logo.decode();
                }

                if (logo.naturalWidth === 0) {
                    throw new Error('Logo Indosakti tidak dapat dimuat untuk PDF.');
                }
            }

            await new Promise<void>((resolve) => {
                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(() => resolve());
                });
            });

            const canvas = await html2canvas(voucher, {
                backgroundColor: '#ffffff',
                scale: 2,
                useCORS: true,
                onclone: (clonedDocument) => {
                    const clonedVoucher = clonedDocument.getElementById('omf-print-voucher');

                    if (!clonedVoucher) {
                        throw new Error('OMF tidak dapat disiapkan untuk PDF.');
                    }

                    Object.assign(clonedVoucher.style, {
                        display: 'block',
                        position: 'static',
                        inset: 'auto',
                        zIndex: 'auto',
                        opacity: '1',
                        padding: '0',
                        width: '190mm',
                    });

                    const sheet = clonedVoucher.querySelector<HTMLElement>('.omf-voucher-sheet');

                    if (!sheet) {
                        throw new Error('Lembar OMF tidak ditemukan.');
                    }

                    Object.assign(sheet.style, {
                        width: '190mm',
                        minHeight: '267mm',
                    });
                },
            });

            if (canvas.width === 0 || canvas.height === 0) {
                throw new Error('Konten OMF kosong.');
            }

            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4',
                compress: true,
            });
            const margin = 10;
            const pageWidth = pdf.internal.pageSize.getWidth() - margin * 2;
            const pageHeight = pdf.internal.pageSize.getHeight() - margin * 2;
            const scale = Math.min(pageWidth / canvas.width, pageHeight / canvas.height);
            const imageWidth = canvas.width * scale;
            const imageHeight = canvas.height * scale;
            const filename = omfNumber.trim().replace(/[^a-zA-Z0-9_-]+/g, '-') || 'OMF';

            pdf.addImage(
                canvas.toDataURL('image/png'),
                'PNG',
                (pdf.internal.pageSize.getWidth() - imageWidth) / 2,
                (pdf.internal.pageSize.getHeight() - imageHeight) / 2,
                imageWidth,
                imageHeight,
                undefined,
                'FAST',
            );
            pdf.save(`${filename}.pdf`);
            pushToast('success', 'PDF OMF berhasil diunduh.');
        } catch (error) {
            console.error('Gagal mengunduh PDF OMF.', error);
            pushToast('error', 'PDF OMF gagal dibuat. Silakan coba kembali.');
        } finally {
            document.getElementById('omf-print-voucher')?.classList.remove('mrs-pdf-exporting');
            setDownloadingPdf(false);
        }
    };

    return { downloadPdf, downloadingPdf };
}

export default function PrintVoucher({ omf }: { omf: OmfDocument }) {
    return (
        <div id="omf-print-voucher" className="omf-print-only">
            <article className="omf-voucher-sheet">
                <header className="omf-voucher-heading">
                    <img src="/images/indosakti-logo.png" alt="PT Indosakti Sejati" />
                    <h1>OUT MATERIAL FORM (OMF)</h1>
                    <div className="mrs-document-number">
                        <strong>No. OMF</strong>
                        <span>{display(omf.omf_number)}</span>
                    </div>
                </header>

                <table className="mrs-voucher-table mrs-meta-table">
                    <colgroup>
                        <col className="mrs-meta-label-column" />
                        <col className="mrs-meta-value-column" />
                        <col className="mrs-meta-label-column" />
                        <col className="mrs-meta-value-column" />
                    </colgroup>
                    <tbody>
                        <tr>
                            <th>Brand</th>
                            <td>{display(omf.brand)}</td>
                            <th>Date</th>
                            <td>{display(omf.issued_date)}</td>
                        </tr>
                        <tr>
                            <th>Department</th>
                            <td>{display(omf.department)}</td>
                            <th>Recipient</th>
                            <td>{display(omf.recipient)}</td>
                        </tr>
                    </tbody>
                </table>

                <table className="mrs-voucher-table mrs-items-table">
                    <colgroup>
                        <col style={{ width: '5%' }} />
                        <col style={{ width: '13%' }} />
                        <col style={{ width: '25%' }} />
                        <col style={{ width: '14%' }} />
                        <col style={{ width: '11%' }} />
                        <col style={{ width: '12%' }} />
                        <col style={{ width: '20%' }} />
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>SKU</th>
                            <th>Item Name</th>
                            <th>Lot Code</th>
                            <th>Expired</th>
                            <th>Qty / UOM</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        {omf.items.map((item, index) => (
                            <tr key={item.id}>
                                <td className="mrs-centered-cell">{index + 1}</td>
                                <td className="mrs-centered-cell">{display(item.sku)}</td>
                                <td>{display(item.item_name)}</td>
                                <td className="mrs-centered-cell">{display(item.lot_code)}</td>
                                <td className="mrs-centered-cell">{display(item.expired_date)}</td>
                                <td className="mrs-quantity-cell">
                                    {display(item.quantity)} {display(item.uom)}
                                </td>
                                <td>{display(item.remark)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {omf.notes && (
                    <p className="mrs-voucher-notes">
                        <strong>Notes:</strong> {omf.notes}
                    </p>
                )}

                <table className="mrs-voucher-table mrs-signature-table">
                    <thead>
                        <tr>
                            <th>Received By</th>
                            <th>Approved By (PIC)</th>
                            <th>Approved By (Forwarder)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <Signature
                                    name={omf.received_by_name}
                                    role={omf.received_by_role || 'Warehouse'}
                                    recordedAt={omf.recorded_at}
                                />
                            </td>
                            <td>
                                <Signature
                                    name={omf.approved_by_name}
                                    role={omf.approved_by_role || 'PIC'}
                                    recordedAt={omf.recorded_at}
                                />
                            </td>
                            <td>
                                <Signature
                                    name={omf.operational_manager_by_name}
                                    role={omf.operational_manager_by_role || 'Forwarder'}
                                    recordedAt={omf.recorded_at}
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </div>
    );
}
