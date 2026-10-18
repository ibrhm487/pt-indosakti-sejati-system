import { CardTable, ConfirmButton, EmptyState, ModalForm, PageHeader } from '@/Components/AppBits';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Field, Input, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatCurrency, formatDate } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

function ActiveCheckbox({ checked, onChange }: { checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-slate-700">
            <input
                type="checkbox"
                className="h-4 w-4 rounded border-slate-300"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            Aktif
        </label>
    );
}

export default function AdminSettingsIndex({
    settings,
    shiftRates,
}: {
    settings: { key: string; label: string; value: string | null }[];
    shiftRates: any[];
}) {
    const settingsForm = useForm<{ settings: { key: string; value: string }[] }>({
        settings: settings.map((s) => ({ key: s.key, value: s.value ?? '' })),
    });

    const [addOpen, setAddOpen] = useState(false);
    const [editing, setEditing] = useState<any | null>(null);
    const addForm = useForm({ label: '', rate: '', effective_from: '', is_active: true });
    const editForm = useForm({ label: '', rate: '', effective_from: '', is_active: true });

    const updateSetting = (index: number, value: string) => {
        settingsForm.setData(
            'settings',
            settingsForm.data.settings.map((s, i) => (i === index ? { ...s, value } : s)),
        );
    };

    const submitSettings = (e: FormEvent) => {
        e.preventDefault();
        settingsForm.put(route('admin.settings.update'), { preserveScroll: true });
    };

    const submitAdd = (e: FormEvent) => {
        e.preventDefault();
        addForm.post(route('admin.shift-rates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setAddOpen(false);
                addForm.reset();
            },
        });
    };

    const openEdit = (r: any) => {
        setEditing(r);
        editForm.setData({
            label: r.label ?? '',
            rate: r.rate !== null && r.rate !== undefined ? String(r.rate) : '',
            effective_from: r.effective_from ? String(r.effective_from).slice(0, 10) : '',
            is_active: !!r.is_active,
        });
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(route('admin.shift-rates.update', editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <AppLayout title="Pengaturan">
            <Head title="Pengaturan" />
            <PageHeader title="Pengaturan" subtitle="Konfigurasi umum dan tarif shift" />

            <Card className="mb-6">
                <CardHeader>
                    <CardTitle>Pengaturan Umum</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submitSettings}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {settings.map((s, index) => (
                                <Field key={s.key} label={s.label}>
                                    <Input
                                        value={settingsForm.data.settings[index]?.value ?? ''}
                                        onChange={(e) => updateSetting(index, e.target.value)}
                                    />
                                </Field>
                            ))}
                        </div>
                        <Button type="submit" variant="primary" disabled={settingsForm.processing} className="mt-2">
                            {settingsForm.processing ? 'Menyimpan…' : 'Simpan Pengaturan'}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-slate-800">Tarif Shift</h3>
                <Button variant="primary" onClick={() => setAddOpen(true)}>
                    <Plus className="h-4 w-4" /> Tarif
                </Button>
            </div>

            <CardTable>
                {shiftRates.length === 0 ? (
                    <EmptyState title="Belum ada tarif shift" />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Label</TH>
                                <TH>Tarif</TH>
                                <TH>Berlaku Sejak</TH>
                                <TH>Status</TH>
                                <TH className="text-right">Aksi</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {shiftRates.map((r: any) => (
                                <TR key={r.id}>
                                    <TD className="font-medium text-slate-800">{r.label}</TD>
                                    <TD>{formatCurrency(r.rate)}</TD>
                                    <TD>{formatDate(r.effective_from)}</TD>
                                    <TD>
                                        <Badge variant={r.is_active ? 'success' : 'danger'}>
                                            {r.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </TD>
                                    <TD className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" onClick={() => openEdit(r)}>
                                                <Pencil className="h-3.5 w-3.5" /> Ubah
                                            </Button>
                                            <ConfirmButton
                                                title="Hapus Tarif Shift"
                                                description={`Hapus tarif ${r.label}?`}
                                                onConfirm={() =>
                                                    router.delete(route('admin.shift-rates.destroy', r.id), {
                                                        preserveScroll: true,
                                                    })
                                                }
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </ConfirmButton>
                                        </div>
                                    </TD>
                                </TR>
                            ))}
                        </TBody>
                    </Table>
                )}
            </CardTable>

            <ModalForm
                open={addOpen}
                onClose={() => setAddOpen(false)}
                title="Tambah Tarif Shift"
                onSubmit={submitAdd}
                submitLabel={addForm.processing ? 'Menyimpan…' : 'Tambah'}
                submitDisabled={addForm.processing}
            >
                <ShiftRateFields form={addForm} />
            </ModalForm>

            <ModalForm
                open={!!editing}
                onClose={() => setEditing(null)}
                title="Ubah Tarif Shift"
                onSubmit={submitEdit}
                submitLabel={editForm.processing ? 'Menyimpan…' : 'Simpan'}
                submitDisabled={editForm.processing}
            >
                <ShiftRateFields form={editForm} />
            </ModalForm>
        </AppLayout>
    );
}

function ShiftRateFields({ form }: { form: any }) {
    const { data, setData, errors } = form;
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Label" error={errors.label}>
                <Input value={data.label} onChange={(e) => setData('label', e.target.value)} />
            </Field>
            <Field label="Tarif" error={errors.rate}>
                <Input type="number" min={0} value={data.rate} onChange={(e) => setData('rate', e.target.value)} />
            </Field>
            <Field label="Berlaku Sejak" error={errors.effective_from}>
                <Input
                    type="date"
                    value={data.effective_from}
                    onChange={(e) => setData('effective_from', e.target.value)}
                />
            </Field>
            <Field label="Status" className="flex items-center gap-2 pt-6">
                <ActiveCheckbox checked={data.is_active} onChange={(v) => setData('is_active', v)} />
            </Field>
        </div>
    );
}
