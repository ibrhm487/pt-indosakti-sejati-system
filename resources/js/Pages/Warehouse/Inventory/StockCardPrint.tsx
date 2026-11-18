import { pushToast } from '@/lib/toast';
import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';
import { useState } from 'react';

export type StockCardRow = {
    no: number;
    date: string | null;
    beginning: number;
    incoming_document: string | null;
    incoming_document_kind: string | null;
    incoming_quantity: number | null;
    outgoing_document: string | null;
    outgoing_document_kind: string | null;
    uom: string;
    expired_date: string | null;
    is_damage: boolean;
};

export type StockCardOutgoingDetail = {
    no: number;
    document_number: string | null;
    document_kind: string | null;
    quantity: number;
    uom: string;
    balance: number;
    is_damage: boolean;
};

export type StockCardType = {
    id: number;
    name: string;
    sku: string;
    description: string | null;
    unit: string;
    brand: string | null;
    brand_code: string | null;
};

export type StockCardData = {
    product: StockCardType;
    lots: { lot_code: string; expired_date: string | null }[];
    selected_lot: string | null;
    lot_expired_date: string | null;
    direction: 'incoming' | 'outgoing';
    type_options: string[];
    type_key: string | null;
    type_raw: string | null;
    rows: StockCardRow[];
    outgoing_details: StockCardOutgoingDetail[];
    totals: { incoming: number; outgoing: number; damage: number };
    summary: { beginning: number; incoming: number; outgoing: number; damage: number; final: number };
    period: { week: string; month: string; year: string };
};

function display(value: string | number | null | undefined): string {
    return value === null || value === undefined || value === '' ? '-' : String(value);
}

function quantity(value: number | null): string {
    return value === null ? '' : String(value);
}

export function useStockCardPdfDownload(card: StockCardData) {
    const [downloadingPdf, setDownloadingPdf] = useState(false);

    const downloadPdf = async () => {
        if (downloadingPdf) {
            return;
        }

        setDownloadingPdf(true);

        try {
            const voucher = document.getElementById('sc-print-voucher');

            if (!(voucher instanceof HTMLElement)) {
                throw new Error('Elemen Stock Card untuk ekspor PDF tidak ditemukan.');
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
                    const clonedVoucher = clonedDocument.getElementById('sc-print-voucher');

                    if (!clonedVoucher) {
                        throw new Error('Stock Card tidak dapat disiapkan untuk PDF.');
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

                    const sheet = clonedVoucher.querySelector<HTMLElement>('.sc-voucher-sheet');

                    if (!sheet) {
                        throw new Error('Lembar Stock Card tidak ditemukan.');
                    }

                    Object.assign(sheet.style, {
                        width: '190mm',
                        minHeight: '267mm',
                    });
                },
            });

            if (canvas.width === 0 || canvas.height === 0) {
                throw new Error('Konten Stock Card kosong.');
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
            const direction = card.direction === 'incoming' ? 'INCOMING' : 'OUTGOING';
            const filename = `SC-${direction}-${card.product.sku}-${card.selected_lot ?? 'NO-LOT'}`
                .replace(/[^a-zA-Z0-9_-]+/g, '-')
                .replace(/-+/g, '-');

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
            pushToast('success', 'PDF Stock Card berhasil diunduh.');
        } catch (error) {
            console.error('Gagal mengunduh PDF Stock Card.', error);
            pushToast('error', 'PDF Stock Card gagal dibuat. Silakan coba kembali.');
        } finally {
            document.getElementById('sc-print-voucher')?.classList.remove('mrs-pdf-exporting');
            setDownloadingPdf(false);
        }
    };

    return { downloadPdf, downloadingPdf };
}

function TypeBlock({ card }: { card: StockCardData }) {
    const fixed = card.type_options.filter((option) => option !== 'Other');

    return (
        <div className="sc-type-block">
            <p className="sc-section-title">TYPE:</p>
            <div className="sc-type-grid">
                {fixed.map((option) => (
                    <span key={option} className="sc-type-option">
                        <span className={`sc-checkbox${card.type_key === option ? ' sc-checkbox-checked' : ''}`} />
                        {option}
                    </span>
                ))}
                <span className="sc-type-option">
                    <span className={`sc-checkbox${card.type_key === 'Other' ? ' sc-checkbox-checked' : ''}`} />
                    Other:
                    <span className="sc-type-other-line">
                        {card.type_key === 'Other' && card.type_raw ? card.type_raw : '\u00a0'}
                    </span>
                </span>
            </div>
        </div>
    );
}

