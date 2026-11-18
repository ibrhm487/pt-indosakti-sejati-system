import { PageHeader } from '@/Components/AppBits';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Field,
    Input,
    Select,
    Textarea,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDocumentNumberPreview } from '@/lib/document-number';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, PackageMinus, Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

export type OmfLot = {
    lot_code: string;
    quantity: number;
    expired_date: string | null;
};

export type OmfProduct = {
    id: number;
    name: string;
    sku: string;
    unit: string;
    brand: string | null;
    brand_code: string | null;
    available: number;
    lots: OmfLot[];
};

export type OmfBrand = { id: number; name: string; code: string | null };

type Row = {
    product_id: string;
    lot_code: string;
    expired_date: string;
    quantity: string;
    remark: string;
};

type FormData = {
    omf_number: string;
    omf_number_manual: boolean;
    issued_date: string;
    brand_id: string;
    department: string;
    recipient: string;
    notes: string;
    received_by_name: string;
    received_by_role: string;
    approved_by_name: string;
    approved_by_role: string;
    operational_manager_by_name: string;
    operational_manager_by_role: string;
    items: Row[];
};

const emptyRow: Row = {
    product_id: '',
    lot_code: '',
    expired_date: '',
    quantity: '',
    remark: '',
};

export default function OmfForm({
    omfNumber,
    today,
    products,
    brands,
}: {
    omfNumber: string;
    today: string;
    products: OmfProduct[];
    brands: OmfBrand[];
}) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        omf_number: omfNumber,
        omf_number_manual: false,
        issued_date: today,
        brand_id: '',
        department: '',
        recipient: '',
        notes: '',
        received_by_name: '',
        received_by_role: 'Warehouse',
        approved_by_name: '',
        approved_by_role: 'PIC',
        operational_manager_by_name: '',
        operational_manager_by_role: 'Forwarder',
        items: [{ ...emptyRow }],
    });

    const productsById = useMemo(
        () => new Map(products.map((product) => [String(product.id), product])),
        [products],
    );

    const updateRow = (index: number, patch: Partial<Row>) => {
        setData(
            'items',
            data.items.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...patch } : row,
            ),
        );
    };

    const chooseProduct = (index: number, productId: string) => {
        const product = productsById.get(productId);
        const firstLot = product?.lots[0];

        updateRow(index, {
            product_id: productId,
            lot_code: firstLot?.lot_code ?? '',
            expired_date: firstLot?.expired_date ?? '',
            quantity: '',
        });
    };

    const chooseLot = (index: number, lotCode: string) => {
        const row = data.items[index];
        const lot = productsById.get(row.product_id)?.lots.find(
            (candidate) => candidate.lot_code === lotCode,
        );

        updateRow(index, {
            lot_code: lotCode,
            expired_date: lot?.expired_date ?? '',
        });
    };

    const chooseBrand = (brandId: string) => {
        const brand = brands.find((candidate) => String(candidate.id) === brandId);
        const patch: Partial<FormData> = { brand_id: brandId };

        if (!data.omf_number_manual) {
            patch.omf_number = formatDocumentNumberPreview(
                data.omf_number,
                data.issued_date,
                brand?.code ?? null,
            );
        }

        setData({ ...data, ...patch });
    };

    const submit = () => {
        post(route('warehouse.omf.store'));
    };

    return (
        <AppLayout title="Buat OMF">
            <Head title="Buat OMF" />
            <PageHeader
                title="Out Material Form"
                subtitle="Catat barang keluar gudang dan potong stok per lot"
            >
                <Link href={route('warehouse.omf.index')}>
                    <Button variant="outline">
                        <ArrowLeft className="h-4 w-4" /> Kembali
                    </Button>
                </Link>
            </PageHeader>

            <div className="space-y-5">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <PackageMinus className="h-4 w-4 text-primary" />
                            Informasi Dokumen
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="No. OMF" required error={errors.omf_number} className="mb-0">
                            <Input
                                value={data.omf_number}
                                onChange={(event) => {
                                    setData({
                                        ...data,
                                        omf_number: event.target.value,
                                        omf_number_manual: true,
                                    });
                                }}
                                placeholder="JKT/0811/MLO/01"
                            />
                            {data.omf_number_manual && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="mt-1"
                                    onClick={() =>
                                        setData({
                                            ...data,
                                            omf_number_manual: false,
                                            omf_number: formatDocumentNumberPreview(
                                                data.omf_number,
                                                data.issued_date,
                                                brands.find((brand) => String(brand.id) === data.brand_id)?.code ?? null,
                                            ),
                                        })
                                    }
                                >
                                    Gunakan nomor otomatis
                                </Button>
                            )}
                            <p className="mt-1 text-xs text-muted">
                                Format: JKT/DDMM/KODEBRAND/NN. Nomor otomatis diverifikasi saat disimpan.
                            </p>
                        </Field>
                        <Field label="Tanggal Keluar" required error={errors.issued_date} className="mb-0">
                            <Input
                                type="date"
                                value={data.issued_date}
                                onChange={(event) => {
                                    const issuedDate = event.target.value;
                                    setData({
                                        ...data,
                                        issued_date: issuedDate,
                                        ...(!data.omf_number_manual
                                            ? {
                                                  omf_number: formatDocumentNumberPreview(
                                                      data.omf_number,
                                                      issuedDate,
                                                      brands.find((brand) => String(brand.id) === data.brand_id)?.code ?? null,
                                                  ),
                                              }
                                            : {}),
                                    });
                                }}
                            />
                        </Field>
                        <Field label="Brand" error={errors.brand_id} className="mb-0">
                            <Select value={data.brand_id} onChange={(event) => chooseBrand(event.target.value)}>
                                <option value="">Tanpa brand</option>
                                {brands.map((brand) => (
                                    <option key={brand.id} value={brand.id}>
                                        {brand.name}
                                        {brand.code ? ` (${brand.code})` : ''}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Departemen / Tujuan" error={errors.department} className="mb-0">
                            <Input
                                value={data.department}
                                onChange={(event) => setData('department', event.target.value)}
                                placeholder="Contoh: Marketing"
                            />
                        </Field>
                        <Field label="Penerima" error={errors.recipient} className="mb-0">
                            <Input
                                value={data.recipient}
                                onChange={(event) => setData('recipient', event.target.value)}
                                placeholder="Nama penerima barang"
                            />
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <CardTitle>Daftar Barang Keluar</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setData('items', [...data.items, { ...emptyRow }])}
                            >
                                <Plus className="h-4 w-4" /> Tambah Baris
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {errors.items && (
                            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
                                {errors.items}
                            </p>
                        )}
                        {data.items.map((row, index) => {
                            const product = productsById.get(row.product_id);
                            const lot = product?.lots.find(
                                (candidate) => candidate.lot_code === row.lot_code,
                            );
                            const quantityError = (errors as Record<string, string>)[
                                `items.${index}.quantity`
                            ];
                            const lotError = (errors as Record<string, string>)[
                                `items.${index}.lot_code`
                            ];

                            return (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border border-border p-3 lg:grid-cols-[2fr_1.4fr_0.8fr_1.4fr_auto]"
                                >
                                    <Field label="Barang" error={(errors as Record<string, string>)[`items.${index}.product_id`]} className="mb-0">
                                        <Select
                                            value={row.product_id}
                                            onChange={(event) => chooseProduct(index, event.target.value)}
                                        >
                                            <option value="">Pilih barang</option>
                                            {products.map((option) => (
                                                <option key={option.id} value={option.id}>
                                                    {option.name} · {option.sku}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Field label="Lot" error={lotError} className="mb-0">
                                        <Select
                                            value={row.lot_code}
                                            onChange={(event) => chooseLot(index, event.target.value)}
                                            disabled={!product}
                                        >
                                            <option value="">Pilih lot</option>
                                            {product?.lots.map((option) => (
                                                <option key={option.lot_code} value={option.lot_code}>
                                                    {option.lot_code} · sisa {option.quantity}
                                                </option>
                                            ))}
                                        </Select>
                                        {lot && (
                                            <p className="mt-1 text-xs text-muted">
                                                Tersedia {lot.quantity} {product?.unit}
                                                {lot.expired_date ? ` · exp ${lot.expired_date}` : ''}
                                            </p>
                                        )}
                                    </Field>
                                    <Field label="Qty" error={quantityError} className="mb-0">
                                        <Input
                                            type="number"
                                            min={1}
                                            max={lot?.quantity}
                                            value={row.quantity}
                                            onChange={(event) =>
                                                updateRow(index, { quantity: event.target.value })
                                            }
                                        />
                                    </Field>
                                    <Field label="Keterangan" className="mb-0">
                                        <Input
                                            value={row.remark}
                                            onChange={(event) =>
                                                updateRow(index, { remark: event.target.value })
                                            }
                                            placeholder="Contoh: PT INDOMAR VISUAL MANADO"
                                        />
                                    </Field>
                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setData(
                                                    'items',
                                                    data.items.filter(
                                                        (_, rowIndex) => rowIndex !== index,
                                                    ),
                                                )
                                            }
                                            disabled={data.items.length === 1}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Catatan & Tanda Tangan</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Field label="Catatan" error={errors.notes} className="mb-0">
                            <Textarea
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                rows={3}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="space-y-3 rounded-lg border border-border p-3">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted">
                                    Received By
                                </p>
                                <Field label="Nama" className="mb-0">
                                    <Input
                                        value={data.received_by_name}
                                        onChange={(event) =>
                                            setData('received_by_name', event.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Jabatan" className="mb-0">
                                    <Input
                                        value={data.received_by_role}
                                        onChange={(event) =>
                                            setData('received_by_role', event.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                            <div className="space-y-3 rounded-lg border border-border p-3">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted">
                                    Approved By (PIC)
                                </p>
                                <Field label="Nama" className="mb-0">
                                    <Input
                                        value={data.approved_by_name}
                                        onChange={(event) =>
                                            setData('approved_by_name', event.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Jabatan" className="mb-0">
                                    <Input
                                        value={data.approved_by_role}
                                        onChange={(event) =>
                                            setData('approved_by_role', event.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                            <div className="space-y-3 rounded-lg border border-border p-3">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted">
                                    Approved By (Forwarder)
                                </p>
                                <Field label="Nama" className="mb-0">
                                    <Input
                                        value={data.operational_manager_by_name}
                                        onChange={(event) =>
                                            setData(
                                                'operational_manager_by_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Jabatan" className="mb-0">
                                    <Input
                                        value={data.operational_manager_by_role}
                                        onChange={(event) =>
                                            setData(
                                                'operational_manager_by_role',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2 pb-6">
                    <Link href={route('warehouse.omf.index')}>
                        <Button variant="outline">Batal</Button>
                    </Link>
                    <Button onClick={submit} disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan OMF'}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
