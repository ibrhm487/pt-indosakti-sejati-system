import { FlashMessages } from '@/Components/AppBits';
import DapodikPortal from '@/Components/DapodikPortal';
import RoleSwitcher from '@/Components/RoleSwitcher';
import { navForRole, type NavItem } from '@/lib/nav';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, Menu, User as UserIcon, X } from 'lucide-react';
import { PropsWithChildren, useEffect, useRef, useState } from 'react';

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
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary text-xs font-bold text-white">
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
                                <span className="mt-1.5 inline-flex rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">
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
    const groups = navForRole(roleSlug, permissions);
    const [open, setOpen] = useState(false);

    const current = (item: NavItem) => {
        if (item.routeName === 'dashboard') {
            return route().current('dashboard');
        }
        if (item.match) {
            return route().current(item.match);
        }
        return route().current(item.routeName);
    };

    const sidebar = (
        <div className="flex h-full flex-col bg-surface">
            <div className="flex items-center gap-3 border-b border-border px-4 py-4">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-sm font-bold text-white shadow-sm">
                    IS
                </div>
                <div className="min-w-0 leading-tight">
                    <p className="truncate text-[11px] font-bold uppercase tracking-wide text-text">
                        PT Indosakti Sejati
                    </p>
                    <p className="truncate text-[10px] text-muted">
                        Event Operations Management
                    </p>
                </div>
            </div>

            {roleName && (
                <div className="border-b border-border px-4 py-3">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted">
                        Ruang Kerja
                    </p>
                    <p className="mt-0.5 truncate text-sm font-semibold text-primary">
                        {roleName}
                    </p>
                </div>
            )}

            <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                {groups.map((group, gi) => (
                    <div key={gi}>
                        {group.section && (
                            <p className="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-muted">
                                {group.section}
                            </p>
                        )}
                        <div className="space-y-1">
                            {group.items.map((item) => (
                                <Link
                                    key={`${item.routeName}-${item.label}`}
                                    href={route(item.routeName)}
                                    onClick={() => setOpen(false)}
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                        current(item)
                                            ? 'bg-primary text-white shadow-sm'
                                            : 'text-muted hover:bg-page hover:text-text',
                                    )}
                                >
                                    {item.icon}
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    </div>
                ))}
            </nav>

            <div className="border-t border-border p-3">
                <div className="flex items-center gap-3 rounded-lg px-2 py-2">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xs font-bold text-primary">
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
                </div>
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="mt-1 flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-danger transition-colors hover:bg-danger/10"
                >
                    <LogOut className="h-4 w-4" />
                    Keluar
                </Link>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-page">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 hidden w-64 border-r border-border lg:block">
                {sidebar}
            </aside>

            {/* Mobile drawer */}
            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div
                        className="absolute inset-0 bg-slate-900/50"
                        onClick={() => setOpen(false)}
                    />
                    <div className="absolute inset-y-0 left-0 w-64 shadow-xl">
                        <button
                            onClick={() => setOpen(false)}
                            aria-label="Tutup menu"
                            className="absolute right-2 top-4 z-10 rounded-md p-1 text-muted hover:text-text"
                        >
                            <X className="h-5 w-5" />
                        </button>
                        {sidebar}
                    </div>
                </div>
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-border bg-surface/95 px-4 backdrop-blur sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button
                            onClick={() => setOpen(true)}
                            aria-label="Buka menu"
                            className="rounded-md p-2 text-muted hover:bg-page lg:hidden"
                        >
                            <Menu className="h-5 w-5" />
                        </button>
                        <div className="min-w-0 leading-tight">
                            <h1 className="truncate text-sm font-semibold text-text">
                                {title}
                            </h1>
                            {roleName && (
                                <p className="hidden truncate text-xs text-muted sm:block">
                                    {roleName}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {permissions.includes('fjp.view') && (
                            <DapodikPortal />
                        )}
                        <RoleSwitcher />
                        <UserMenu
                            user={auth?.user}
                            roleName={roleName}
                        />
                    </div>
                </header>

                <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <FlashMessages />
                    {children}
                </main>
            </div>
        </div>
    );
}