function Signature({ role, withPosition = false }: { role: string; withPosition?: boolean }) {
    return (
        <div className="mrs-signature">
            <div className="mrs-signature-space" aria-hidden="true" />
            <div className="mrs-signature-name">{'\u00a0'}</div>
            <div className="mrs-signature-role">{role}</div>
            {withPosition && (
                <>
                    <div className="sc-signature-field">Name: ____________________</div>
                    <div className="sc-signature-field">Position: _________________</div>
                </>
            )}
        </div>
    );
}

export default function StockCardPrint({ card }: { card: StockCardData }) {
    const incoming = card.direction === 'incoming';
    const unit = card.product.unit;
    const totalOutgoingAll = card.totals.outgoing + card.totals.damage;

    return (
        <div id="sc-print-voucher" className="mrs-print-only">
            <article className="mrs-voucher-sheet sc-voucher-sheet">
                <header className="mrs-voucher-heading sc-voucher-heading">
                    <img src="/images/indosakti-logo.png" alt="PT Indosakti Sejati" />
                    <div>
                        <h1>STOCK CARD (SC)</h1>
                        <p className="sc-heading-sub">{incoming ? 'INCOMING / RETURN' : 'OUTGOING'}</p>
                    </div>
                    <div />
                </header>

                <TypeBlock card={card} />

                <table className="mrs-voucher-table sc-info-table">
                    <tbody>
                        <tr>
                            <th>Item No</th>
                            <td>{display(card.product.sku)}</td>
                            <th>Description</th>
                            <td>{display(card.product.name)}</td>
                        </tr>
                        <tr>
                            <th>SKU</th>
                            <td>{display(card.product.sku)}</td>
                            <th>Unit</th>
                            <td>{display(card.product.unit)}</td>
                        </tr>
                        <tr>
                            <th>Lot No</th>
                            <td>{display(card.selected_lot)}</td>
                            <th>Expired Date</th>
                            <td>{display(card.lot_expired_date)}</td>
                        </tr>
                    </tbody>
                </table>

                <section className="sc-section">
                    <p className="sc-section-title">{incoming ? 'STOCK MOVEMENT' : 'STOCK CARD'}</p>
                    <table className="mrs-voucher-table sc-movement-table">
                        <colgroup>
                            <col style={{ width: '5%' }} />
                            <col style={{ width: '10%' }} />
                            <col style={{ width: '11%' }} />
                            <col style={{ width: '18%' }} />
                            <col style={{ width: '9%' }} />
                            <col style={{ width: '9%' }} />
                            <col style={{ width: '12%' }} />
                            <col style={{ width: '26%' }} />
                        </colgroup>
                        <thead>
                            <tr>
                                <th rowSpan={2}>No</th>
                                <th rowSpan={2}>Date</th>
                                <th rowSpan={2}>Beginning Stock</th>
                                <th colSpan={3}>Incoming / Return</th>
                                <th rowSpan={2}>Expired Date</th>
                                <th rowSpan={2}>Outgoing / Damage</th>
                            </tr>
                            <tr>
                                <th>Document No</th>
                                <th>Qty</th>
                                <th>UOM</th>
                            </tr>
                        </thead>
                        <tbody>
                            {card.rows.length === 0 ? (
                                <tr>
                                    <td className="mrs-centered-cell" colSpan={8}>
                                        Belum ada pergerakan untuk lot ini.
                                    </td>
                                </tr>
                            ) : (
                                card.rows.map((row) => (
                                    <tr key={row.no}>
                                        <td className="mrs-centered-cell">{row.no}</td>
                                        <td className="mrs-centered-cell">{display(row.date)}</td>
                                        <td className="mrs-centered-cell">
                                            {row.beginning === 0 ? '-' : row.beginning}
                                        </td>
                                        <td className="mrs-centered-cell">{display(row.incoming_document)}</td>
                                        <td className="mrs-centered-cell">{quantity(row.incoming_quantity)}</td>
                                        <td className="mrs-centered-cell">
                                            {row.incoming_quantity === null ? '' : row.uom}
                                        </td>
                                        <td className="mrs-centered-cell">{display(row.expired_date)}</td>
                                        <td className="mrs-centered-cell">
                                            {row.outgoing_document
                                                ? `${row.outgoing_document}${row.is_damage ? ' (Damage)' : ''}`
                                                : '-'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </section>

                <section className="sc-section">
                    <p className="sc-section-title">{incoming ? 'OUTGOING / DAMAGE DETAIL' : 'OUTGOING / DAMAGE'}</p>
                    <table className="mrs-voucher-table sc-detail-table">
                        <thead>
                            <tr>
                                <th>MRV / OMF No</th>
                                <th>Qty</th>
                                <th>UOM</th>
                                {incoming && <th>Balance Stock</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {incoming && (
                                <tr>
                                    <td className="mrs-centered-cell">{'\u00a0'}</td>
                                    <td className="mrs-centered-cell">{'\u00a0'}</td>
                                    <td className="mrs-centered-cell">{'\u00a0'}</td>
                                    <td className="mrs-centered-cell">{card.summary.beginning}</td>
                                </tr>
                            )}
                            {card.outgoing_details.length === 0 ? (
                                <tr>
                                    <td className="mrs-centered-cell" colSpan={incoming ? 4 : 3}>
                                        Belum ada barang keluar.
                                    </td>
                                </tr>
                            ) : (
                                card.outgoing_details.map((detail) => (
                                    <tr key={detail.no}>
                                        <td className="mrs-centered-cell">
                                            {display(detail.document_number)}
                                            {detail.is_damage ? ' (Damage)' : ''}
                                        </td>
                                        <td className="mrs-centered-cell">{detail.quantity}</td>
                                        <td className="mrs-centered-cell">{detail.uom}</td>
                                        {incoming && (
                                            <td className="mrs-centered-cell">{detail.balance}</td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </section>

                {incoming ? (
                    <table className="mrs-voucher-table sc-total-table">
                        <tbody>
                            <tr>
                                <th>TOTAL INCOMING</th>
                                <td>
                                    {card.totals.incoming} {unit}
                                </td>
                            </tr>
                            <tr>
                                <th>TOTAL OUTGOING</th>
                                <td>
                                    {totalOutgoingAll} {unit}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                ) : (
                    <table className="mrs-voucher-table sc-balance-table">
                        <tbody>
                            <tr>
                                <th>Beginning Stock</th>
                                <td>
                                    {card.summary.beginning} {unit}
                                </td>
                            </tr>
                            <tr>
                                <th>Total Incoming</th>
                                <td>
                                    {card.summary.incoming} {unit}
                                </td>
                            </tr>
                            <tr>
                                <th>Total Outgoing</th>
                                <td>
                                    {card.summary.outgoing} {unit}
                                </td>
                            </tr>
                            <tr>
                                <th>Total Damage</th>
                                <td>
                                    {card.summary.damage} {unit}
                                </td>
                            </tr>
                            <tr className="sc-balance-final">
                                <th>FINAL BALANCE</th>
                                <td>
                                    {card.summary.final} {unit}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                )}

                <div className="sc-remarks">
                    <p className="sc-section-title">Remarks</p>
                    <div className="sc-remarks-line" />
                </div>

                <table className="mrs-voucher-table sc-period-table">
                    <tbody>
                        <tr>
                            <th>WEEK</th>
                            <td>{display(card.period.week)}</td>
                            <th>MONTH</th>
                            <td>{display(card.period.month)}</td>
                            <th>YEAR</th>
                            <td>{display(card.period.year)}</td>
                        </tr>
                    </tbody>
                </table>

                <table className="mrs-voucher-table mrs-signature-table sc-signature-table">
                    <thead>
                        <tr>
                            <th>PREPARED BY</th>
                            <th>ACKNOWLEDGE BY</th>
                            <th>APPROVED BY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <Signature role="Warehouseman" />
                            </td>
                            <td>
                                <Signature role="PIC" />
                            </td>
                            <td>
                                <Signature role="Name / Position" withPosition />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </div>
    );
}
