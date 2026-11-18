import { Button, Card, CardContent, Field, Input, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDocumentNumberPreview } from '@/lib/document-number';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, History, PackagePlus, PenLine, Plus, Trash2, Truck } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

export type ReceiptLot = {
    lot_code: string;
    quantity: number;
    expired_date: string | null;
};
export type ReceiptProduct = {
    id: number;
    name: string;
    sku: string;
    unit: string;
    brand_id: number | null;
    brand_code: string | null;
    lots: ReceiptLot[];
};
export type RecentMovement = {
    id: number;
    product: string | null;
    type_label: string;
    quantity: number;
    balance_after: number;
    at: string | null;
};

type ReceiptItemData = {
    product_id: string;
    sku: string;
    item_name: string;
    lot_code: string;
    description: string;
    received_quantity: string;
    uom: string;
    expired_date: string;
    status: string;
    remark: string;
};

type ReceiptSlipFormData = {
    mrs_number: string;
    mrs_number_manual: boolean;
    receipt_type: string;
    do_number: string;
    shipper: string;
    supplier: string;
    document_status: string;
    received_date: string;
    release_date: string;
    release_time: string;
    authorized_by: string;
    quantity_released: string;
    quantity_assurance: string;
    received_by_name: string;
    received_by_role: string;
    approved_by_name: string;
    approved_by_role: string;
    operational_manager_by_name: string;
    operational_manager_by_role: string;
    notes: string;
    items: ReceiptItemData[];
};

function emptyItem(): ReceiptItemData {
    return {
        product_id: '',
        sku: '',
        item_name: '',
        lot_code: '',
        description: '',
        received_quantity: '',
        uom: '',
        expired_date: '',
        status: '',
        remark: '',
    };
}

function Section({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4">
            <div className="flex items-center gap-2 border-b border-border pb-3 text-sm font-semibold text-text">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-soft text-primary">
                    {icon}
                </span>
                {title}
            </div>
            {children}
        </section>
    );
}

