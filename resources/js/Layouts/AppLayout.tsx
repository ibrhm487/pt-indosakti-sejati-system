import DapodikPortal from '@/Components/DapodikPortal';
import SidebarNav from '@/Components/SidebarNav';
import Toaster from '@/Components/Toaster';
import { navForRole } from '@/lib/nav';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import {
    ChevronsUpDown,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    User as UserIcon,
    X,
} from 'lucide-react';
import { PropsWithChildren, useEffect, useRef, useState } from 'react';

const SIDEBAR_STORAGE_KEY = 'indosakti.sidebar.collapsed';
const BRAND_LOGO = '/images/indosakti-logo.png';

function initialsOf(name?: string) {
    return (
        name
            ?.split(' ')
            .map((n) => n[0])
            .slice(0, 2)
            .join('')
            .toUpperCase() ?? 'U'
    );
}

function UserMenu({
    user,
    roleName,
}: {
    user?: { name?: string; email?: string };
    roleName?: string | null;
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onClick = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-label="Menu pengguna"
                className="flex items-center gap-3 rounded-xl border border-transparent px-2 py-1.5 transition-colors hover:border-border hover:bg-page focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            >
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-primary-hover text-xs font-bold text-white shadow-sm shadow-brand/20">
                    {initialsOf(user?.name)}
                </span>
                <span className="hidden text-left leading-tight sm:block">
                    <span className="block max-w-[10rem] truncate text-sm font-medium text-text">
                        {user?.name}
                    </span>
                    <span className="block text-xs text-muted">
                        {roleName ?? user?.email}
                    </span>
                </span>
                <ChevronsUpDown className="hidden h-3.5 w-3.5 text-muted sm:block" />
            </button>

            {open && (
                <>
                    <div
                        className="fixed inset-0 z-40"
                        onClick={() => setOpen(false)}
                    />
                    <div className="absolute end-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-border bg-surface py-1 shadow-xl shadow-slate-900/10">
                        <div className="border-b border-border px-4 py-3">
                            <p className="truncate text-sm font-semibold text-text">
                                {user?.name}
                            </p>
                            <p className="truncate text-xs text-muted">
                                {user?.email}
                            </p>
                            {roleName && (
                                <span className="mt-1.5 inline-flex rounded-full bg-brand-soft px-2 py-0.5 text-[11px] font-semibold text-primary">
                                    {roleName}
                                </span>
                            )}
                        </div>
                        <div className="p-1">
                            <Link
                                href={route('profile.edit')}
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-text transition-colors hover:bg-page"
                            >
                                <UserIcon className="h-4 w-4 text-muted" />
                                Profil
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                onClick={() => setOpen(false)}
                                className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-danger transition-colors hover:bg-danger/10"
                            >
                                <LogOut className="h-4 w-4" />
                                Keluar
                            </Link>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

export default function AppLayout({
    title,
    children,
}: PropsWithChildren<{ title?: string }>) {
    const page = usePage();
    const { auth } = page.props;
    const permissions = auth?.permissions ?? [];
    const roleSlug = auth?.user?.active_role?.slug ?? null;
    const roleName = auth?.user?.active_role?.name ?? null;
    const eventBrands = (page.props as any).eventBrands ?? [];
    const groups = navForRole(roleSlug, permissions, eventBrands);
    const [open, setOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1',
    );
    const closeButtonRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);
        closeButtonRef.current?.focus();

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    const toggleCollapsed = () => {
        setCollapsed((value) => {
            const next = !value;
            window.localStorage.setItem(
                SIDEBAR_STORAGE_KEY,
                next ? '1' : '0',
            );

            return next;
        });
    };

    const sidebarContent = (isCollapsed: boolean, isMobile = false) => {
        const closeMobile = () => setOpen(false);

        return (
            <div className="flex h-full flex-col bg-surface">
                <div
                    className={cn(
                        'flex h-16 shrink-0 items-center border-b border-border',
                        isCollapsed
                            ? 'justify-center px-2'
                            : 'gap-3 px-4',
                    )}
                >
                    <Link
                        href={route('dashboard')}
                        onClick={isMobile ? closeMobile : undefined}
                        aria-label="PT Indosakti Sejati"
                        className="flex min-w-0 items-center"
                    >
                        {isCollapsed ? (
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft text-sm font-extrabold tracking-tight text-primary">
                                IS
                            </span>
                        ) : (
                            <img
                                src={BRAND_LOGO}
                                alt="PT Indosakti Sejati"
                                className="h-9 w-auto max-w-[10.5rem] object-contain"
                            />
                        )}
                    </Link>
                    {!isCollapsed && isMobile && (
                        <button
                            ref={closeButtonRef}
                            type="button"
                            onClick={closeMobile}
                            aria-label="Tutup menu"
                            className="ms-auto rounded-lg p-2 text-muted transition-colors hover:bg-page hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    )}
                </div>

                {roleName && (
                    <div
                        className={cn(
                            'shrink-0 border-b border-border',
                            isCollapsed ? 'px-2 py-3' : 'px-4 py-3',
                        )}
                    >
                        {isCollapsed ? (
                            <div
                                title={roleName}
                                className="mx-auto flex h-9 w-9 items-center justify-center rounded-xl bg-brand-soft text-[10px] font-bold uppercase text-primary"
                            >
                                {initialsOf(roleName)}
                            </div>
                        ) : (
                            <div className="rounded-xl border border-brand/15 bg-brand-soft/70 px-3 py-2.5">
                                <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">
                                    Ruang Kerja
                                </p>
                                <p className="mt-0.5 truncate text-sm font-semibold text-primary">
                                    {roleName}
                                </p>
                            </div>
                        )}
                    </div>
                )}

                <nav
                    aria-label="Navigasi utama"
                    className={cn(
                        'flex-1 overflow-y-auto py-4',
                        isCollapsed ? 'px-2' : 'px-3',
                    )}
                >
                    <SidebarNav
                        groups={groups}
                        collapsed={isCollapsed}
                        mobile={isMobile}
                        currentUrl={page.url}
                        onNavigate={isMobile ? closeMobile : undefined}
                    />
                </nav>

                <div
                    className={cn(
                        'shrink-0 border-t border-border',
                        isCollapsed ? 'p-2' : 'p-3',
                    )}
                >
                    {isCollapsed ? (
                        <div className="flex flex-col items-center gap-1.5">
                            <Link
                                href={route('profile.edit')}
                                title="Profil"
                                className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft text-xs font-bold text-primary transition-colors hover:bg-brand/15"
                            >
                                {initialsOf(auth?.user?.name)}
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                title="Keluar"
                                className="flex h-9 w-10 items-center justify-center rounded-lg text-danger transition-colors hover:bg-danger/10"
                            >
                                <LogOut className="h-4 w-4" />
                            </Link>
                        </div>
                    ) : (
                        <>
                            <Link
                                href={route('profile.edit')}
                                onClick={isMobile ? closeMobile : undefined}
                                className="group flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-page"
                            >
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-primary-hover text-xs font-bold text-white shadow-sm shadow-brand/20">
                                    {initialsOf(auth?.user?.name)}
                                </span>
                                <div className="min-w-0 leading-tight">
                                    <p className="truncate text-sm font-medium text-text">
                                        {auth?.user?.name}
                                    </p>
                                    <p className="truncate text-xs text-muted">
                                        {roleName ?? auth?.user?.email}
                                    </p>
                                </div>
                                <ChevronsUpDown className="ms-auto h-3.5 w-3.5 text-muted opacity-0 transition-opacity group-hover:opacity-100" />
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                onClick={isMobile ? closeMobile : undefined}
                                className="mt-1 flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-danger transition-colors hover:bg-danger/10"
                            >
                                <LogOut className="h-4 w-4" />
                                Keluar
                            </Link>
                        </>
                    )}
                </div>
            </div>
        );
    };

    return (
        <div className="min-h-screen bg-page">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-primary focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary/30 print:hidden"
            >
                Lewati ke konten
            </a>

            {/* Desktop sidebar */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-30 hidden border-r border-border bg-surface transition-[width] duration-300 lg:block print:hidden',
                    collapsed ? 'w-20' : 'w-64',
                )}
            >
                {sidebarContent(collapsed)}
            </aside>

            {/* Mobile drawer */}
            {open && (
                <div className="fixed inset-0 z-50 lg:hidden print:hidden">
                    <div
                        className="absolute inset-0 bg-slate-950/50 backdrop-blur-[2px]"
                        onClick={() => setOpen(false)}
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label="Menu navigasi"
                        className="absolute inset-y-0 left-0 w-72 max-w-[88vw] bg-surface shadow-2xl"
                    >
                        {sidebarContent(false, true)}
                    </div>
                </div>
            )}

            <div
                className={cn(
                    'transition-[padding] duration-300 print:pl-0',
                    collapsed ? 'lg:pl-20' : 'lg:pl-64',
                )}
            >
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-border bg-surface/95 px-4 shadow-sm backdrop-blur sm:px-6 print:hidden">
                    <div className="flex min-w-0 items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setOpen(true)}
                            aria-label="Buka menu"
                            className="rounded-lg p-2 text-muted transition-colors hover:bg-page hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 lg:hidden"
                        >
                            <Menu className="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            onClick={toggleCollapsed}
                            aria-label={
                                collapsed
                                    ? 'Perluas sidebar'
                                    : 'Ciutkan sidebar'
                            }
                            aria-pressed={collapsed}
                            className="hidden rounded-lg p-2 text-muted transition-colors hover:bg-page hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 lg:inline-flex"
                        >
                            {collapsed ? (
                                <PanelLeftOpen className="h-5 w-5" />
                            ) : (
                                <PanelLeftClose className="h-5 w-5" />
                            )}
                        </button>
                        <div className="min-w-0 leading-tight">
                            <div className="flex items-center gap-2">
                                <span className="hidden h-1.5 w-1.5 shrink-0 rounded-full bg-brand sm:block" />
                                <h1 className="truncate text-sm font-semibold text-text">
                                    {title}
                                </h1>
                            </div>
                            <p className="hidden truncate text-xs text-muted sm:block">
                                {roleName
                                    ? `Ruang kerja ${roleName}`
                                    : 'PT Indosakti Sejati'}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {permissions.includes('fjp.view') && (
                            <DapodikPortal />
                        )}
                        <UserMenu user={auth?.user} roleName={roleName} />
                    </div>
                </header>

                <main
                    id="main-content"
                    className="mx-auto max-w-7xl scroll-mt-20 px-4 py-6 sm:px-6 lg:px-8 print:max-w-none print:px-0 print:py-0"
                >
                    <Toaster />
                    {children}
                </main>
            </div>
        </div>
    );
}
