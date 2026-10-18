import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-12 sm:px-6 lg:px-8">
            <div className="w-full max-w-md">
                <div className="mb-8 flex flex-col items-center text-center">
                    <Link href="/">
                        <div className="flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-indigo-500 to-violet-600 text-xl font-bold text-white shadow-lg shadow-indigo-500/20">
                            IS
                        </div>
                    </Link>
                    <h2 className="mt-6 text-xl font-semibold tracking-tight text-slate-900">
                        PT Indosakti Sejati
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Sistem Operasional Event Sampling
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                    {children}
                </div>

                <p className="mt-6 text-center text-xs text-slate-400">
                    © {new Date().getFullYear()} PT Indosakti Sejati
                </p>
            </div>
        </div>
    );
}