export default function ReceiptForm({
    products,
    mrsNumber,
    today,
    recentMovements = [],
}: {
    products: ReceiptProduct[];
    mrsNumber: string;
    today: string;
    recentMovements?: RecentMovement[];
}) {
    const form = useForm<ReceiptSlipFormData>({
        mrs_number: mrsNumber,
        mrs_number_manual: false,
        receipt_type: '',
        do_number: '',
        shipper: '',
        supplier: '',
        document_status: 'GOOD',
        received_date: today,
        release_date: '',
        release_time: '',
        authorized_by: '',
        quantity_released: '',
        quantity_assurance: '',
        received_by_name: '',
        received_by_role: 'Warehouse',
        approved_by_name: '',
        approved_by_role: 'PIC',
        operational_manager_by_name: '',
        operational_manager_by_role: 'Forwarder',
        notes: '',
        items: [emptyItem()],
    });

    const updateItem = (index: number, patch: Partial<ReceiptItemData>) => {
        form.setData(
            'items',
            form.data.items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, ...patch } : item,
            ),
        );
    };

    const [customType, setCustomType] = useState(false);

    const stockCardTypes = ['Free Goods/FGR', 'Cups/Spoon', 'Premium/Gimmick', 'POP/POSM'];

    const chooseType = (value: string) => {
        if (value === 'Other') {
            setCustomType(true);
            form.setData('receipt_type', '');
            return;
        }

        setCustomType(false);
        form.setData('receipt_type', value);
    };

    const syncNumberFromBrand = (brandCode: string | null, date = form.data.received_date) => {
        if (form.data.mrs_number_manual) {
            return;
        }

        form.setData(
            'mrs_number',
            formatDocumentNumberPreview(form.data.mrs_number, date, brandCode),
        );
    };

    const itemError = (index: number, field: keyof ReceiptItemData): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`items.${index}.${field}`];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('warehouse.receipts.store'));
    };

    return (
        <AppLayout title="Buat MRS">
            <Head title="Material Receipt Slip Baru" />
            <div className="mb-5">
                <Link
                    href={route('warehouse.receipts.index')}
                    className="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-text"
                >
                    <ArrowLeft className="h-4 w-4" /> Kembali ke Barang Masuk
                </Link>
                <h1 className="mt-3 text-2xl font-semibold tracking-tight text-text">Material Receipt Slip</h1>
                <p className="mt-1 text-sm text-muted">
                    Catat dokumen dan barang yang diterima. Saldo stok bertambah sesuai jumlah diterima setelah disimpan.
                </p>
            </div>

            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardContent className="space-y-7 p-5 sm:p-7">
                            <Section icon={<Truck className="h-4 w-4" />} title="Informasi Penerimaan">
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <Field label="No. MRS" error={form.errors.mrs_number}>
                                        <Input
                                            value={form.data.mrs_number}
                                            onChange={(event) => {
                                                form.setData({
                                                    ...form.data,
                                                    mrs_number: event.target.value,
                                                    mrs_number_manual: true,
                                                });
                                            }}
                                            maxLength={60}
                                            required
                                        />
                                        {form.data.mrs_number_manual && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="mt-1"
                                                onClick={() => {
                                                    const firstProduct = products.find(
                                                        (product) => String(product.id) === form.data.items[0]?.product_id,
                                                    );
                                                    form.setData({
                                                        ...form.data,
                                                        mrs_number_manual: false,
                                                        mrs_number: formatDocumentNumberPreview(
                                                            form.data.mrs_number,
                                                            form.data.received_date,
                                                            firstProduct?.brand_code ?? null,
                                                        ),
                                                    });
                                                }}
                                            >
                                                Gunakan nomor otomatis
                                            </Button>
                                        )}
                                        <p className="mt-1 text-xs text-muted">
                                            Format: JKT/DDMM/KODEBRAND/NN. Brand mengikuti produk pertama.
                                        </p>
                                    </Field>
                                    <Field label="Type" error={form.errors.receipt_type}>
                                        <Select
                                            value={customType ? 'Other' : form.data.receipt_type}
                                            onChange={(event) => chooseType(event.target.value)}
                                            required={!customType}
                                        >
                                            <option value="">Pilih tipe penerimaan…</option>
                                            {stockCardTypes.map((type) => (
                                                <option key={type} value={type}>
                                                    {type}
                                                </option>
                                            ))}
                                            <option value="Other">Other</option>
                                        </Select>
                                        {customType && (
                                            <Input
                                                className="mt-2"
                                                value={form.data.receipt_type}
                                                onChange={(event) =>
                                                    form.setData('receipt_type', event.target.value)
                                                }
                                                placeholder="Tulis tipe penerimaan"
                                                maxLength={100}
                                                required
                                            />
                                        )}
                                    </Field>
                                    <Field label="DO Number" error={form.errors.do_number}>
                                        <Input
                                            value={form.data.do_number}
                                            onChange={(event) => form.setData('do_number', event.target.value)}
                                            required
                                        />
                                    </Field>
                                    <Field label="Shipper / Courier" error={form.errors.shipper}>
                                        <Input
                                            value={form.data.shipper}
                                            onChange={(event) => form.setData('shipper', event.target.value)}
                                        />
                                    </Field>
                                    <Field label="Supplier" error={form.errors.supplier}>
                                        <Input
                                            value={form.data.supplier}
                                            onChange={(event) => form.setData('supplier', event.target.value)}
                                            placeholder="Contoh: PT Indofood"
                                        />
                                    </Field>
                                    <Field label="Document Status" error={form.errors.document_status}>
                                        <Select
                                            value={form.data.document_status}
                                            onChange={(event) =>
                                                form.setData('document_status', event.target.value)
                                            }
                                        >
                                            <option value="GOOD">Good</option>
                                            <option value="HOLD">Hold</option>
                                            <option value="DAMAGED">Damaged</option>
                                        </Select>
                                    </Field>
                                    <Field label="Date" error={form.errors.received_date}>
                                        <Input
                                            type="date"
                                            value={form.data.received_date}
                                            onChange={(event) => {
                                                const date = event.target.value;
                                                form.setData('received_date', date);
                                                const firstProduct = products.find(
                                                    (product) => String(product.id) === form.data.items[0]?.product_id,
                                                );
                                                syncNumberFromBrand(firstProduct?.brand_code ?? null, date);
                                            }}
                                            required
                                        />
                                    </Field>
                                </div>
                            </Section>

                            <Section icon={<PackagePlus className="h-4 w-4" />} title="Daftar Barang">
                                {form.errors.items && (
                                    <p className="rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">
                                        {form.errors.items}
                                    </p>
                                )}
                                <div className="space-y-4">
                                    {form.data.items.map((item, index) => {
                                        const firstProduct = products.find(
                                            (product) => String(product.id) === form.data.items[0]?.product_id,
                                        );
                                        const availableProducts = index === 0 || !firstProduct
                                            ? products
                                            : products.filter((product) => product.brand_id === firstProduct.brand_id);

                                        return (
                                            <div key={index} className="rounded-xl border border-border p-4">
                                                <div className="mb-4 flex items-center justify-between">
                                                    <p className="text-sm font-semibold text-text">Barang {index + 1}</p>
                                                    {form.data.items.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                form.setData(
                                                                    'items',
                                                                    form.data.items.filter((_, itemIndex) => itemIndex !== index),
                                                                )
                                                            }
                                                            aria-label={`Hapus barang ${index + 1}`}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                                    <Field label="Pilih Produk" error={itemError(index, 'product_id')} className="sm:col-span-2">
                                                        <Select
                                                            value={item.product_id}
                                                            onChange={(event) => {
                                                                const product = products.find(
                                                                    (option) => String(option.id) === event.target.value,
                                                                );
                                                                const firstLot = product?.lots[0];
                                                                updateItem(index, {
                                                                    product_id: event.target.value,
                                                                    sku: product?.sku ?? '',
                                                                    item_name: product?.name ?? '',
                                                                    uom: product?.unit ?? '',
                                                                    lot_code: firstLot?.lot_code ?? '',
                                                                    expired_date: firstLot?.expired_date ?? '',
                                                                });

                                                                if (index === 0) {
                                                                    syncNumberFromBrand(
                                                                        product?.brand_code ?? null,
                                                                    );
                                                                }
                                                            }}
                                                            required
                                                        >
                                                            <option value="">Pilih produk gudang</option>
                                                            {availableProducts.map((product) => (
                                                                <option key={product.id} value={product.id}>
                                                                    {product.name} ({product.sku})
                                                                </option>
                                                            ))}
                                                        </Select>
                                                    </Field>
                                                    <Field label="SKU" error={itemError(index, 'sku')}>
                                                        <Input
                                                            value={item.sku}
                                                            onChange={(event) => updateItem(index, { sku: event.target.value })}
                                                            required
                                                        />
                                                    </Field>
                                                    <Field label="Item Name" error={itemError(index, 'item_name')}>
                                                        <Input
                                                            value={item.item_name}
                                                            onChange={(event) => updateItem(index, { item_name: event.target.value })}
                                                            required
                                                        />
                                                    </Field>
                                                    <Field label="Lot Code" error={itemError(index, 'lot_code')}>
                                                        <Input
                                                            value={item.lot_code}
                                                            onChange={(event) => {
                                                                const product = products.find(
                                                                    (option) => String(option.id) === item.product_id,
                                                                );
                                                                const lot = product?.lots.find(
                                                                    (candidate) => candidate.lot_code === event.target.value,
                                                                );
                                                                updateItem(index, {
                                                                    lot_code: event.target.value,
                                                                    ...(lot
                                                                        ? { expired_date: lot.expired_date ?? '' }
                                                                        : {}),
                                                                });
                                                            }}
                                                            list={`lot-options-${index}`}
                                                            placeholder="Pilih atau isi lot code"
                                                        />
                                                        <datalist id={`lot-options-${index}`}>
                                                            {(products.find(
                                                                (option) => String(option.id) === item.product_id,
                                                            )?.lots ?? []).map((lot) => (
                                                                <option key={lot.lot_code} value={lot.lot_code}>
                                                                    {lot.lot_code} · sisa {lot.quantity}
                                                                </option>
                                                            ))}
                                                        </datalist>
                                                    </Field>
                                                    <Field label="Description" error={itemError(index, 'description')}>
                                                        <Input
                                                            value={item.description}
                                                            onChange={(event) => updateItem(index, { description: event.target.value })}
                                                        />
                                                    </Field>
                                                    <Field label="Received Qty" error={itemError(index, 'received_quantity')}>
                                                        <Input
                                                            type="number"
                                                            min={1}
                                                            value={item.received_quantity}
                                                            onChange={(event) =>
                                                                updateItem(index, { received_quantity: event.target.value })
                                                            }
                                                            required
                                                        />
                                                    </Field>
                                                    <Field label="UOM" error={itemError(index, 'uom')}>
                                                        <Input
                                                            value={item.uom}
                                                            onChange={(event) => updateItem(index, { uom: event.target.value })}
                                                            required
                                                        />
                                                    </Field>
                                                    <Field label="Expired Date" error={itemError(index, 'expired_date')}>
                                                        <Input
                                                            type="date"
                                                            value={item.expired_date}
                                                            onChange={(event) => updateItem(index, { expired_date: event.target.value })}
                                                        />
                                                    </Field>
                                                    <Field label="Status" error={itemError(index, 'status')}>
                                                        <Input
                                                            value={item.status}
                                                            onChange={(event) => updateItem(index, { status: event.target.value })}
                                                            placeholder="Contoh: Good"
                                                        />
                                                    </Field>
                                                    <Field label="Remark" error={itemError(index, 'remark')} className="sm:col-span-2 xl:col-span-4">
                                                        <Input
                                                            value={item.remark}
                                                            onChange={(event) => updateItem(index, { remark: event.target.value })}
                                                        />
                                                    </Field>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => form.setData('items', [...form.data.items, emptyItem()])}
                                >
                                    <Plus className="h-4 w-4" /> Tambah Barang
                                </Button>
                            </Section>

                            <Section icon={<Truck className="h-4 w-4" />} title="Rilis & Otorisasi">
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <Field
                                        label="Date Released"
                                        error={form.errors.release_date ?? form.errors.release_time}
                                    >
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Input
                                                type="date"
                                                aria-label="Tanggal Date Released"
                                                value={form.data.release_date}
                                                onChange={(event) => form.setData('release_date', event.target.value)}
                                            />
                                            <Input
                                                type="time"
                                                aria-label="Jam Date Released"
                                                value={form.data.release_time}
                                                onChange={(event) => form.setData('release_time', event.target.value)}
                                            />
                                        </div>
                                    </Field>
                                    <Field label="Authorized By" error={form.errors.authorized_by}>
                                        <Input
                                            value={form.data.authorized_by}
                                            onChange={(event) => form.setData('authorized_by', event.target.value)}
                                        />
                                    </Field>
                                    <Field label="Quantity Released" error={form.errors.quantity_released}>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={form.data.quantity_released}
                                            onChange={(event) => form.setData('quantity_released', event.target.value)}
                                        />
                                    </Field>
                                    <Field label="Quantity Assurance" error={form.errors.quantity_assurance}>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={form.data.quantity_assurance}
                                            onChange={(event) => form.setData('quantity_assurance', event.target.value)}
                                        />
                                    </Field>
                                </div>
                            </Section>

                            <Section icon={<PenLine className="h-4 w-4" />} title="Tanda Tangan">
                                <p className="text-sm text-muted">
                                    Tanggal dan jam pencatatan otomatis dicantumkan di bawah jabatan pada lembar MRS.
                                </p>
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {([
                                        ['received_by_name', 'received_by_role', 'Received By'],
                                        ['approved_by_name', 'approved_by_role', 'Approved By (PIC)'],
                                        [
                                            'operational_manager_by_name',
                                            'operational_manager_by_role',
                                            'Approved By (Forwarder)',
                                        ],
                                    ] as const).map(([nameKey, roleKey, title]) => (
                                        <div key={nameKey} className="space-y-3 rounded-xl border border-border p-4">
                                            <p className="text-sm font-semibold text-text">{title}</p>
                                            <Field label="Nama" error={form.errors[nameKey]}>
                                                <Input
                                                    value={form.data[nameKey]}
                                                    onChange={(event) => form.setData(nameKey, event.target.value)}
                                                />
                                            </Field>
                                            <Field label="Jabatan / Keterangan" error={form.errors[roleKey]}>
                                                <Input
                                                    value={form.data[roleKey]}
                                                    onChange={(event) => form.setData(roleKey, event.target.value)}
                                                />
                                            </Field>
                                        </div>
                                    ))}
                                </div>
                            </Section>

                            <Field label="Catatan" error={form.errors.notes}>
                                <Textarea
                                    value={form.data.notes}
                                    onChange={(event) => form.setData('notes', event.target.value)}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan MRS…' : 'Simpan MRS'}
                        </Button>
                        <Link href={route('warehouse.receipts.index')}>
                            <Button type="button" variant="outline">Batal</Button>
                        </Link>
                    </div>
                </form>

                <aside className="h-fit">
                    <Card>
                        <CardContent className="space-y-4 p-5">
                            <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
                                <History className="h-4 w-4" /> Transaksi Stok Terakhir
                            </p>
                            {recentMovements.length === 0 ? (
                                <p className="text-sm text-muted">Belum ada pergerakan stok tercatat.</p>
                            ) : (
                                <div className="space-y-3">
                                    {recentMovements.map((movement) => (
                                        <div key={movement.id} className="flex items-center justify-between gap-3 border-b border-border pb-3 last:border-0">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-text">{movement.product ?? '-'}</p>
                                                <p className="text-xs text-muted">{movement.type_label} · {movement.at}</p>
                                            </div>
                                            <p className="shrink-0 text-sm font-semibold text-success">+{movement.quantity}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </AppLayout>
    );
}
