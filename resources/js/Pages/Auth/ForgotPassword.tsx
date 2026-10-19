import { Button, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Lupa Password" />

            <h1 className="text-lg font-semibold tracking-tight text-text">
                Lupa password
            </h1>
            <p className="mt-1 text-sm text-muted">
                Masukkan email Anda dan kami akan mengirimkan tautan untuk
                mengatur ulang password.
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

                <Button
                    type="submit"
                    variant="primary"
                    className="w-full"
                    disabled={processing}
                >
                    {processing
                        ? 'Mengirim…'
                        : 'Kirim Tautan Reset Password'}
                </Button>
            </form>
        </GuestLayout>
    );
}
