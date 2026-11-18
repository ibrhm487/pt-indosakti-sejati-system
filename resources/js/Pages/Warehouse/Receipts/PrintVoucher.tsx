import { pushToast } from '@/lib/toast';
import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';
import { useState } from 'react';

export type ReceiptSlipItem = {
    id: number;
    sku: string;
    item_name: string;
    lot_code: string | null;
    description: string | null;
    received_quantity: number;
    uom: string;
    expired_date: string | null;
    status: string | null;
    remark: string | null;
};

export type ReceiptSlip = {
    id: number;
    mrs_number: string;
    receipt_type: string;
    do_number: string;
    shipper: string | null;
    supplier: string | null;
    document_status: string | null;
    received_date: string;
    release_date: string | null;
    release_time: string | null;
    authorized_by: string | null;
    quantity_released: number | null;
    quantity_assurance: number | null;
    received_by_name: string | null;
    received_by_role: string | null;
    approved_by_name: string | null;
    approved_by_role: string | null;
    operational_manager_by_name: string | null;
    operational_manager_by_role: string | null;
    recorded_at: string | null;
    quality_assurance_by_name: string | null;
    quality_assurance_by_role: string | null;
    notes: string | null;
    recorded_by: string | null;
    items: ReceiptSlipItem[];
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

export function useReceiptSlipPdfDownload(mrsNumber: string) {
    const [downloadingPdf, setDownloadingPdf] = useState(false);

    const downloadPdf = async () => {
        if (downloadingPdf) {
            return;
        }

        setDownloadingPdf(true);

        try {
            const voucher = document.getElementById('mrs-print-voucher');

            if (!(voucher instanceof HTMLElement)) {
                throw new Error('Elemen MRS untuk ekspor PDF tidak ditemukan.');
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
                    const clonedVoucher = clonedDocument.getElementById('mrs-print-voucher');

                    if (!clonedVoucher) {
                        throw new Error('MRS tidak dapat disiapkan untuk PDF.');
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

                    const sheet = clonedVoucher.querySelector<HTMLElement>('.mrs-voucher-sheet');

                    if (!sheet) {
                        throw new Error('Lembar MRS tidak ditemukan.');
                    }

                    Object.assign(sheet.style, {
                        width: '190mm',
                        minHeight: '267mm',
                    });
                },
            });

            if (canvas.width === 0 || canvas.height === 0) {
                throw new Error('Konten MRS kosong.');
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
            const filename = mrsNumber.trim().replace(/[^a-zA-Z0-9_-]+/g, '-') || 'MRS';

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
            pushToast('success', 'PDF MRS berhasil diunduh.');
        } catch (error) {
            console.error('Gagal mengunduh PDF MRS.', error);
            pushToast('error', 'PDF MRS gagal dibuat. Silakan coba kembali.');
        } finally {
            document.getElementById('mrs-print-voucher')?.classList.remove('mrs-pdf-exporting');
            setDownloadingPdf(false);
        }
    };

    return { downloadPdf, downloadingPdf };
}

export default function PrintVoucher({ slip }: { slip: ReceiptSlip }) {
    return (
        <div id="mrs-print-voucher" className="mrs-print-only">
            <article className="mrs-voucher-sheet">
                <header className="mrs-voucher-heading">
                    <img src="/images/indosakti-logo.png" alt="PT Indosakti Sejati" />
                    <h1>MATERIAL RECEIPT SLIP (MRS)</h1>
                    <div className="mrs-document-number">
                        <strong>No. MRS</strong>
                        <span>{display(slip.mrs_number)}</span>
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
                            <th>Type</th>
                            <td>{display(slip.receipt_type)}</td>
                            <th>DO Number</th>
                            <td>{display(slip.do_number)}</td>
                        </tr>
                        <tr>
                            <th>Shipper / Courier</th>
                            <td>{display(slip.shipper)}</td>
                            <th>Date</th>
                            <td>{display(slip.received_date)}</td>
                        </tr>
                        <tr>
                            <th>Supplier</th>
                            <td>{display(slip.supplier)}</td>
                            <th>Document Status</th>
                            <td>{display(slip.document_status)}</td>
                        </tr>
                    </tbody>
                </table>

                <table className="mrs-voucher-table mrs-items-table">
                    <colgroup>
                        <col className="mrs-sku-column" />
                        <col className="mrs-lot-column" />
                        <col className="mrs-item-name-column" />
                        <col className="mrs-description-column" />
                        <col className="mrs-quantity-column" />
                        <col className="mrs-expiry-column" />
                        <col className="mrs-status-column" />
                        <col className="mrs-remark-column" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Lot Code</th>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Received Qty / UOM</th>
                            <th>Expired Date</th>
                            <th>Status</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        {slip.items.map((item) => (
                            <tr key={item.id}>
                                <td className="mrs-centered-cell">{display(item.sku)}</td>
                                <td className="mrs-centered-cell">{display(item.lot_code)}</td>
                                <td>{display(item.item_name)}</td>
                                <td>{display(item.description)}</td>
                                <td className="mrs-quantity-cell">
                                    {display(item.received_quantity)} {display(item.uom)}
                                </td>
                                <td className="mrs-centered-cell">{display(item.expired_date)}</td>
                                <td className="mrs-centered-cell">{display(item.status)}</td>
                                <td>{display(item.remark)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <table className="mrs-voucher-table mrs-release-table">
                    <colgroup>
                        <col />
                        <col />
                        <col />
                        <col />
                    </colgroup>
                    <tbody>
                        <tr>
                            <th>Date Released</th>
                            <td>
                                {display(
                                    slip.release_date
                                        ? `${slip.release_date}${slip.release_time ? ` ${slip.release_time}` : ''}`
                                        : null,
                                )}
                            </td>
                            <th>Authorized By</th>
                            <td>{display(slip.authorized_by)}</td>
                        </tr>
                        <tr>
                            <th>Quantity Released</th>
                            <td>{display(slip.quantity_released)}</td>
                            <th>Quantity Assurance</th>
                            <td>{display(slip.quantity_assurance)}</td>
                        </tr>
                    </tbody>
                </table>

                {slip.notes && <p className="mrs-voucher-notes"><strong>Notes:</strong> {slip.notes}</p>}

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
                                    name={slip.received_by_name}
                                    role={slip.received_by_role || 'Warehouse'}
                                    recordedAt={slip.recorded_at}
                                />
                            </td>
                            <td>
                                <Signature
                                    name={slip.approved_by_name}
                                    role={slip.approved_by_role || 'PIC'}
                                    recordedAt={slip.recorded_at}
                                />
                            </td>
                            <td>
                                <Signature
                                    name={slip.operational_manager_by_name}
                                    role={slip.operational_manager_by_role || 'Forwarder'}
                                    recordedAt={slip.recorded_at}
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </div>
    );
}
