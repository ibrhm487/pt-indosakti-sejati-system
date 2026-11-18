import {
    CardTable,
    ConfirmButton,
    EmptyState,
    PageHeader,
} from '@/Components/AppBits';
import {
    Badge,
    Button,
    Field,
    Input,
    Modal,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Eye, KeyRound, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Permission = { slug: string; name: string };
type PermissionGroups = Record<string, Permission[]>;

type RoleRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
    permissions: string[];
};

type RoleFormData = {
    name: string;
    slug: string;
    description: string;
    permissions: string[];
};

const EMPTY_FORM: RoleFormData = {
    name: '',
    slug: '',
    description: '',
    permissions: [],
};

const MODULE_LABELS: Record<string, string> = {
    dashboard: 'Dashboard',
    events: 'Event',
    fjp: 'FJP',
    venues: 'Lokasi',
    permits: 'Perizinan',
    warehouse: 'Gudang',
    kitchen: 'Kitchen',
    reports: 'Laporan',
    quick_reports: 'Quick Report',
    targets: 'Target Kuartal',
    attendance: 'Absensi',
    admin: 'Administrasi',
};

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function PermissionMatrix({
    groups,
    selected,
    onToggle,
    onToggleModule,
    readOnly = false,
}: {
    groups: PermissionGroups;
    selected: string[];
    onToggle?: (slug: string) => void;
    onToggleModule?: (slugs: string[]) => void;
    readOnly?: boolean;
}) {
    return (
        <div className="max-h-[52vh] space-y-3 overflow-y-auto pe-1">
            {Object.entries(groups).map(([module, permissions]) => {
                const slugs = permissions.map((p) => p.slug);
                const selectedCount = slugs.filter((slug) =>
                    selected.includes(slug),
                ).length;
                const allSelected = selectedCount === slugs.length;

                return (
                    <div
                        key={module}
                        className="rounded-xl border border-border bg-surface"
                    >
                        <div className="flex items-center justify-between gap-3 border-b border-border bg-page/50 px-3 py-2">
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-semibold uppercase tracking-wide text-text">
                                    {MODULE_LABELS[module] ?? module}
                                </span>
                                <Badge variant="outline">
                                    {selectedCount}/{slugs.length}
                                </Badge>
                            </div>
                            {!readOnly && onToggleModule && (
                                <label className="flex items-center gap-1.5 text-xs text-muted">
                                    <input
                                        type="checkbox"
                                        className="h-3.5 w-3.5 rounded border-border"
                                        checked={allSelected}
                                        onChange={() =>
                                            onToggleModule(
                                                allSelected ? [] : slugs,
                                            )
                                        }
                                    />
                                    Semua
                                </label>
                            )}
                        </div>
                        <div className="grid gap-x-4 gap-y-2 p-3 sm:grid-cols-2">
                            {permissions.map((permission) => (
                                <label
                                    key={permission.slug}
                                    className={cn(
                                        'flex items-start gap-2 text-sm',
                                        readOnly
                                            ? 'text-muted'
                                            : 'text-text',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        className="mt-0.5 h-4 w-4 rounded border-border"
                                        checked={selected.includes(
                                            permission.slug,
                                        )}
                                        disabled={readOnly}
                                        onChange={() =>
                                            onToggle?.(permission.slug)
                                        }
                                    />
                                    <span className="min-w-0">
                                        <span className="block">
                                            {permission.name}
                                        </span>
                                        <span className="block font-mono text-[11px] text-muted">
                                            {permission.slug}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function AdminRolesIndex({
    roles,
    permissionGroups,
}: {
    roles: RoleRow[];
    permissionGroups: PermissionGroups;
}) {
    const pageErrors = usePage().props.errors as Record<string, string>;
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<RoleRow | null>(null);
    const [detail, setDetail] = useState<RoleRow | null>(null);
    const [slugEdited, setSlugEdited] = useState(false);

    const form = useForm<RoleFormData>(EMPTY_FORM);

    const openCreate = () => {
        setEditing(null);
        setSlugEdited(false);
        form.setData(EMPTY_FORM);
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (role: RoleRow) => {
        setEditing(role);
        setSlugEdited(true);
        form.setData({
            name: role.name,
            slug: role.slug,
            description: role.description ?? '',
            permissions: role.permissions,
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing) {
            form.put(route('admin.roles.update', editing.id), {
                onSuccess: closeForm,
            });

            return;
        }

        form.post(route('admin.roles.store'), { onSuccess: closeForm });
    };

    const togglePermission = (slug: string) => {
        form.setData(
            'permissions',
            form.data.permissions.includes(slug)
                ? form.data.permissions.filter((item) => item !== slug)
                : [...form.data.permissions, slug],
        );
    };

    const toggleModule = (slugs: string[]) => {
        const current = form.data.permissions;
        const allSelected = slugs.every((slug) => current.includes(slug));
        const next = allSelected
            ? current.filter((slug) => !slugs.includes(slug))
            : Array.from(new Set([...current, ...slugs]));

        form.setData('permissions', next);
    };

    return (
        <AppLayout title="Role & Izin">
            <Head title="Role & Izin" />
            <PageHeader
                title="Role & Izin"
                subtitle="Kelola peran dan hak akses pengguna"
            >
                <Button variant="primary" onClick={openCreate}>
                    <Plus className="h-4 w-4" /> Role Baru
                </Button>
            </PageHeader>

            {pageErrors.role && (
                <div className="mb-4 rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                    {pageErrors.role}
                </div>
            )}

            <CardTable>
                {roles.length === 0 ? (
                    <EmptyState title="Belum ada role" />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Role</TH>
                                <TH>Slug</TH>
                                <TH>Pengguna</TH>
                                <TH>Izin</TH>
                                <TH>Tipe</TH>
                                <TH className="text-right">Aksi</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {roles.map((role) => (
                                <TR key={role.id}>
                                    <TD>
                                        <p className="font-medium text-text">
                                            {role.name}
                                        </p>
                                        {role.description && (
                                            <p className="text-xs text-muted">
                                                {role.description}
                                            </p>
                                        )}
                                    </TD>
                                    <TD>
                                        <code className="rounded bg-page px-1.5 py-0.5 text-xs text-muted">
                                            {role.slug}
                                        </code>
                                    </TD>
                                    <TD>{role.users_count}</TD>
                                    <TD>
                                        <Badge variant="info">
                                            {role.permissions_count} izin
                                        </Badge>
                                    </TD>
                                    <TD>
                                        <Badge
                                            variant={
                                                role.is_system
                                                    ? 'accent'
                                                    : 'outline'
                                            }
                                        >
                                            {role.is_system
                                                ? 'Sistem'
                                                : 'Kustom'}
                                        </Badge>
                                    </TD>
                                    <TD className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setDetail(role)
                                                }
                                            >
                                                <Eye className="h-3.5 w-3.5" />{' '}
                                                Lihat
                                            </Button>
                                            {!role.is_system && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openEdit(role)
                                                        }
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />{' '}
                                                        Ubah
                                                    </Button>
                                                    <ConfirmButton
                                                        title="Hapus Role"
                                                        description={`Hapus role ${role.name}?`}
                                                        onConfirm={() =>
                                                            router.delete(
                                                                route(
                                                                    'admin.roles.destroy',
                                                                    role.id,
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </ConfirmButton>
                                                </>
                                            )}
                                        </div>
                                    </TD>
                                </TR>
                            ))}
                        </TBody>
                    </Table>
                )}
            </CardTable>

            <Modal
                open={formOpen}
                onClose={closeForm}
                size="xl"
                title={editing ? 'Ubah Role' : 'Role Baru'}
                description="Tentukan nama role dan izin yang dimiliki."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={closeForm}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="role-form"
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form
                    id="role-form"
                    onSubmit={submit}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nama Role" error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    form.setData('name', value);

                                    if (!editing && !slugEdited) {
                                        form.setData('slug', slugify(value));
                                    }
                                }}
                            />
                        </Field>
                        <Field label="Slug" error={form.errors.slug}>
                            <Input
                                value={form.data.slug}
                                onChange={(event) => {
                                    setSlugEdited(true);
                                    form.setData(
                                        'slug',
                                        event.target.value.toLowerCase(),
                                    );
                                }}
                            />
                        </Field>
                        <Field
                            label="Deskripsi"
                            error={form.errors.description}
                            className="sm:col-span-2"
                        >
                            <Input
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted">
                                <KeyRound className="h-3.5 w-3.5" /> Izin
                            </div>
                            <span className="text-xs text-muted">
                                {form.data.permissions.length} dipilih
                            </span>
                        </div>
                        {form.errors.permissions && (
                            <p className="mb-2 text-xs text-danger">
                                {form.errors.permissions}
                            </p>
                        )}
                        <PermissionMatrix
                            groups={permissionGroups}
                            selected={form.data.permissions}
                            onToggle={togglePermission}
                            onToggleModule={toggleModule}
                        />
                    </div>
                </form>
            </Modal>

            <Modal
                open={!!detail}
                onClose={() => setDetail(null)}
                size="xl"
                title={detail ? `Izin ${detail.name}` : 'Izin Role'}
                description={
                    detail?.is_system
                        ? 'Role sistem memiliki seluruh izin yang terhubung.'
                        : undefined
                }
                footer={
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setDetail(null)}
                    >
                        Tutup
                    </Button>
                }
            >
                {detail && (
                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <ShieldCheck className="h-4 w-4 text-primary" />
                            <span className="text-muted">
                                {detail.permissions_count} izin terhubung
                            </span>
                            <Badge
                                variant={
                                    detail.is_system ? 'accent' : 'outline'
                                }
                            >
                                {detail.is_system ? 'Sistem' : 'Kustom'}
                            </Badge>
                        </div>
                        <PermissionMatrix
                            groups={permissionGroups}
                            selected={detail.permissions}
                            readOnly
                        />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
