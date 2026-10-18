import { Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Select, Table, TBody, TD, TH, THead, TR, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Plus, Trash2 } from 'lucide-react';

type MrvItem = { product_id: string; quantity_requested: string };
type MrvFormData = {
    event_id: string;
    notes: string;
    items: MrvItem[];
};

export default function MrvForm({
    events,
    products,
}: {
    events: { id: number; event_name: string }[];
    products: { id: number; name: string; sku: string; unit: string; available: number }[];
}) {
    const { data, setData, post, processing, errors } = useForm<MrvFormData>({
        event_id: '',
        notes: '',
        items: [{ product_id: '', quantity_requested: '' }],
    });

    const addRow = () => setData('items', [...data.items, { product_id: '', quantity_requested: '' }]);

    const removeRow = (index: number) =>
        setData('items', data.items.filter((_, i) => i !== index));

    const updateRow = (index: number, patch: Partial<MrvItem>) =>
        setData(
            'items',
            data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('warehouse.mrv.store'));
    };

    return (
        <AppLayout title="Buat MRV">
            <Head title="Buat MRV" />
            <form onSubmit={submit}>
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Informasi MRV</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Event" error={errors.event_id}>
                                <Select value={data.event_id} onChange={(e) => setData('event_id', e.target.value)}>
                                    <option value="">- Pilih Event -</option>
                                    {events.map((ev) => (
                                        <option key={ev.id} value={ev.id}>
                                            {ev.event_name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label="Catatan" error={errors.notes}>
                                <Textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </Field>
                        </div>
                    </CardContent>
                </Card>

                <Card className="mb-6">
                    <CardHeader className="flex items-center justify-between">
                        <CardTitle>Daftar Barang</CardTitle>
                        <Button type="button" variant="outline" size="sm" onClick={addRow}>
                            <Plus className="h-4 w-4" /> Baris
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Produk</TH>
                                    <TH className="w-40">Jumlah</TH>
                                    <TH className="w-16"></TH>
                                </TR>
                            </THead>
                            <TBody>
                                {data.items.map((item, index) => (
                                    <TR key={index}>
                                        <TD>
                                            <Select
                                                value={item.product_id}
                                                onChange={(e) => updateRow(index, { product_id: e.target.value })}
                                            >
                                                <option value="">- Pilih Produk -</option>
                                                {products.map((p) => (
                                                    <option key={p.id} value={p.id}>
                                                        {p.name} ({p.sku}) — tersedia {p.available} {p.unit}
                                                    </option>
                                                ))}
                                            </Select>
                                        </TD>
                                        <TD>
                                            <Input
                                                type="number"
                                                min={1}
                                                value={item.quantity_requested}
                                                onChange={(e) =>
                                                    updateRow(index, { quantity_requested: e.target.value })
                                                }
                                            />
                                        </TD>
                                        <TD className="text-right">
                                            {data.items.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => removeRow(index)}
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        {errors.items && <p className="mt-2 text-xs text-red-600">{errors.items}</p>}
                    </CardContent>
                </Card>

                <div className="flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('warehouse.mrv.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
