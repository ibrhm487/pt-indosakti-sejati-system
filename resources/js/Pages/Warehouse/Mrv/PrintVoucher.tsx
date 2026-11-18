import { pushToast } from '@/lib/toast';
import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';
import { useState } from 'react';

type PrintMaterial = {
    sku: string | null;
    lotCode: string | null;
    name: string | null;
    quantity: string | number | null;
    unit: string | null;
    remark: string | null;
};

type PrintSupportingItem = {
    name: string | null;
    quantity: string | number | null;
    unit: string | null;
    remark: string | null;
};

type PrintJug = {
    number: string;
    temperature: string | number | null;
};

type PrintVoucherProps = {
    mrvNumber: string;
    activityType: string | null;
    activityDate: string | null;
    week: string;
    shift: string | null;
    brand: string | null;
    teamMember: string | null;
    venues: string[];
    categories: string[];
    materials: PrintMaterial[];
    supportingItems: PrintSupportingItem[];
    jugs: PrintJug[];
    wetTarget: string | number | null;
    dryTarget: string | number | null;
    pickupTime: string | null;
    preparedBy: string | null;
    approvedBy: string | null;
    wetReceivedBy: string | null;
    dryReceivedBy: string | null;
};

const CATEGORY_OPTIONS = [
    { value: 'wet_sample', label: 'Wet Sample', group: 'component' },
    { value: 'dry_sample', label: 'Dry Sample', group: 'component' },
    { value: 'others', label: 'Other', group: 'ready' },
] as const;

export function useMrvPdfDownload(mrvNumber: string) {
    const [downloadingPdf, setDownloadingPdf] = useState(false);

    const downloadPdf = async () => {
        if (downloadingPdf) {
            return;
        }

        setDownloadingPdf(true);

        try {
            await downloadMrvPdf(mrvNumber);
            pushToast('success', 'PDF MRV berhasil diunduh.');
        } catch (error) {
            console.error('Gagal mengunduh PDF MRV.', error);
            pushToast('error', 'PDF MRV gagal dibuat. Silakan coba kembali.');
        } finally {
            setDownloadingPdf(false);
        }
    };

    return { downloadPdf, downloadingPdf };
}

async function downloadMrvPdf(mrvNumber: string): Promise<void> {
    const voucher = document.getElementById('mrv-print-voucher');

    if (!(voucher instanceof HTMLElement)) {
        throw new Error('Elemen voucher MRV untuk ekspor PDF tidak ditemukan.');
    }

    voucher.classList.add('mrv-pdf-exporting');

    try {
        const logo = voucher.querySelector<HTMLImageElement>('img');

        if (logo) {
            await logo.decode();
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
                const clonedVoucher =
                    clonedDocument.getElementById('mrv-print-voucher');

                if (!clonedVoucher) {
                    throw new Error('Voucher MRV tidak dapat disiapkan untuk PDF.');
                }

                Object.assign(clonedVoucher.style, {
                    display: 'block',
                    position: 'static',
                    inset: 'auto',
                    zIndex: 'auto',
                    opacity: '1',
                    width: '283mm',
                });
            },
        });

        if (canvas.width === 0 || canvas.height === 0) {
            throw new Error('Konten voucher MRV kosong.');
        }

        const pdf = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4',
            compress: true,
        });
        const margin = 7;
        const pageWidth = pdf.internal.pageSize.getWidth() - margin * 2;
        const pageHeight = pdf.internal.pageSize.getHeight() - margin * 2;
        const scale = Math.min(pageWidth / canvas.width, pageHeight / canvas.height);
        const imageWidth = canvas.width * scale;
        const imageHeight = canvas.height * scale;
        const image = canvas.toDataURL('image/png');
        const filename =
            mrvNumber.trim().replace(/[^a-zA-Z0-9_-]+/g, '-') || 'MRV';

        pdf.addImage(
            image,
            'PNG',
            (pdf.internal.pageSize.getWidth() - imageWidth) / 2,
            (pdf.internal.pageSize.getHeight() - imageHeight) / 2,
            imageWidth,
            imageHeight,
            undefined,
            'FAST',
        );
        pdf.save(`${filename}.pdf`);
    } finally {
        voucher.classList.remove('mrv-pdf-exporting');
    }
}

function display(value: string | number | null | undefined): string {
    return value === null || value === undefined || value === '' ? '-' : String(value);
}

function SignatureCell({ title, name, role }: { title: string; name: string | null; role: string }) {
    return (
        <td className="mrv-signature-cell">
            <p className="mrv-signature-title">{title}</p>
            <div className="mrv-signature-line">
                <span>Name: {name || '\u00a0'}</span>
                <span>{role}</span>
            </div>
        </td>
    );
}

