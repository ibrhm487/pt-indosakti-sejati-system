import { CardTable, ConfirmButton, EmptyState, FilterCard, PageHeader, Pagination, SearchInput } from '@/Components/AppBits';
import {
    EMPTY_USER_FORM,
    RoleOption,
    UserFormData,
    UserFormFields,
    userToForm,
} from '@/Components/UserForm';
import { Badge, Button, Modal, Select, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type UserRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    active_role_id: number | null;
    role_ids: number[];
    roles: { slug: string; name: string }[];
    active_role: string | null;
};

export default function AdminUsersIndex({
    users,
    roles,
    filters,
}: {
    users: any;
    roles: RoleOption[];
    filters: { search?: string; role_id?: string | number };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [roleId, setRoleId] = useState(filters.role_id ? String(filters.role_id) : '');
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const canManage = usePage().props.auth.permissions.includes('admin.users.manage');

    const form = useForm<UserFormData>(EMPTY_USER_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.users.index'), { search, role_id: roleId }, { preserveState: true });
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(EMPTY_USER_FORM);
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (u: UserRow) => {
        setEditingId(u.id);
        form.setData(userToForm(u));
        form.clearErrors();
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setEditingId(null);
        form.reset();
        form.clearErrors();
    };

    const submitForm = (e: FormEvent) => {
        e.preventDefault();
        if (editingId) {
            form.put(route('admin.users.update', editingId), { onSuccess: closeForm });
        } else {
            form.post(route('admin.users.store'), { onSuccess: closeForm });
        }
    };

    return (
        <AppLayout title="Pengguna">
            <Head title="Pengguna" />
            <PageHeader title="Pengguna" subtitle="Kelola akun dan peran pengguna">
                {canManage && (
                    <Button variant="primary" onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Pengguna Baru
                    </Button>
                )}
            </PageHeader>

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        placeholder="Cari nama atau email…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Select className="sm:w-64" value={roleId} onChange={(e) => setRoleId(e.target.value)}>
                        <option value="">Semua peran</option>
                        {roles.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.name}
                            </option>
                        ))}
                    </Select>
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">
                        Terapkan
                    </Button>
                </form>
            </FilterCard>

            <CardTable>
                {users.data.length === 0 ? (
                    <EmptyState title="Belum ada pengguna" description="Tambahkan pengguna baru untuk memulai." />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nama / Email</TH>
                                    <TH>Peran</TH>
                                    <TH>Peran Aktif</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {users.data.map((u: UserRow) => (
                                    <TR key={u.id}>
                                        <TD>
                                            <p className="font-medium text-slate-800">{u.name}</p>
                                            <p className="text-xs text-slate-400">{u.email}</p>
                                            {u.phone && <p className="text-xs text-slate-400">{u.phone}</p>}
                                        </TD>
                                        <TD>
                                            <div className="flex flex-wrap gap-1">
                                                {(u.roles ?? []).map((r) => (
                                                    <Badge key={r.slug} variant="info">
                                                        {r.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TD>
                                        <TD>{u.active_role ?? '-'}</TD>
                                        <TD>
                                            <Badge variant={u.is_active ? 'success' : 'danger'}>
                                                {u.is_active ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                        </TD>
                                        <TD className="text-right">
                                            <div className="flex justify-end gap-2">
                                                {canManage && (
                                                    <Button size="sm" variant="outline" onClick={() => openEdit(u)}>
                                                        <Pencil className="h-3.5 w-3.5" /> Ubah
                                                    </Button>
                                                )}
                                                {canManage && (
                                                    <ConfirmButton
                                                        title="Hapus Pengguna"
                                                        description={`Hapus pengguna ${u.name}?`}
                                                        onConfirm={() =>
                                                            router.delete(route('admin.users.destroy', u.id), {
                                                                preserveScroll: true,
                                                            })
                                                        }
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </ConfirmButton>
                                                )}
                                            </div>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={users} />
                    </>
                )}
            </CardTable>

            <Modal
                open={formOpen}
                onClose={closeForm}
                size="lg"
                title={editingId ? 'Ubah Pengguna' : 'Pengguna Baru'}
                description="Informasi akun dan hak akses pengguna."
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={closeForm}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="user-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="user-form" onSubmit={submitForm}>
                    <UserFormFields form={form} roles={roles} isEdit={!!editingId} />
                </form>
            </Modal>
        </AppLayout>
    );
}
