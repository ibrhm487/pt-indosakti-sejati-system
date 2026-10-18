import { Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Select, Table, TBody, TD, TH, THead, TR, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

type ReturnableItem = {
    product_id: number;
    product: string | null;
    unit: string | null;
    quantity_issued: number;
};

type ReturnableRequest = {
    id: number;
    mrv_number: string;
    event: string | null;
    items: ReturnableItem[];
};

type ReturnItem = { product_id: string; quantity_good: string; quantity_damaged: string };

type ReturnFormData = {
    stock_request_id: string;
    notes: string;
    items: ReturnItem[];
    photo: File | null;
};

const toItems = (request: ReturnableRequest | null): ReturnItem[] =>
    (request?.items ?? []).map((item) => ({
        product_id: String(item.product_id),
        quantity_good: '',
        quantity_damaged: '',
    }));

export default function ReturnForm({
    requests,
    selectedRequest,
}: {
    requests: ReturnableRequest[];
    selectedRequest: ReturnableRequest | null;
}) {
    const { data, setData, post, processing, errors } = useForm<ReturnFormData>({
        stock_request_id: selectedRequest ? String(selectedRequest.id) : '',
        notes: '',
        items: toItems(selectedRequest),
        photo: null,
    });

    const activeRequest = useMemo(
        () => requests.find((r) => String(r.id) === data.stock_request_id) ?? null,
        [requests, data.stock_request_id],
    );

    const selectRequest = (value: string) => {
        const request = requests.find((r) => String(r.id) === value) ?? null;
        setData('stock_request_id', value);
        setData('items', toItems(request));
    };

    const updateRow = (index: number, patch: Partial<ReturnItem>) =>
        setData(
            'items',
            data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('warehouse.returns.store'), { forceFormData: true });
    };

    return (
        <AppLayout title="Catat Pengembalian">
            <Head title="Catat Pengembalian" />
            <form onSubmit={submit}>
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Informasi Pengembalian</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="MRV (sudah dikeluarkan)" error={errors.stock_request_id}>
                                <Select value={data.stock_request_id} onChange={(e) => selectRequest(e.target.value)}>
                                    <option value="">- Pilih MRV -</option>
                                    {requests.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.mrv_number} — {r.event ?? '-'}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label="Catatan" error={errors.notes}>
                                <Textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </Field>
                            <Field label="Foto Bukti Pengembalian (opsional)" error={errors.photo} className="sm:col-span-2">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(e) => setData('photo', e.target.files?.[0] ?? null)}
                                    className="block w-full cursor-pointer rounded-lg border border-slate-200 bg-white text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-slate-700"
                                />
                            </Field>
                        </div>
                    </CardContent>
                </Card>

                {activeRequest && (
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>Barang Dikembalikan</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Produk</TH>
                                        <TH className="text-right">Dikeluarkan</TH>
                                        <TH className="w-40">Baik</TH>
                                        <TH className="w-40">Rusak</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {activeRequest.items.map((item, index) => (
                                        <TR key={item.product_id}>
                                            <TD>
                                                <p className="font-medium text-slate-800">{item.product ?? '-'}</p>
                                                <p className="text-xs text-slate-400">{item.unit}</p>
                                            </TD>
                                            <TD className="text-right">{formatNumber(item.quantity_issued)}</TD>
                                            <TD>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={data.items[index]?.quantity_good ?? ''}
                                                    onChange={(e) => updateRow(index, { quantity_good: e.target.value })}
                                                />
                                            </TD>
                                            <TD>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={data.items[index]?.quantity_damaged ?? ''}
                                                    onChange={(e) => updateRow(index, { quantity_damaged: e.target.value })}
                                                />
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {errors.items && <p className="mb-4 text-sm text-red-600">{errors.items}</p>}

                <div className="flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('warehouse.returns.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
