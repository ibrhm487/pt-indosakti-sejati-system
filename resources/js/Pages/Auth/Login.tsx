import { Button, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Masuk" />

            <h1 className="text-lg font-semibold tracking-tight text-text">
                Masuk
            </h1>
            <p className="mt-1 text-sm text-muted">
                Selamat datang kembali. Silakan masuk ke akun Anda.
            </p>

            {status && (
                <div className="mt-4 rounded-xl border border-success/20 bg-success/10 px-3.5 py-2.5 text-sm font-medium text-success">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-4">
                <Field label="Email" error={errors.email}>
                    <Input
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </Field>

                <Field label="Password" error={errors.password}>
                    <Input
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm text-text">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked || false)
                            }
                            className="h-4 w-4 rounded border-border"
                        />
                        Ingat saya
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm font-medium text-primary hover:text-primary"
                        >
                            Lupa password?
                        </Link>
                    )}
                </div>

                <Button
                    type="submit"
                    variant="primary"
                    className="w-full"
                    disabled={processing}
                >
                    {processing ? 'Memproses…' : 'Masuk'}
                </Button>
            </form>

            <p className="mt-6 text-center text-xs text-muted">
                Akun dibuat oleh administrator sistem.
            </p>
        </GuestLayout>
    );
}
