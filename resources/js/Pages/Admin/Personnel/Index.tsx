import { CardTable, ConfirmButton, EmptyState, FilterCard, ModalForm, PageHeader, Pagination, SearchInput } from '@/Components/AppBits';
import { Badge, Button, Field, Input, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function AdminPersonnelIndex({
    personnel,
    filters,
}: {
    personnel: any;
    filters: { search?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [addOpen, setAddOpen] = useState(false);
    const [editing, setEditing] = useState<any | null>(null);

    const addForm = useForm({ name: '', role: '', phone: '', is_active: true });
    const editForm = useForm({ name: '', role: '', phone: '', is_active: true });

    const submitSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('admin.personnel.index'), { search }, { preserveState: true });
    };

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('admin.personnel.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setAddOpen(false);
                addForm.reset();
            },
        });
    };

    const openEdit = (p: any) => {
        setEditing(p);
        editForm.setData({
            name: p.name ?? '',
            role: p.role ?? '',
            phone: p.phone ?? '',
            is_active: !!p.is_active,
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(route('admin.personnel.update', editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <AppLayout title="Personel">
            <Head title="Personel" />
            <PageHeader title="Personel" subtitle="Daftar personel dan perannya">
                <Button variant="primary" onClick={() => setAddOpen(true)}>
                    <Plus className="h-4 w-4" /> Personel
                </Button>
            </PageHeader>

            <FilterCard>
                <form onSubmit={submitSearch} className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        placeholder="Cari nama personel…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {personnel.data.length === 0 ? (
                    <EmptyState title="Belum ada personel" description="Tambahkan personel baru untuk memulai." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nama</TH>
                                    <TH>Peran</TH>
                                    <TH>Telepon</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {personnel.data.map((p: any) => (
                                    <TR key={p.id}>
                                        <TD className="font-medium text-text">{p.name}</TD>
                                        <TD>{p.role ?? '-'}</TD>
                                        <TD>{p.phone ?? '-'}</TD>
                                        <TD>
                                            <Badge variant={p.is_active ? 'success' : 'danger'}>
                                                {p.is_active ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                        </TD>
                                        <TD className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button size="sm" variant="outline" onClick={() => openEdit(p)}>
                                                    <Pencil className="h-3.5 w-3.5" /> Ubah
                                                </Button>
                                                <ConfirmButton
                                                    title="Hapus Personel"
                                                    description={`Hapus personel ${p.name}?`}
                                                    onConfirm={() =>
                                                        router.delete(route('admin.personnel.destroy', p.id), {
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
                        <Pagination paginator={personnel} />
                    </>
                )}
            </CardTable>

            <ModalForm
                open={addOpen}
                onClose={() => setAddOpen(false)}
                title="Tambah Personel"
                onSubmit={submitAdd}
                submitLabel={addForm.processing ? 'Menyimpan…' : 'Tambah'}
                submitDisabled={addForm.processing}
            >
                <PersonnelFields form={addForm} />
            </ModalForm>

            <ModalForm
                open={!!editing}
                onClose={() => setEditing(null)}
                title="Ubah Personel"
                onSubmit={submitEdit}
                submitLabel={editForm.processing ? 'Menyimpan…' : 'Simpan'}
                submitDisabled={editForm.processing}
            >
                <PersonnelFields form={editForm} />
            </ModalForm>
        </AppLayout>
    );
}

function PersonnelFields({ form }: { form: any }) {
    const { data, setData, errors } = form;
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Nama" error={errors.name}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
            </Field>
            <Field label="Peran" error={errors.role}>
                <Input value={data.role} onChange={(e) => setData('role', e.target.value)} />
            </Field>
            <Field label="Telepon" error={errors.phone}>
                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
            </Field>
            <Field label="Status" className="flex items-center gap-2 pt-6">
                <input
                    type="checkbox"
                    className="h-4 w-4 rounded border-border"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                />
                <span className="text-sm text-text">Aktif</span>
            </Field>
        </div>
    );
}
