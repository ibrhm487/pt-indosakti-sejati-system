import { Button, Card, CardContent, Field, Input, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, InertiaFormProps, useForm } from '@inertiajs/react';
import { ShieldCheck, UserRound } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';

export type RoleOption = { id: number; slug: string; name: string };

export type UserFormData = {
    name: string;
    email: string;
    password: string;
    phone: string;
    is_active: boolean;
    roles: number[];
    active_role_id: string;
};

export const EMPTY_USER_FORM: UserFormData = {
    name: '',
    email: '',
    password: '',
    phone: '',
    is_active: true,
    roles: [],
    active_role_id: '',
};

export function userToForm(u: {
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    active_role_id: number | null;
    role_ids?: number[];
}): UserFormData {
    return {
        name: u.name,
        email: u.email,
        password: '',
        phone: u.phone ?? '',
        is_active: u.is_active,
        roles: u.role_ids ?? [],
        active_role_id: u.active_role_id ? String(u.active_role_id) : '',
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

export function UserFormFields({
    form,
    roles,
    isEdit = false,
}: {
    form: InertiaFormProps<UserFormData>;
    roles: RoleOption[];
    isEdit?: boolean;
}) {
    const { data, setData, errors } = form;

    const toggleRole = (id: number) => {
        setData('roles', data.roles.includes(id) ? data.roles.filter((r) => r !== id) : [...data.roles, id]);
    };

    return (
        <div className="space-y-6">
            <Section icon={<UserRound className="h-3.5 w-3.5" />} title="Informasi Akun">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama" error={errors.name} className="sm:col-span-2">
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </Field>
                    <Field label="Email" error={errors.email}>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    </Field>
                    <Field label="Telepon" error={errors.phone}>
                        <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    </Field>
                    <Field
                        label={isEdit ? 'Kata Sandi (biarkan kosong jika tidak diubah)' : 'Kata Sandi'}
                        error={errors.password}
                    >
                        <Input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </Field>
                    <Field label="Status" className="flex items-center gap-2 pt-6">
                        <input
                            type="checkbox"
                            className="h-4 w-4 rounded border-slate-300"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        <span className="text-sm text-slate-700">Aktif</span>
                    </Field>
                </div>
            </Section>

            <Section icon={<ShieldCheck className="h-3.5 w-3.5" />} title="Peran & Akses">
                <Field label="Peran yang dimiliki" error={errors.roles}>
                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        {roles.map((r) => (
                            <label key={r.id} className="flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 rounded border-slate-300"
                                    checked={data.roles.includes(r.id)}
                                    onChange={() => toggleRole(r.id)}
                                />
                                {r.name}
                            </label>
                        ))}
                    </div>
                </Field>
                <Field label="Peran Aktif" error={errors.active_role_id}>
                    <Select
                        value={data.active_role_id}
                        onChange={(e) => setData('active_role_id', e.target.value)}
                    >
                        <option value="">- Pilih -</option>
                        {roles.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.name}
                            </option>
                        ))}
                    </Select>
                </Field>
            </Section>
        </div>
    );
}

export default function UserForm({
    user,
    roles,
}: {
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        is_active: boolean;
        active_role_id: number | null;
        roles: number[];
    } | null;
    roles: RoleOption[];
}) {
    const isEdit = !!user;
    const form = useForm<UserFormData>(user ? userToForm({ ...user, role_ids: user.roles }) : EMPTY_USER_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit && user) {
            form.put(route('admin.users.update', user.id));
        } else {
            form.post(route('admin.users.store'));
        }
    };

    return (
        <AppLayout title={isEdit ? 'Ubah Pengguna' : 'Pengguna Baru'}>
            <Head title={isEdit ? 'Ubah Pengguna' : 'Pengguna Baru'} />
            <form onSubmit={submit}>
                <Card className="mb-6">
                    <CardContent className="pt-6">
                        <UserFormFields form={form} roles={roles} isEdit={isEdit} />
                    </CardContent>
                </Card>

                <div className="flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={form.processing}>
                        {form.processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                    <Link href={route('admin.users.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
