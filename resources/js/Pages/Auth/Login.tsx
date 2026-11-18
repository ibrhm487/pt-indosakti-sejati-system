import { Button, Field, Input, Select } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    LoaderCircle,
    LockKeyhole,
    Mail,
    ShieldCheck,
} from 'lucide-react';
import { FormEventHandler } from 'react';

type RoleOption = { id: number; slug: string; name: string };

export default function Login({
    status,
    canResetPassword,
    showRoleSelection = false,
    roles = [],
    pendingEmail = null,
}: {
    status?: string;
    canResetPassword: boolean;
    showRoleSelection?: boolean;
    roles?: RoleOption[];
    pendingEmail?: string | null;
}) {
    const credentials = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const roleSelection = useForm({
        role_id: '',
    });

    const isProcessing = credentials.processing || roleSelection.processing;

    const submitLogin: FormEventHandler = (e) => {
        e.preventDefault();
        credentials.post(route('login'), {
            onFinish: () => credentials.reset('password'),
        });
    };

    const submitRole: FormEventHandler = (e) => {
        e.preventDefault();
        roleSelection.post(route('login.role'));
    };

    return (
        <GuestLayout>
            <Head title="Masuk" />

            {isProcessing && (
                <div
                    role="status"
                    aria-live="polite"
                    className="auth-overlay-enter fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/40 px-5 backdrop-blur-[2px]"
                >
                    <div className="relative w-full max-w-sm overflow-hidden rounded-xl bg-white px-5 py-5 shadow-xl shadow-slate-950/15">
                        <div
                            aria-hidden="true"
                            className="auth-loading-line absolute inset-x-0 top-0 h-0.5 overflow-hidden bg-[#f4dadd]"
                        />
                        <div className="flex items-center gap-4">
                            <img
                                src="/images/indosakti-logo.png"
                                alt="PT Indosakti Sejati"
                                className="h-9 w-auto max-w-40 object-contain"
                            />
                            <div className="min-w-0 flex-1 border-l border-slate-200 pl-4">
                                <p className="text-sm font-semibold text-slate-900">
                                    {roleSelection.processing
                                        ? 'Menyiapkan ruang kerja'
                                        : 'Memverifikasi akun'}
                                </p>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Mohon tunggu sebentar
                                </p>
                            </div>
                            <LoaderCircle
                                aria-hidden="true"
                                className="h-5 w-5 shrink-0 text-primary motion-safe:animate-spin"
                            />
                        </div>
                    </div>
                </div>
            )}

            <div className="space-y-3">
                <span className="inline-flex items-center gap-2 text-[10px] font-semibold tracking-[0.16em] text-primary uppercase">
                    <span className="h-px w-5 bg-primary" />
                    Akses operasional
                </span>
                <div>
                    <h1 className="text-[1.7rem] leading-tight font-semibold tracking-[-0.035em] text-text">
                        {showRoleSelection ? 'Pilih peran' : 'Selamat datang kembali'}
                    </h1>
                    <p className="mt-2 text-[13px] leading-5 text-muted">
                        {showRoleSelection
                            ? `Akun ${pendingEmail ?? 'Anda'} memiliki beberapa peran. Pilih peran untuk melanjutkan.`
                            : 'Masuk untuk mengelola kegiatan dan tim lapangan.'}
                    </p>
                </div>
            </div>

            {status && (
                <div className="mt-4 rounded-xl border border-success/20 bg-success/10 px-3.5 py-2.5 text-sm font-medium text-success">
                    {status}
                </div>
            )}

            {!showRoleSelection && roleSelection.errors.role_id && (
                <div className="mt-4 rounded-xl border border-danger/20 bg-danger/10 px-3.5 py-2.5 text-sm font-medium text-danger">
                    {roleSelection.errors.role_id}
                </div>
            )}

            {showRoleSelection ? (
                <form
                    onSubmit={submitRole}
                    className="auth-enter auth-enter-delay-2 mt-7 space-y-4"
                >
                    <Field label="Peran" error={roleSelection.errors.role_id}>
                        <Select
                            name="role_id"
                            value={roleSelection.data.role_id}
                            autoFocus
                            onChange={(e) =>
                                roleSelection.setData('role_id', e.target.value)
                            }
                        >
                            <option value="" disabled>
                                Pilih peran…
                            </option>
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Button
                        type="submit"
                        variant="primary"
                        className="group h-11 w-full rounded-lg"
                        disabled={
                            roleSelection.processing ||
                            !roleSelection.data.role_id
                        }
                    >
                        {roleSelection.processing
                            ? 'Memproses…'
                            : 'Pilih & Masuk'}
                        {!roleSelection.processing && (
                            <ArrowRight
                                aria-hidden="true"
                                className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5"
                            />
                        )}
                    </Button>

                    <p className="text-center text-xs text-muted">
                        Peran yang dipilih menentukan menu dan data yang
                        ditampilkan.
                    </p>
                </form>
            ) : (
                <form
                    onSubmit={submitLogin}
                    className="auth-enter auth-enter-delay-2 mt-7 space-y-4"
                    aria-busy={credentials.processing}
                >
                    <Field label="Email" error={credentials.errors.email}>
                        <div className="relative">
                            <Mail
                                aria-hidden="true"
                                className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                            />
                            <Input
                                type="email"
                                name="email"
                                value={credentials.data.email}
                                autoComplete="username"
                                autoFocus
                                placeholder="nama@perusahaan.com"
                                className="h-11 rounded-lg pl-10"
                                onChange={(e) =>
                                    credentials.setData('email', e.target.value)
                                }
                            />
                        </div>
                    </Field>

                    <Field label="Password" error={credentials.errors.password}>
                        <div className="relative">
                            <LockKeyhole
                                aria-hidden="true"
                                className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                            />
                            <Input
                                type="password"
                                name="password"
                                value={credentials.data.password}
                                autoComplete="current-password"
                                placeholder="Masukkan password"
                                className="h-11 rounded-lg pl-10"
                                onChange={(e) =>
                                    credentials.setData('password', e.target.value)
                                }
                            />
                        </div>
                    </Field>

                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-2 text-sm text-text">
                            <input
                                type="checkbox"
                                name="remember"
                                checked={credentials.data.remember}
                                onChange={(e) =>
                                    credentials.setData(
                                        'remember',
                                        e.target.checked || false,
                                    )
                                }
                                className="h-4 w-4 rounded border-border accent-primary"
                            />
                            Ingat saya
                        </label>
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-sm font-medium text-primary transition-colors hover:text-primary-hover"
                            >
                                Lupa password?
                            </Link>
                        )}
                    </div>

                    <Button
                        type="submit"
                        variant="primary"
                        className="group h-11 w-full rounded-lg shadow-none"
                        disabled={credentials.processing}
                    >
                        {credentials.processing ? (
                            'Memproses…'
                        ) : (
                            <>
                                Masuk
                                <ArrowRight
                                    aria-hidden="true"
                                    className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                />
                            </>
                        )}
                    </Button>
                </form>
            )}

            <p className="mt-6 flex items-start justify-center gap-2 text-center text-xs leading-5 text-muted">
                <ShieldCheck
                    aria-hidden="true"
                    className="mt-0.5 h-4 w-4 shrink-0 text-slate-400"
                />
                <span>
                    Akses dikelola administrator. Hubungi admin jika Anda belum
                    memiliki akun.
                </span>
            </p>
        </GuestLayout>
    );
}
