import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-page px-4 py-12 sm:px-6 lg:px-8">
            <div className="w-full max-w-md">
                <div className="mb-8 flex flex-col items-center text-center">
                    <Link href="/">
                        <div className="flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-primary to-primary-hover text-xl font-bold text-white shadow-lg shadow-primary/20">
                            IS
                        </div>
                    </Link>
                    <h2 className="mt-6 text-xl font-semibold tracking-tight text-text">
                        PT Indosakti Sejati
                    </h2>
                    <p className="mt-1 text-sm text-muted">
                        Sistem Operasional Event Sampling
                    </p>
                </div>

                <div className="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">
                    {children}
                </div>

                <p className="mt-6 text-center text-xs text-muted">
                    © {new Date().getFullYear()} PT Indosakti Sejati
                </p>
            </div>
        </div>
    );
}
