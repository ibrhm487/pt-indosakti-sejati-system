import { Button, Card, CardContent, Field, Input, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, InertiaFormProps, useForm } from '@inertiajs/react';
import { Camera, PackagePlus, StickyNote, Truck } from 'lucide-react';
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
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
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
                        className="block w-full cursor-pointer rounded-lg border border-slate-200 bg-white text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-slate-700"
                    />
                </Field>
                <p className="text-xs text-slate-400">JPG/PNG/WebP, maks. 4 MB. Foto tersimpan privat dan hanya dapat diakses berwenang.</p>
            </Section>
        </div>
    );
}

export default function ReceiptForm({ products }: { products: ReceiptProduct[] }) {
    const form = useForm<ReceiptFormData>(emptyReceiptForm());

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('warehouse.receipts.store'), { forceFormData: true });
    };

    return (
        <AppLayout title="Catat Barang Masuk">
            <Head title="Catat Barang Masuk" />
            <form onSubmit={submit}>
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
        </AppLayout>
    );
}
