import { FlashMessages } from '@/Components/AppBits';
import DapodikPortal from '@/Components/DapodikPortal';
import WorkflowLegend from '@/Components/WorkflowLegend';
import WorkspaceBar from '@/Components/WorkspaceBar';
import { navForRole, type NavItem } from '@/lib/nav';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { LogOut, Menu, User as UserIcon, X } from 'lucide-react';
import { PropsWithChildren, useEffect, useRef, useState } from 'react';

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

    const initials =
        user?.name
            ?.split(' ')
            .map((n) => n[0])
            .slice(0, 2)
            .join('')
            .toUpperCase() ?? 'U';

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="flex items-center gap-3 rounded-xl border border-transparent px-2 py-1.5 transition-colors hover:border-slate-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
            >
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-bold text-white">
                    {initials}
                </span>
                <span className="hidden text-left leading-tight sm:block">
                    <span className="block text-sm font-medium text-slate-800">
                        {user?.name}
                    </span>
                    <span className="block text-xs text-slate-400">
                        {roleName ?? user?.email}
                    </span>
                </span>
            </button>

            {open && (
                <>
                    <div
                        className="fixed inset-0 z-40"
                        onClick={() => setOpen(false)}
                    />
                    <div className="absolute end-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200/80 bg-white py-1 shadow-xl shadow-slate-900/5 ring-1 ring-slate-900/5">
                        <div className="border-b border-slate-100 px-4 py-3">
                            <p className="truncate text-sm font-semibold text-slate-900">
                                {user?.name}
                            </p>
                            <p className="truncate text-xs text-slate-400">
                                {user?.email}
                            </p>
                            {roleName && (
                                <span className="mt-1.5 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                    {roleName}
                                </span>
                            )}
                        </div>
                        <div className="p-1">
                            <Link
                                href={route('profile.edit')}
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                            >
                                <UserIcon className="h-4 w-4 text-slate-400" />
                                Profil
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                onClick={() => setOpen(false)}
                                className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50"
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
            <div className="flex h-full flex-col bg-white">
                <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-4">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-sm font-bold text-white shadow-sm">
                        IS
                    </div>
                    <div className="min-w-0 leading-tight">
                        <p className="truncate text-[11px] font-bold uppercase tracking-wide text-slate-900">
                            PT Indosakti Sejati
                        </p>
                        <p className="truncate text-[10px] text-slate-400">
                            Sistem Operasional Event Sampling
                        </p>
                    </div>
                </div>

            <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                {groups.map((group, gi) => (
                    <div key={gi}>
                        {group.section && (
                            <p className="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
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
                                        'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                                        current(item)
                                            ? 'bg-indigo-50 text-indigo-700 shadow-sm ring-1 ring-indigo-100'
                                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
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

            <div className="border-t border-slate-200 p-3">
                {/* Workflow legend removed per request */}
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-slate-50">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 hidden w-64 border-r border-slate-200/80 lg:block">
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
                            className="absolute right-2 top-4 z-10 rounded-md p-1 text-slate-400 hover:text-slate-700"
                        >
                            <X className="h-5 w-5" />
                        </button>
                        {sidebar}
                    </div>
                </div>
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button
                            onClick={() => setOpen(true)}
                            className="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        >
                            <Menu className="h-5 w-5" />
                        </button>
                        <h2 className="truncate text-sm font-semibold text-slate-800">
                            {title}
                        </h2>
                    </div>
                    <div className="flex items-center gap-2">
                        {permissions.includes('fjp.view') && (
                            <DapodikPortal />
                        )}
                        <UserMenu
                            user={auth?.user}
                            roleName={auth?.user?.active_role?.name ?? null}
                        />
                    </div>
                </header>

                <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <FlashMessages />
                    <WorkspaceBar />
                    {children}
                </main>
            </div>
        </div>
    );
}
