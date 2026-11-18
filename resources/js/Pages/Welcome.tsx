import Toaster from '@/Components/Toaster';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardList,
    Package,
    ShieldCheck,
    UtensilsCrossed,
    UsersRound,
    ArrowRight,
    CheckCircle2,
} from 'lucide-react';

const FEATURES = [
    {
        icon: CalendarDays,
        title: 'Manajemen Event',
        desc: 'Jadwalkan dan pantau event sampling dari perencanaan hingga selesai.',
    },
    {
        icon: ClipboardList,
        title: 'Formulir FJP',
        desc: 'Kelola Formulir Jadwal Pelaksanaan sekolah beserta status perizinannya.',
    },
    {
        icon: ShieldCheck,
        title: 'Perizinan',
        desc: 'Alur pengajuan dan persetujuan surat izin yang transparan.',
    },
    {
        icon: Package,
        title: 'Gudang & Stok',
        desc: 'Pantau stok, barang masuk/keluar, dan permintaan dari PIC.',
    },
    {
        icon: UtensilsCrossed,
        title: 'Kitchen',
        desc: 'Jadwal produksi dapur dengan bukti foto masak dan cuci jug.',
    },
    {
        icon: UsersRound,
        title: 'Tim Lapangan',
        desc: 'Pembagian tim, absensi, dan laporan harian dalam satu tempat.',
    },
];

const STEPS = ['Permitter', 'PIC', 'Tim & Gudang', 'Kitchen'];

export default function Welcome({
    auth,
}: PageProps<{ laravelVersion: string; phpVersion: string }>) {
    return (
        <>
            <Head title="Beranda" />
            <Toaster />
            <div className="min-h-screen bg-page text-text antialiased">
                {/* Header */}
                <header className="sticky top-0 z-30 border-b border-border/70 bg-surface/80 backdrop-blur">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <Link
                                href="/"
                                aria-label="PT Indosakti Sejati"
                                className="flex items-center"
                            >
                                <img
                                    src="/images/indosakti-logo.png"
                                    alt="PT Indosakti Sejati"
                                    className="h-9 w-auto max-w-[10rem] object-contain sm:max-w-[12rem]"
                                />
                            </Link>
                        </div>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-hover"
                                >
                                    Buka Dashboard
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            ) : (
                                <Link
                                    href={route('login')}
                                    className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-hover"
                                >
                                    Masuk
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <section className="relative overflow-hidden">
                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-primary/5 to-transparent" />
                    <div className="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
                        <div className="mx-auto max-w-2xl text-center">
                            <span className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-surface px-3 py-1 text-xs font-medium text-primary shadow-sm">
                                <span className="h-1.5 w-1.5 rounded-full bg-primary/100" />
                                Sistem Operasional Event Sampling
                            </span>
                            <h1 className="mt-6 text-4xl font-bold tracking-tight text-text sm:text-5xl">
                                Kelola event sampling end-to-end dalam satu
                                platform
                            </h1>
                            <p className="mt-6 text-lg text-text">
                                Dari perizinan sekolah, jadwal tim lapangan,
                                gudang, hingga kitchen — semuanya terhubung dan
                                terpantau secara real time.
                            </p>
                            <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                                <Link
                                    href={
                                        auth.user
                                            ? route('dashboard')
                                            : route('login')
                                    }
                                    className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition-colors hover:bg-primary-hover"
                                >
                                    {auth.user
                                        ? 'Ke Dashboard'
                                        : 'Masuk ke Sistem'}
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Workflow */}
                <section className="mx-auto max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                        <p className="mb-3 text-center text-xs font-semibold uppercase tracking-wider text-muted">
                            Alur Kerja Operasional
                        </p>
                        <div className="flex flex-wrap items-center justify-center gap-2">
                            {STEPS.map((step, index) => (
                                <span key={step} className="flex items-center">
                                    <span className="rounded-lg bg-page px-3 py-1.5 text-sm font-medium text-text ring-1 ring-border">
                                        {step}
                                    </span>
                                    {index < STEPS.length - 1 && (
                                        <ArrowRight className="mx-1 h-4 w-4 text-muted/60" />
                                    )}
                                </span>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-3xl font-bold tracking-tight text-text">
                            Semua yang Anda butuhkan
                        </h2>
                        <p className="mt-3 text-text">
                            Modul-modul yang saling terhubung untuk menjalankan
                            operasional event sampling tanpa ribet.
                        </p>
                    </div>
                    <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map((f) => (
                            <div
                                key={f.title}
                                className="rounded-2xl border border-border bg-surface p-6 shadow-sm transition-shadow hover:shadow-md"
                            >
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <f.icon className="h-5 w-5" />
                                </div>
                                <h3 className="mt-4 text-base font-semibold text-text">
                                    {f.title}
                                </h3>
                                <p className="mt-1.5 text-sm text-muted">
                                    {f.desc}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* CTA */}
                <section className="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-3xl bg-gradient-to-br from-primary to-primary-hover px-6 py-14 text-center shadow-xl shadow-primary/20 sm:px-12">
                        <h2 className="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            Siap mengoptimalkan operasional event Anda?
                        </h2>
                        <p className="mx-auto mt-3 max-w-xl text-white/70">
                            Masuk untuk mulai mengelola seluruh siklus event
                            sampling. Akun dibuat oleh administrator sistem.
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <Link
                                href={
                                    auth.user
                                        ? route('dashboard')
                                        : route('login')
                                }
                                className="inline-flex items-center gap-2 rounded-xl bg-surface px-6 py-3 text-sm font-semibold text-primary shadow-sm transition-colors hover:bg-primary/10"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                {auth.user ? 'Ke Dashboard' : 'Masuk'}
                            </Link>
                        </div>
                    </div>
                </section>

                <footer className="border-t border-border/70 bg-surface">
                    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                            <div className="flex items-center gap-3">
                                <img
                                    src="/images/indosakti-logo.png"
                                    alt="PT Indosakti Sejati"
                                    className="h-7 w-auto max-w-[8rem] object-contain"
                                />
                                <p className="text-sm text-muted">
                                    © {new Date().getFullYear()} PT Indosakti
                                    Sejati
                                </p>
                            </div>
                            <p className="text-xs text-muted">
                                Sistem Operasional Event Sampling
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
