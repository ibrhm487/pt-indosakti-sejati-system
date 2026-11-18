import { EmptyState, FilterCard, PageHeader } from '@/Components/AppBits';
import { Button, Input } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

type BrandOption = {
    id: number;
    name: string;
};

type MiosLot = {
    lot_code: string | null;
    expired_date: string | null;
    beginning_stock: number;
    incoming_new: number;
    incoming_return: number;
    outgoing_mrv: number;
    outgoing_omf: number;
    outgoing_damage: number;
    adjustments: number;
    balance: number;
};

type MiosRow = {
    product_id: number;
    description: string;
    item_number: string;
    sku: string;
    uom: string;
    units_per_case: number | null;
    balance_pack_units: number;
    balance_cases: number | null;
    lots: MiosLot[];
    beginning_stock: number;
    incoming_new: number;
    incoming_return: number;
    outgoing_mrv: number;
    outgoing_omf: number;
    outgoing_damage: number;
    adjustments: number;
    balance: number;
};

type MiosFilters = {
    brand_id: number | null;
    start_date: string;
    end_date: string;
};

function TableHeader({
    children,
    className = '',
    rowSpan,
    colSpan,
}: {
    children: ReactNode;
    className?: string;
    rowSpan?: number;
    colSpan?: number;
}) {
    return (
        <th
            rowSpan={rowSpan}
            colSpan={colSpan}
            className={`border border-slate-500 px-2 py-2 text-center align-middle text-xs font-semibold dark:border-slate-600 ${className}`}
        >
            {children}
        </th>
    );
}

function NumberCell({ children, className = '' }: { children: ReactNode; className?: string }) {
    return (
        <td
            className={`border border-border px-2 py-2 text-right tabular-nums ${className}`}
        >
            {children}
        </td>
    );
}