export default function PrintVoucher({
    mrvNumber,
    activityType,
    activityDate,
    week,
    shift,
    brand,
    teamMember,
    venues,
    categories,
    materials,
    supportingItems,
    jugs,
    wetTarget,
    dryTarget,
    pickupTime,
    preparedBy,
    approvedBy,
    wetReceivedBy,
    dryReceivedBy,
}: PrintVoucherProps) {
    const printMaterials = materials.length > 0 ? materials : [null];
    const printSupportingItems = supportingItems.length > 0 ? supportingItems : [null];
    const printJugs = jugs.length > 0 ? jugs : [null, null];

    return (
        <div id="mrv-print-voucher" className="mrv-print-only">
            <article className="mrv-voucher-sheet">
                <header className="mrv-voucher-heading">
                    <img
                        src="/images/indosakti-logo.png"
                        alt="PT Indosakti Sejati"
                        className="mrv-voucher-logo"
                    />
                    <h1 className="mrv-voucher-title">
                        MATERIAL REQUISITION VOUCHER (MRV)
                    </h1>
                    <span aria-hidden="true" />
                </header>
                <div className="mrv-voucher-meta">
                    <span><strong>No. MRV:</strong> {display(mrvNumber)}</span>
                    <span><strong>Activity:</strong> {display(activityType)}</span>
                    <span><strong>Date:</strong> {display(activityDate)}</span>
                    <span><strong>Week:</strong> {display(week)}</span>
                    <span><strong>Shift:</strong> {display(shift)}</span>
                </div>

                <table className="mrv-voucher-table mrv-description-table">
                    <colgroup>
                        <col className="mrv-description-column" />
                        <col className="mrv-category-column" />
                        <col className="mrv-category-column" />
                        <col className="mrv-category-column" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th colSpan={2}>as a component</th>
                            <th>as a ready to use</th>
                        </tr>
                    </thead>
                    <tbody>
                        {[
                            ['Brand', brand],
                            ['Team member', teamMember],
                            ['Venue', venues.length > 0 ? venues.join(', ') : null],
                        ].map(([label, value], rowIndex) => (
                            <tr key={label}>
                                <td className="mrv-description-value">
                                    <span>{label}</span>
                                    <span>: {display(value)}</span>
                                </td>
                                {rowIndex === 0 &&
                                    CATEGORY_OPTIONS.filter((option) => option.group === 'component').map(
                                        (option) => (
                                            <td
                                                key={option.value}
                                                rowSpan={3}
                                                className="mrv-category-cell"
                                            >
                                                <span className="mrv-print-checkbox">
                                                    {categories.includes(option.value) ? '✓' : ''}
                                                </span>
                                                {option.label}
                                            </td>
                                        ),
                                    )}
                                {rowIndex === 0 && (
                                    <td rowSpan={3} className="mrv-category-cell">
                                        <span className="mrv-print-checkbox">
                                            {categories.includes('others') ? '✓' : ''}
                                        </span>
                                        Other
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>

                <table className="mrv-voucher-table mrv-items-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>SKU</th>
                            <th>Lot Code</th>
                            <th>Item Name</th>
                            <th>Qty</th>
                            <th>UOM</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        {printMaterials.map((item, index) => (
                            <tr key={`material-${index}`}>
                                <td>{index + 1}</td>
                                <td>{display(item?.sku)}</td>
                                <td>{display(item?.lotCode)}</td>
                                <td>{display(item?.name)}</td>
                                <td>{display(item?.quantity)}</td>
                                <td>{display(item?.unit)}</td>
                                <td>{display(item?.remark)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <table className="mrv-voucher-table mrv-supporting-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Supporting</th>
                            <th>Qty</th>
                            <th>UOM</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        {printSupportingItems.map((item, index) => (
                            <tr key={`support-${index}`}>
                                <td>{index + 1}</td>
                                <td>{display(item?.name)}</td>
                                <td>{display(item?.quantity)}</td>
                                <td>{display(item?.unit)}</td>
                                <td>{display(item?.remark)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mrv-voucher-bottom">
                    <table className="mrv-voucher-table mrv-jug-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Jug Number</th>
                                <th>Temperature (°C)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {printJugs.map((jug, index) => (
                                <tr key={`jug-${index}`}>
                                    <td>{index + 1}</td>
                                    <td>{display(jug?.number)}</td>
                                    <td>{display(jug?.temperature)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <table className="mrv-voucher-table mrv-target-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Wet</th>
                                <th>Dry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Sampling Target</th>
                                <td>{display(wetTarget)}</td>
                                <td>{display(dryTarget)}</td>
                            </tr>
                            <tr>
                                <th>UOM</th>
                                <td colSpan={2}>Cups</td>
                            </tr>
                            <tr>
                                <th>Pickup Time</th>
                                <td colSpan={2}>{display(pickupTime)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <table className="mrv-voucher-table mrv-signature-table">
                    <tbody>
                        <tr>
                            <th colSpan={2}>Requisition Validator</th>
                            <th colSpan={2}>Material Provider &amp; Receiver</th>
                        </tr>
                        <tr>
                            <SignatureCell title="Approved By" name={approvedBy} role="PIC" />
                            <SignatureCell title="Prepared By" name={preparedBy} role="Warehouse" />
                            <SignatureCell
                                title="All Material Receiver By"
                                name={wetReceivedBy}
                                role="Cooler"
                            />
                            <SignatureCell
                                title="All Material Received By"
                                name={dryReceivedBy}
                                role="Sampling Team"
                            />
                        </tr>
                    </tbody>
                </table>
            </article>
        </div>
    );
}
