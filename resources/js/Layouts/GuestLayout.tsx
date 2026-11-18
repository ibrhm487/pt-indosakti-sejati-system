import Toaster from '@/Components/Toaster';
import { Link } from '@inertiajs/react';
import { ArrowDownRight } from 'lucide-react';
import { PropsWithChildren } from 'react';

function BrandLogo({ compact = false }: { compact?: boolean }) {
    return (
        <Link
            href="/"
            aria-label="PT Indosakti Sejati"
            className={`inline-flex items-center bg-white transition-shadow hover:shadow-md ${
                compact ? 'rounded-xl px-3.5 py-2' : 'rounded-xl px-4 py-2.5'
            }`}
        >
            <img
                src="/images/indosakti-logo.png"
                alt="PT Indosakti Sejati"
                className={`w-auto object-contain ${
                    compact ? 'h-9 max-w-44' : 'h-10 max-w-52'
                }`}
            />
        </Link>
    );
}

const workflowSteps = [
    { number: '01', title: 'Rencanakan kegiatan', detail: 'Jadwal dan kebutuhan' },
    { number: '02', title: 'Koordinasikan tim', detail: 'PIC dan tim lapangan' },
    { number: '03', title: 'Tinjau hasil', detail: 'Laporan dan dokumentasi' },
];

export default function Guest({ children }: PropsWithChildren) {
    const currentYear = new Date().getFullYear();

    return (
        <div className="min-h-screen bg-[#f4f3f0] text-[#202124]">
            <Toaster />

            <div className="grid min-h-screen lg:grid-cols-[1.08fr_0.92fr]">
                <aside className="relative hidden min-h-screen flex-col overflow-hidden bg-[#202124] px-12 py-9 text-white lg:flex xl:px-16">
                    <div className="absolute left-0 top-0 h-1 w-full bg-[#d91e2a]" />

                    <div className="auth-enter relative z-10">
                        <BrandLogo />
                    </div>

                    <div className="relative z-10 my-auto max-w-xl py-16">
                        <div className="auth-enter flex items-center gap-3 text-[11px] font-semibold tracking-[0.18em] text-white/55 uppercase">
                            <span className="h-px w-8 bg-[#d9a441]" />
                            Ruang kerja operasional
                        </div>

                        <h2 className="auth-enter auth-enter-delay-1 mt-7 max-w-lg text-[2.65rem] leading-[1.13] font-medium tracking-[-0.045em] xl:text-5xl">
                            Rapi sejak rencana.
                            <span className="mt-1 block text-[#ef777c]">
                                Terukur sampai laporan.
                            </span>
                        </h2>

                        <p className="auth-enter auth-enter-delay-2 mt-5 max-w-md text-sm leading-6 text-white/60">
                            Satu alur kerja untuk menyiapkan kegiatan sampling,
                            mengoordinasikan tim, dan meninjau hasil lapangan.
                        </p>

                        <div className="mt-11 max-w-md border-t border-white/10">
                            {workflowSteps.map((step, index) => (
                                <div
                                    key={step.number}
                                    className={`auth-enter flex items-center gap-4 border-b border-white/10 py-4 ${
                                        index === 0
                                            ? 'auth-enter-delay-2'
                                            : index === 1
                                              ? 'auth-enter-delay-3'
                                              : 'auth-enter-delay-4'
                                    }`}
                                >
                                    <span className="w-8 shrink-0 font-mono text-xs text-[#e2b75e]">
                                        {step.number}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-white/90">
                                            {step.title}
                                        </p>
                                        <p className="mt-0.5 text-xs text-white/45">
                                            {step.detail}
                                        </p>
                                    </div>
                                    {index === workflowSteps.length - 1 && (
                                        <ArrowDownRight
                                            aria-hidden="true"
                                            className="h-4 w-4 text-white/35"
                                        />
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="relative z-10 flex items-center justify-between border-t border-white/10 pt-5 text-[11px] text-white/40">
                        <span>PT Indosakti Sejati</span>
                        <span>© {currentYear}</span>
                    </div>
                </aside>

                <main className="flex min-h-screen items-center justify-center px-5 py-8 sm:px-8 lg:px-12">
                    <div className="w-full max-w-[27rem]">
                        <div className="auth-enter mb-7 flex items-center justify-between gap-4 lg:hidden">
                            <BrandLogo compact />
                            <span className="text-right text-[10px] leading-4 font-medium tracking-[0.12em] text-slate-500 uppercase">
                                Sistem Operasional
                                <br />
                                Event Sampling
                            </span>
                        </div>

                        <div className="auth-enter auth-enter-delay-1 border-t-2 border-[#d91e2a] bg-white px-6 py-7 shadow-[0_18px_50px_-36px_rgba(32,33,36,0.35)] sm:px-9 sm:py-9">
                            {children}
                        </div>

                        <p className="mt-5 text-center text-[11px] text-slate-400 lg:text-slate-500">
                            Akses internal · PT Indosakti Sejati
                        </p>
                    </div>
                </main>
            </div>
        </div>
    );
}