export default function Mios({
    brands,
    selectedBrandId,
    selectedBrandName,
    filters,
    rows,
}: {
    brands: BrandOption[];
    selectedBrandId: number | null;
    selectedBrandName: string | null;
    filters: MiosFilters;
    rows: MiosRow[];
}) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [exporting, setExporting] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const applyPeriod = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('warehouse.inventory.mios'),
            { brand_id: selectedBrandId, start_date: startDate, end_date: endDate },
            { preserveState: true, preserveScroll: true },
        );
    };

    const exportTableImage = async () => {
        if (rows.length === 0) return;

        setExporting(true);
        setExportError(null);

        try {
            const columnWidths = [48, 220, 130, 130, 70, 190, 125, 105, 105, 105, 105, 105, 110, 120, 100, 150];
            const headers = [
                'No',
                'Description',
                'Item Number',
                'SKU',
                'UOM',
                'Lot Code · Balance',
                'Beginning Stock',
                'Incoming New',
                'Incoming Return',
                'Outgoing MRV',
                'Outgoing OMF',
                'Outgoing Damage',
                'Adjustment',
                'Pos/Box/Tin',
                'Cases',
                'Expired Date',
            ];
            const titleHeight = 68;
            const topHeaderHeight = 38;
            const subHeaderHeight = 34;
            const headerHeight = topHeaderHeight + subHeaderHeight;
            const rowHeights = rows.map((row) => Math.max(36, (row.lots.length * 18) + 10));
            const canvasWidth = columnWidths.reduce((total, width) => total + width, 0);
            const canvasHeight = titleHeight + headerHeight + rowHeights.reduce((total, height) => total + height, 0);

            if (canvasWidth > 32767 || canvasHeight > 32767) {
                throw new Error('Jumlah baris melebihi batas ukuran satu gambar PNG.');
            }

            const canvas = document.createElement('canvas');
            canvas.width = canvasWidth;
            canvas.height = canvasHeight;
            const context = canvas.getContext('2d');

            if (!context) {
                throw new Error('Browser tidak dapat membuat canvas gambar.');
            }

            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvasWidth, canvasHeight);
            context.fillStyle = '#0f172a';
            context.font = 'bold 18px Arial, sans-serif';
            context.textBaseline = 'middle';
            context.fillText(
                `MIOS — ${selectedBrandName ?? 'Semua Brand'} · ${filters.start_date} s.d. ${filters.end_date}`,
                16,
                titleHeight / 2,
            );

            const drawCell = (
                text: string,
                x: number,
                y: number,
                width: number,
                height: number,
                options: { fill: string; color: string; align?: CanvasTextAlign; bold?: boolean },
            ) => {
                context.fillStyle = options.fill;
                context.fillRect(x, y, width, height);
                context.strokeStyle = '#94a3b8';
                context.strokeRect(x, y, width, height);
                context.fillStyle = options.color;
                context.font = `${options.bold ? 'bold ' : ''}12px Arial, sans-serif`;
                context.textAlign = options.align ?? 'left';
                context.textBaseline = 'middle';

                const padding = 8;
                const textX = options.align === 'right'
                    ? x + width - padding
                    : options.align === 'center'
                        ? x + width / 2
                        : x + padding;
                const maxWidth = width - (padding * 2);
                const lineHeight = 14;
                const maxLines = Math.max(1, Math.floor((height - 6) / lineHeight));
                const lines = text.split('\n').slice(0, maxLines);
                const firstLineY = y + ((height - (lines.length * lineHeight)) / 2) + (lineHeight / 2);

                lines.forEach((line, lineIndex) => {
                    let displayText = line;

                    while (displayText.length > 1 && context.measureText(displayText).width > maxWidth) {
                        displayText = `${displayText.slice(0, -2)}…`;
                    }

                    context.fillText(displayText, textX, firstLineY + (lineIndex * lineHeight), maxWidth);
                });
            };

            let x = 0;
            headers.forEach((header, index) => {
                if (index === 13) {
                    drawCell('Balance Stock', x, titleHeight, columnWidths[13] + columnWidths[14], topHeaderHeight, {
                        fill: '#bfdbfe',
                        color: '#0f172a',
                        align: 'center',
                        bold: true,
                    });
                    drawCell(header, x, titleHeight + topHeaderHeight, columnWidths[index], subHeaderHeight, {
                        fill: '#dbeafe',
                        color: '#0f172a',
                        align: 'center',
                        bold: true,
                    });
                } else if (index === 14) {
                    drawCell(header, x, titleHeight + topHeaderHeight, columnWidths[index], subHeaderHeight, {
                        fill: '#dbeafe',
                        color: '#0f172a',
                        align: 'center',
                        bold: true,
                    });
                } else {
                    drawCell(header, x, titleHeight, columnWidths[index], headerHeight, {
                        fill: '#e2e8f0',
                        color: '#0f172a',
                        align: index === 1 ? 'left' : 'center',
                        bold: true,
                    });
                }
                x += columnWidths[index];
            });

            let rowY = titleHeight + headerHeight;
            rows.forEach((row, rowIndex) => {
                const rowHeight = rowHeights[rowIndex];
                const values = [
                    String(rowIndex + 1),
                    row.description,
                    row.item_number,
                    row.sku,
                    row.uom,
                    row.lots.map((lot) => `${lot.lot_code ?? '-'} · ${formatNumber(lot.balance)} ${row.uom}`).join('\n'),
                    formatNumber(row.beginning_stock),
                    formatNumber(row.incoming_new),
                    formatNumber(row.incoming_return),
                    formatNumber(row.outgoing_mrv),
                    formatNumber(row.outgoing_omf),
                    formatNumber(row.outgoing_damage),
                    formatNumber(row.adjustments),
                    `${formatNumber(row.balance_pack_units)} ${row.uom}`,
                    row.balance_cases === null ? '—' : formatNumber(row.balance_cases),
                    row.lots.map((lot) => lot.expired_date ?? '-').join('\n'),
                ];
                let cellX = 0;

                values.forEach((value, columnIndex) => {
                    drawCell(value, cellX, rowY, columnWidths[columnIndex], rowHeight, {
                        fill: rowIndex % 2 === 0 ? '#ffffff' : '#f8fafc',
                        color: '#1e293b',
                        align: columnIndex === 1 ? 'left' : columnIndex < 6 || columnIndex === 15 ? 'center' : 'right',
                    });
                    cellX += columnWidths[columnIndex];
                });

                rowY += rowHeight;
            });

            const image = await new Promise<Blob | null>((resolve) => {
                canvas.toBlob(resolve, 'image/png');
            });

            if (!image) {
                throw new Error('Canvas tidak menghasilkan file gambar.');
            }

            const imageUrl = URL.createObjectURL(image);
            const link = document.createElement('a');
            link.href = imageUrl;
            const brandFileName = selectedBrandName?.replace(/[^\w-]+/g, '-') ?? 'All-Brands';
            link.download = `MIOS-${brandFileName}-${filters.start_date}-${filters.end_date}.png`;
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(imageUrl), 1000);
        } catch (error) {
            console.error('Gagal mengekspor tabel MIOS sebagai gambar.', error);
            setExportError('Gambar MIOS gagal dibuat. Silakan coba kembali.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <AppLayout title="MIOS">
            <Head title="MIOS - Rekap Barang per Brand" />
            <PageHeader
                title="MIOS"
                subtitle="Rekap persediaan, penerimaan, dan pengeluaran berdasarkan brand, periode, serta lot."
            >
                <Link href={route('warehouse.inventory.index')}>
                    <Button variant="outline">Kontrol Stok</Button>
                </Link>
                <Button variant="outline" onClick={exportTableImage} disabled={exporting || rows.length === 0}>
                    <Download className="h-4 w-4" />
                    {exporting ? 'Membuat gambar…' : 'Ekspor PNG'}
                </Button>
            </PageHeader>

            <div className="mb-5 flex flex-wrap gap-2" aria-label="Navigasi brand MIOS">
                <Link
                    href={route('warehouse.inventory.mios', {
                        start_date: filters.start_date,
                        end_date: filters.end_date,
                    })}
                    preserveState
                    preserveScroll
                    className={`rounded-lg border px-4 py-2 text-sm font-semibold transition-colors ${
                        selectedBrandId === null
                            ? 'border-primary bg-primary text-white'
                            : 'border-border bg-surface text-muted hover:border-primary hover:text-primary'
                    }`}
                    aria-current={selectedBrandId === null ? 'page' : undefined}
                >
                    Semua Brand
                </Link>
                {brands.map((brand) => (
                    <Link
                        key={brand.id}
                        href={route('warehouse.inventory.mios', {
                            brand_id: brand.id,
                            start_date: filters.start_date,
                            end_date: filters.end_date,
                        })}
                        preserveState
                        preserveScroll
                        className={`rounded-lg border px-4 py-2 text-sm font-semibold transition-colors ${
                            selectedBrandId === brand.id
                                ? 'border-primary bg-primary text-white'
                                : 'border-border bg-surface text-muted hover:border-primary hover:text-primary'
                        }`}
                        aria-current={selectedBrandId === brand.id ? 'page' : undefined}
                    >
                        {brand.name}
                    </Link>
                ))}
            </div>

            <FilterCard>
                <form onSubmit={applyPeriod} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid flex-1 gap-3 sm:grid-cols-2">
                        <label className="grid gap-1.5 text-sm font-medium text-text">
                            Periode mulai
                            <Input
                                type="date"
                                value={startDate}
                                onChange={(event) => setStartDate(event.target.value)}
                                required
                            />
                        </label>
                        <label className="grid gap-1.5 text-sm font-medium text-text">
                            Periode akhir
                            <Input
                                type="date"
                                value={endDate}
                                onChange={(event) => setEndDate(event.target.value)}
                                required
                            />
                        </label>
                    </div>
                    <Button type="submit">Terapkan Periode</Button>
                </form>
            </FilterCard>

            <div className="mb-3 flex flex-wrap items-end justify-between gap-2 text-sm">
                <div>
                    <p className="font-semibold text-text">{selectedBrandName ?? 'Brand belum tersedia'}</p>
                    <p className="text-muted">
                        Periode {filters.start_date} s.d. {filters.end_date}
                    </p>
                </div>
                <p className="text-xs text-muted">
                    OMF berasal dari pengeluaran non-MRV. Damage bersifat informatif karena sudah tercatat pada MRV;
                    penyesuaian ditampilkan terpisah agar saldo dapat direkonsiliasi.
                </p>
            </div>

            {rows.length === 0 ? (
                <EmptyState
                    title={selectedBrandId !== null ? 'Belum ada pergerakan pada periode ini' : 'Belum ada data'}
                    description="Pilih brand lain atau sesuaikan periode untuk melihat data MIOS."
                />
            ) : (
                <>
                    {exportError && (
                        <p role="alert" className="mb-3 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">
                            {exportError}
                        </p>
                    )}
                    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
                    <table className="min-w-[1940px] w-full border-collapse text-sm">
                        <thead>
                            <tr className="bg-slate-200 text-slate-900 dark:bg-slate-800 dark:text-slate-100">
                                <TableHeader rowSpan={2}>No</TableHeader>
                                <TableHeader rowSpan={2} className="min-w-64">Description</TableHeader>
                                <TableHeader rowSpan={2}>Item Number</TableHeader>
                                <TableHeader rowSpan={2}>SKU</TableHeader>
                                <TableHeader rowSpan={2}>UOM</TableHeader>
                                <TableHeader rowSpan={2}>Lot Code · Balance</TableHeader>
                                <TableHeader rowSpan={2}>Beginning Stock</TableHeader>
                                <TableHeader colSpan={2} className="bg-sky-200 dark:bg-sky-900">Incoming</TableHeader>
                                <TableHeader colSpan={3} className="bg-rose-100 dark:bg-rose-950">Outgoing</TableHeader>
                                <TableHeader rowSpan={2}>Adjustment</TableHeader>
                                <TableHeader colSpan={2} className="bg-blue-200 dark:bg-blue-900">
                                    Balance Stock
                                </TableHeader>
                                <TableHeader rowSpan={2}>Expired Date</TableHeader>
                            </tr>
                            <tr className="bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200">
                                <TableHeader className="bg-sky-100 dark:bg-sky-950">New</TableHeader>
                                <TableHeader className="bg-sky-100 dark:bg-sky-950">Return</TableHeader>
                                <TableHeader className="bg-rose-50 dark:bg-rose-900">MRV</TableHeader>
                                <TableHeader className="bg-rose-50 dark:bg-rose-900">OMF</TableHeader>
                                <TableHeader className="bg-rose-50 text-danger dark:bg-rose-900">Damage</TableHeader>
                                <TableHeader className="bg-blue-100 dark:bg-blue-950">Pos/Box/Tin</TableHeader>
                                <TableHeader className="bg-blue-100 dark:bg-blue-950">Cases</TableHeader>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={row.product_id}
                                    className="text-text hover:bg-slate-50 dark:hover:bg-slate-800"
                                >
                                    <td className="border border-border px-2 py-2 text-center">{index + 1}</td>
                                    <td className="border border-border px-2 py-2 font-medium">
                                        <div>{row.description}</div>
                                        <div className="text-xs font-normal text-muted">
                                            {row.lots.length} lot
                                        </div>
                                    </td>
                                    <td className="border border-border px-2 py-2 text-center">{row.item_number}</td>
                                    <td className="border border-border px-2 py-2 text-center">{row.sku}</td>
                                    <td className="border border-border px-2 py-2 text-center">{row.uom}</td>
                                    <td className="border border-border px-2 py-2 text-center text-xs leading-5">
                                        {row.lots.map((lot) => (
                                            <div key={`${lot.lot_code ?? 'no-lot'}-${lot.expired_date ?? ''}`}>
                                                {lot.lot_code ?? '-'} · {formatNumber(lot.balance)} {row.uom}
                                            </div>
                                        ))}
                                    </td>
                                    <NumberCell>{formatNumber(row.beginning_stock)}</NumberCell>
                                    <NumberCell className="bg-sky-50 dark:bg-sky-950/40">
                                        {formatNumber(row.incoming_new)}
                                    </NumberCell>
                                    <NumberCell className="bg-sky-50 dark:bg-sky-950/40">
                                        {formatNumber(row.incoming_return)}
                                    </NumberCell>
                                    <NumberCell className="bg-rose-50 dark:bg-rose-950/40">
                                        {formatNumber(row.outgoing_mrv)}
                                    </NumberCell>
                                    <NumberCell className="bg-rose-50 dark:bg-rose-950/40">
                                        {formatNumber(row.outgoing_omf)}
                                    </NumberCell>
                                    <NumberCell className="bg-rose-50 text-danger dark:bg-rose-950/40">
                                        {formatNumber(row.outgoing_damage)}
                                    </NumberCell>
                                    <NumberCell>{formatNumber(row.adjustments)}</NumberCell>
                                    <NumberCell className="bg-blue-50 font-semibold dark:bg-blue-950/40">
                                        {formatNumber(row.balance_pack_units)} {row.uom}
                                    </NumberCell>
                                    <NumberCell className="bg-blue-50 font-semibold dark:bg-blue-950/40">
                                        {row.balance_cases === null ? '—' : formatNumber(row.balance_cases)}
                                    </NumberCell>
                                    <td className="border border-border px-2 py-2 text-center text-xs leading-5">
                                        {row.lots.map((lot) => (
                                            <div key={`${lot.lot_code ?? 'no-lot'}-${lot.expired_date ?? ''}`}>
                                                {lot.expired_date ?? '-'}
                                            </div>
                                        ))}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
                </>
            )}
        </AppLayout>
    );
}
