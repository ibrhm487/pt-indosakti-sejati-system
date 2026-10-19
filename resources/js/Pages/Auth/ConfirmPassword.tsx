import { Button, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Konfirmasi Password" />

            <h1 className="text-lg font-semibold tracking-tight text-text">
                Konfirmasi password
            </h1>
            <p className="mt-1 text-sm text-muted">
                Ini adalah area aman aplikasi. Silakan konfirmasi password Anda
                sebelum melanjutkan.
            </p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <Field label="Password" error={errors.password}>
                    <Input
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        autoFocus
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <Button
                    type="submit"
                    variant="primary"
                    className="w-full"
                    disabled={processing}
                >
                    {processing ? 'Memproses…' : 'Konfirmasi'}
                </Button>
            </form>
        </GuestLayout>
    );
}
