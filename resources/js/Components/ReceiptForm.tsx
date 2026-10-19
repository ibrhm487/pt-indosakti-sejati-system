import { Button, Card, CardContent, Field, Input, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, InertiaFormProps, useForm } from '@inertiajs/react';
import { Camera, History, PackagePlus, StickyNote, Truck } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';

export type ReceiptProduct = { id: number; name: string; sku: string; unit: string };

export type ReceiptFormData = {
    product_id: string;
    quantity: string;
    supplier: string;
    received_date: string;
    notes: string;
    photo: File | null;
};

export const emptyReceiptForm = (): ReceiptFormData => ({
    product_id: '',
    quantity: '',
    supplier: '',
    received_date: new Date().toISOString().slice(0, 10),
    notes: '',
    photo: null,
});

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
        <div className="space-y-3">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
                <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-page text-muted">
                    {icon}
                </span>
                {title}
            </div>
            {children}
        </div>
    );
}

export function ReceiptFormFields({
    form,
    products,
}: {
    form: InertiaFormProps<ReceiptFormData>;
    products: ReceiptProduct[];
}) {
    const { data, setData, errors } = form;

    return (
        <div className="space-y-6">
            <Section icon={<PackagePlus className="h-3.5 w-3.5" />} title="Informasi Penerimaan">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Produk" error={errors.product_id} className="sm:col-span-2">
                        <Select value={data.product_id} onChange={(e) => setData('product_id', e.target.value)}>
                            <option value="">- Pilih Produk -</option>
                            {products.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name} ({p.sku})
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Jumlah" error={errors.quantity}>
                        <Input
                            type="number"
                            min={1}
                            value={data.quantity}
                            onChange={(e) => setData('quantity', e.target.value)}
                        />
                    </Field>
                    <Field label="Tanggal Terima" error={errors.received_date}>
                        <Input
                            type="date"
                            value={data.received_date}
                            onChange={(e) => setData('received_date', e.target.value)}
                        />
                    </Field>
                </div>
            </Section>

            <Section icon={<Truck className="h-3.5 w-3.5" />} title="Supplier">
                <Field label="Nama Supplier" error={errors.supplier}>
                    <Input value={data.supplier} onChange={(e) => setData('supplier', e.target.value)} />
                </Field>
            </Section>

            <Section icon={<StickyNote className="h-3.5 w-3.5" />} title="Catatan">
                <Field error={errors.notes}>
                    <Textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="Catatan tambahan (opsional)"
                    />
                </Field>
            </Section>

            <Section icon={<Camera className="h-3.5 w-3.5" />} title="Foto Bukti">
                <Field label="Foto Barang Masuk" error={errors.photo}>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(e) => setData('photo', e.target.files?.[0] ?? null)}
                        className="block w-full cursor-pointer rounded-lg border border-border bg-surface text-sm text-text file:mr-3 file:rounded-lg file:border-0 file:bg-page file:px-3 file:py-2 file:text-text"
                    />
                </Field>
                <p className="text-xs text-muted">JPG/PNG/WebP, maks. 4 MB. Foto tersimpan privat dan hanya dapat diakses berwenang.</p>
            </Section>
        </div>
    );
}

export type RecentMovement = {
    id: number;
    product: string | null;
    type: string;
    type_label: string;
    quantity: number;
    balance_after: number;
    at: string | null;
};

export default function ReceiptForm({
    products,
    recentMovements = [],
}: {
    products: ReceiptProduct[];
    recentMovements?: RecentMovement[];
}) {
    const form = useForm<ReceiptFormData>(emptyReceiptForm());

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('warehouse.receipts.store'), { forceFormData: true });
    };

    return (
        <AppLayout title="Catat Barang Masuk">
            <Head title="Catat Barang Masuk" />
            <div className="grid gap-6 lg:grid-cols-3">
                <form onSubmit={submit} className="lg:col-span-2">
                    <Card className="mb-6">
                        <CardContent className="pt-6">
                            <ReceiptFormFields form={form} products={products} />
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-2">
                        <Button type="submit" variant="primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                        <Link href={route('warehouse.receipts.index')}>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </Link>
                    </div>
                </form>

                <aside className="h-fit">
                    <Card>
                        <CardContent className="pt-6">
                            <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
                                <History className="h-3.5 w-3.5" /> Transaksi Terakhir
                            </p>
                            {recentMovements.length === 0 ? (
                                <p className="mt-3 text-sm text-muted">
                                    Belum ada pergerakan stok tercatat.
                                </p>
                            ) : (
                                <div className="mt-3 overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead>
                                            <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
                                                <th className="py-2 pr-3 font-medium">Barang</th>
                                                <th className="py-2 pr-3 font-medium">Jenis</th>
                                                <th className="py-2 pr-3 text-right font-medium">Qty</th>
                                                <th className="py-2 text-right font-medium">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {recentMovements.map((m) => (
                                                <tr key={m.id} className="border-b border-border last:border-0">
                                                    <td className="py-2 pr-3">
                                                        <p className="font-medium text-text">{m.product ?? '-'}</p>
                                                        <p className="text-xs text-muted">{m.at}</p>
                                                    </td>
                                                    <td className="py-2 pr-3 text-text">
                                                        {m.type_label}
                                                    </td>
                                                    <td
                                                        className={`py-2 pr-3 text-right ${
                                                            m.type === 'stock_out' || m.type === 'damage'
                                                                ? 'text-danger'
                                                                : 'text-success'
                                                        }`}
                                                    >
                                                        {m.type === 'stock_out' || m.type === 'damage' ? '-' : '+'}
                                                        {m.quantity}
                                                    </td>
                                                    <td className="py-2 text-right font-medium text-text">
                                                        {m.balance_after}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </AppLayout>
    );
}
