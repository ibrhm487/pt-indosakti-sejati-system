import { Button } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verifikasi Email" />

            <h1 className="text-lg font-semibold tracking-tight text-slate-900">
                Verifikasi email
            </h1>
            <p className="mt-1 text-sm text-slate-500">
                Terima kasih telah mendaftar. Sebelum memulai, silakan verifikasi
                alamat email Anda dengan mengeklik tautan yang baru saja kami
                kirimkan. Jika Anda tidak menerima emailnya, kami dengan senang
                hati akan mengirim ulang.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm font-medium text-emerald-700">
                    Tautan verifikasi baru telah dikirim ke alamat email yang
                    Anda berikan saat pendaftaran.
                </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-4">
                <Button
                    type="submit"
                    variant="primary"
                    className="w-full"
                    disabled={processing}
                >
                    {processing ? 'Mengirim…' : 'Kirim Ulang Email Verifikasi'}
                </Button>

                <div className="text-center">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-sm font-medium text-slate-500 hover:text-slate-700"
                    >
                        Keluar
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
