import { CardTable, ConfirmButton, EmptyState, ModalForm, PageHeader, Tabs } from '@/Components/AppBits';
import { Badge, Button, Field, Input, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

const TABS = [
    { key: 'brands', label: 'Brand' },
    { key: 'event-types', label: 'Jenis Event' },
    { key: 'venues', label: 'Lokasi' },
    { key: 'categories', label: 'Kategori' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export default function AdminMasterDataIndex({
    brands,
    eventTypes,
    venues,
    categories,
}: {
    brands: any[];
    eventTypes: any[];
    venues: any[];
    categories: any[];
}) {
    const [tab, setTab] = useState<TabKey>('brands');

    return (
        <AppLayout title="Data Master">
            <Head title="Data Master" />
            <PageHeader title="Data Master" subtitle="Kelola data referensi aplikasi" />

            <div className="mb-6">
                <Tabs items={TABS.map((t) => ({ key: t.key, label: t.label }))} active={tab} onChange={(k) => setTab(k as TabKey)} />
            </div>

            {tab === 'brands' && <BrandsSection items={brands} />}
            {tab === 'event-types' && <EventTypesSection items={eventTypes} />}
            {tab === 'venues' && <VenuesSection items={venues} />}
            {tab === 'categories' && <CategoriesSection items={categories} />}
        </AppLayout>
    );
}

function ActiveCheckbox({
    checked,
    onChange,
}: {
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-sm text-text">
            <input
                type="checkbox"
                className="h-4 w-4 rounded border-border"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            Aktif
        </label>
    );
}

function SectionToolbar({ label, onClick }: { label: string; onClick: () => void }) {
    return (
        <div className="mb-4 flex justify-end">
            <Button variant="primary" onClick={onClick}>
                <Plus className="h-4 w-4" /> {label}
            </Button>
        </div>
    );
}

function BrandsSection({ items }: { items: any[] }) {
    const [addOpen, setAddOpen] = useState(false);
    const [editing, setEditing] = useState<any | null>(null);

    const addForm = useForm({ name: '', quarterly_target_cups: '', is_active: true });
    const editForm = useForm({ name: '', quarterly_target_cups: '', is_active: true });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('admin.master.store', 'brands'), {
            preserveScroll: true,
            onSuccess: () => {
                setAddOpen(false);
                addForm.reset();
            },
        });
    };

    const openEdit = (b: any) => {
        setEditing(b);
        editForm.setData({
            name: b.name ?? '',
            quarterly_target_cups: b.quarterly_target_cups ? String(b.quarterly_target_cups) : '',
            is_active: !!b.is_active,
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(route('admin.master.update', { type: 'brands', id: editing.id }), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <>
            <SectionToolbar label="Tambah Brand" onClick={() => setAddOpen(true)} />
            <CardTable>
                {items.length === 0 ? (
                    <EmptyState title="Belum ada brand" />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Nama</TH>
                                <TH>Target Kuartalan</TH>
                                <TH>Status</TH>
                                <TH className="text-right">Aksi</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {items.map((b: any) => (
                                <TR key={b.id}>
                                    <TD className="font-medium text-text">{b.name}</TD>
                                    <TD>{b.quarterly_target_cups ?? '-'}</TD>
                                    <TD>
                                        <Badge variant={b.is_active ? 'success' : 'danger'}>
                                            {b.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </TD>
                                    <TD className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" onClick={() => openEdit(b)}>
                                                <Pencil className="h-3.5 w-3.5" /> Ubah
                                            </Button>
                                            <ConfirmButton
                                                title="Hapus Brand"
                                                description={`Hapus brand ${b.name}?`}
                                                onConfirm={() =>
                                                    router.delete(route('admin.master.destroy', { type: 'brands', id: b.id }), {
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
                title="Tambah Brand"
                onSubmit={submitAdd}
                submitLabel={addForm.processing ? 'Menyimpan…' : 'Tambah'}
                submitDisabled={addForm.processing}
            >
                <Field label="Nama" error={addForm.errors.name}>
                    <Input value={addForm.data.name} onChange={(e) => addForm.setData('name', e.target.value)} />
                </Field>
                <Field label="Target Kuartalan (cup)" error={addForm.errors.quarterly_target_cups}>
                    <Input
                        type="number"
                        min={0}
                        value={addForm.data.quarterly_target_cups}
                        onChange={(e) => addForm.setData('quarterly_target_cups', e.target.value)}
                    />
                </Field>
                <Field label="Status">
                    <ActiveCheckbox checked={addForm.data.is_active} onChange={(v) => addForm.setData('is_active', v)} />
                </Field>
            </ModalForm>

            <ModalForm
                open={!!editing}
                onClose={() => setEditing(null)}
                title="Ubah Brand"
                onSubmit={submitEdit}
                submitLabel={editForm.processing ? 'Menyimpan…' : 'Simpan'}
                submitDisabled={editForm.processing}
            >
                <Field label="Nama" error={editForm.errors.name}>
                    <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                </Field>
                <Field label="Target Kuartalan (cup)" error={editForm.errors.quarterly_target_cups}>
                    <Input
                        type="number"
                        min={0}
                        value={editForm.data.quarterly_target_cups}
                        onChange={(e) => editForm.setData('quarterly_target_cups', e.target.value)}
                    />
                </Field>
                <Field label="Status">
                    <ActiveCheckbox checked={editForm.data.is_active} onChange={(v) => editForm.setData('is_active', v)} />
                </Field>
            </ModalForm>
        </>
    );
}

function EventTypesSection({ items }: { items: any[] }) {
    const [addOpen, setAddOpen] = useState(false);
    const [editing, setEditing] = useState<any | null>(null);

    const addForm = useForm({ name: '', requires_school: false, kitchen_default: false, is_active: true });
    const editForm = useForm({ name: '', requires_school: false, kitchen_default: false, is_active: true });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('admin.master.store', 'event-types'), {
            preserveScroll: true,
            onSuccess: () => {
                setAddOpen(false);
                addForm.reset();
            },
        });
    };

    const openEdit = (t: any) => {
        setEditing(t);
        editForm.setData({
            name: t.name ?? '',
            requires_school: !!t.requires_school,
            kitchen_default: !!t.kitchen_default,
            is_active: !!t.is_active,
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(route('admin.master.update', { type: 'event-types', id: editing.id }), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <>
            <SectionToolbar label="Tambah Jenis Event" onClick={() => setAddOpen(true)} />
            <CardTable>
                {items.length === 0 ? (
                    <EmptyState title="Belum ada jenis event" />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Nama</TH>
                                <TH>Butuh Sekolah</TH>
                                <TH>Kitchen Default</TH>
                                <TH>Status</TH>
                                <TH className="text-right">Aksi</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {items.map((t: any) => (
                                <TR key={t.id}>
                                    <TD className="font-medium text-text">{t.name}</TD>
                                    <TD>{t.requires_school ? 'Ya' : 'Tidak'}</TD>
                                    <TD>{t.kitchen_default ? 'Ya' : 'Tidak'}</TD>
                                    <TD>
                                        <Badge variant={t.is_active ? 'success' : 'danger'}>
                                            {t.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </TD>
                                    <TD className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" onClick={() => openEdit(t)}>
                                                <Pencil className="h-3.5 w-3.5" /> Ubah
                                            </Button>
                                            <ConfirmButton
                                                title="Hapus Jenis Event"
                                                description={`Hapus jenis event ${t.name}?`}
                                                onConfirm={() =>
                                                    router.delete(
                                                        route('admin.master.destroy', { type: 'event-types', id: t.id }),
                                                        { preserveScroll: true },
                                                    )
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
                title="Tambah Jenis Event"
                onSubmit={submitAdd}
                submitLabel={addForm.processing ? 'Menyimpan…' : 'Tambah'}
                submitDisabled={addForm.processing}
            >
                <Field label="Nama" error={addForm.errors.name}>
                    <Input value={addForm.data.name} onChange={(e) => addForm.setData('name', e.target.value)} />
                </Field>
                <Field label="Butuh Sekolah">
                    <ActiveCheckbox checked={addForm.data.requires_school} onChange={(v) => addForm.setData('requires_school', v)} />
                </Field>
                <Field label="Kitchen Default">
                    <ActiveCheckbox checked={addForm.data.kitchen_default} onChange={(v) => addForm.setData('kitchen_default', v)} />
                </Field>
                <Field label="Status">
                    <ActiveCheckbox checked={addForm.data.is_active} onChange={(v) => addForm.setData('is_active', v)} />
                </Field>
            </ModalForm>

            <ModalForm
                open={!!editing}
                onClose={() => setEditing(null)}
                title="Ubah Jenis Event"
                onSubmit={submitEdit}
                submitLabel={editForm.processing ? 'Menyimpan…' : 'Simpan'}
                submitDisabled={editForm.processing}
            >
                <Field label="Nama" error={editForm.errors.name}>
                    <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                </Field>
                <Field label="Butuh Sekolah">
                    <ActiveCheckbox checked={editForm.data.requires_school} onChange={(v) => editForm.setData('requires_school', v)} />
                </Field>
                <Field label="Kitchen Default">
                    <ActiveCheckbox checked={editForm.data.kitchen_default} onChange={(v) => editForm.setData('kitchen_default', v)} />
                </Field>
                <Field label="Status">
                    <ActiveCheckbox checked={editForm.data.is_active} onChange={(v) => editForm.setData('is_active', v)} />
                </Field>
            </ModalForm>
        </>
    );
}

const EMPTY_VENUE = {
    name: '',
    npsn: '',
    is_school: false,
    address: '',
    city: '',
    province: '',
    contact_name: '',
    contact_phone: '',
};

function VenuesSection({ items }: { items: any[] }) {
    const [addOpen, setAddOpen] = useState(false);
    const [editing, setEditing] = useState<any | null>(null);

    const addForm = useForm({ ...EMPTY_VENUE });
    const editForm = useForm({ ...EMPTY_VENUE });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('admin.master.store', 'venues'), {
            preserveScroll: true,
            onSuccess: () => {
                setAddOpen(false);
                addForm.reset();
            },
        });
    };

    const openEdit = (v: any) => {
        setEditing(v);
        editForm.setData({
            name: v.name ?? '',
            npsn: v.npsn ?? '',
            is_school: !!v.is_school,
            address: v.address ?? '',
            city: v.city ?? '',
            province: v.province ?? '',
            contact_name: v.contact_name ?? '',
            contact_phone: v.contact_phone ?? '',
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(route('admin.master.update', { type: 'venues', id: editing.id }), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <>
            <SectionToolbar label="Tambah Lokasi" onClick={() => setAddOpen(true)} />
            <CardTable>
                {items.length === 0 ? (
                    <EmptyState title="Belum ada lokasi" />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Nama</TH>
                                <TH>NPSN</TH>
                                <TH>Kota</TH>
                                <TH>Provinsi</TH>
                                <TH>Sekolah</TH>
                                <TH className="text-right">Aksi</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {items.map((v: any) => (
                                <TR key={v.id}>
                                    <TD className="font-medium text-text">{v.name}</TD>
                                    <TD>{v.npsn ?? '-'}</TD>
                                    <TD>{v.city ?? '-'}</TD>
                                    <TD>{v.province ?? '-'}</TD>
                                    <TD>{v.is_school ? 'Ya' : 'Tidak'}</TD>
                                    <TD className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" onClick={() => openEdit(v)}>
                                                <Pencil className="h-3.5 w-3.5" /> Ubah
                                            </Button>
                                            <ConfirmButton
                                                title="Hapus Lokasi"
                                                description={`Hapus lokasi ${v.name}?`}
                                                onConfirm={() =>
                                                    router.delete(route('admin.master.destroy', { type: 'venues', id: v.id }), {
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
                size="lg"
                title="Tambah Lokasi"
                onSubmit={submitAdd}
                submitLabel={addForm.processing ? 'Menyimpan…' : 'Tambah'}
                submitDisabled={addForm.processing}
            >
                <VenueFields form={addForm} />
            </ModalForm>

            <ModalForm
                open={!!editing}
                onClose={() => setEditing(null)}
                size="lg"
                title="Ubah Lokasi"
                onSubmit={submitEdit}
                submitLabel={editForm.processing ? 'Menyimpan…' : 'Simpan'}
                submitDisabled={editForm.processing}
            >
                <VenueFields form={editForm} />
            </ModalForm>
        </>
    );
}

function VenueFields({ form }: { form: any }) {
    const { data, setData, errors } = form;
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Nama" error={errors.name}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
            </Field>
            <Field label="NPSN" error={errors.npsn}>
                <Input value={data.npsn} onChange={(e) => setData('npsn', e.target.value)} />
            </Field>
            <Field label="Sekolah">
                <ActiveCheckbox checked={data.is_school} onChange={(v) => setData('is_school', v)} />
            </Field>
            <Field label="Kota" error={errors.city}>
                <Input value={data.city} onChange={(e) => setData('city', e.target.value)} />
            </Field>
            <Field label="Alamat" error={errors.address} className="sm:col-span-2">
                <Input value={data.address} onChange={(e) => setData('address', e.target.value)} />
            </Field>
            <Field label="Provinsi" error={errors.province}>
                <Input value={data.province} onChange={(e) => setData('province', e.target.value)} />
            </Field>
            <Field label="Nama Kontak" error={errors.contact_name}>
                <Input value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} />
            </Field>
            <Field label="Telepon Kontak" error={errors.contact_phone}>
                <Input value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} />
            </Field>
        </div>
    );
}

function CategoriesSection({ items }: { items: any[] }) {
    const [addOpen, setAddOpen] = useState(false);
    const [editing, setEditing] = useState<any | null>(null);

    const addForm = useForm({ name: '' });
    const editForm = useForm({ name: '' });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('admin.master.store', 'categories'), {
            preserveScroll: true,
            onSuccess: () => {
                setAddOpen(false);
                addForm.reset();
            },
        });
    };

    const openEdit = (c: any) => {
        setEditing(c);
        editForm.setData({ name: c.name ?? '' });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(route('admin.master.update', { type: 'categories', id: editing.id }), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <>
            <SectionToolbar label="Tambah Kategori" onClick={() => setAddOpen(true)} />
            <CardTable>
                {items.length === 0 ? (
                    <EmptyState title="Belum ada kategori" />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Nama</TH>
                                <TH className="text-right">Aksi</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {items.map((c: any) => (
                                <TR key={c.id}>
                                    <TD className="font-medium text-text">{c.name}</TD>
                                    <TD className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" onClick={() => openEdit(c)}>
                                                <Pencil className="h-3.5 w-3.5" /> Ubah
                                            </Button>
                                            <ConfirmButton
                                                title="Hapus Kategori"
                                                description={`Hapus kategori ${c.name}?`}
                                                onConfirm={() =>
                                                    router.delete(route('admin.master.destroy', { type: 'categories', id: c.id }), {
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
                title="Tambah Kategori"
                onSubmit={submitAdd}
                submitLabel={addForm.processing ? 'Menyimpan…' : 'Tambah'}
                submitDisabled={addForm.processing}
                size="sm"
            >
                <Field label="Nama" error={addForm.errors.name}>
                    <Input value={addForm.data.name} onChange={(e) => addForm.setData('name', e.target.value)} />
                </Field>
            </ModalForm>

            <ModalForm
                open={!!editing}
                onClose={() => setEditing(null)}
                title="Ubah Kategori"
                onSubmit={submitEdit}
                submitLabel={editForm.processing ? 'Menyimpan…' : 'Simpan'}
                submitDisabled={editForm.processing}
                size="sm"
            >
                <Field label="Nama" error={editForm.errors.name}>
                    <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                </Field>
            </ModalForm>
        </>
    );
}
