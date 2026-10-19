import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type RoleOption = { id: number; slug: string; name: string };

export default function RoleSwitcher() {
    const { auth } = usePage().props as any;
    const roles: RoleOption[] = auth?.user?.roles ?? [];
    const active: RoleOption | null = auth?.user?.active_role ?? null;

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

    if (roles.length === 0) return null;

    const switchTo = (role: RoleOption) => {
        setOpen(false);
        if (active?.slug === role.slug) return;
        router.post(
            route('role.switch'),
            { role_id: role.id },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-haspopup="listbox"
                aria-expanded={open}
                className="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm font-medium text-text transition-colors hover:bg-page focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            >
                <span className="hidden text-xs font-normal text-muted sm:inline">
                    Peran aktif:
                </span>
                <span className="text-primary">{active?.name ?? '-'}</span>
                <ChevronsUpDown className="h-3.5 w-3.5 text-muted" />
            </button>

            {open && (
                <>
                    <div
                        className="fixed inset-0 z-40"
                        onClick={() => setOpen(false)}
                    />
                    <div
                        role="listbox"
                        aria-label="Pilih peran aktif"
                        className="absolute end-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-border bg-surface py-1 shadow-lg shadow-slate-900/10"
                    >
                        <p className="border-b border-border px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-muted">
                            Ganti Peran
                        </p>
                        {roles.map((role) => {
                            const isActive = active?.slug === role.slug;
                            return (
                                <button
                                    key={role.slug}
                                    type="button"
                                    role="option"
                                    aria-selected={isActive}
                                    onClick={() => switchTo(role)}
                                    className={
                                        'flex w-full items-center justify-between gap-2 px-3 py-2 text-sm transition-colors ' +
                                        (isActive
                                            ? 'bg-primary/10 font-semibold text-primary'
                                            : 'text-text hover:bg-page')
                                    }
                                >
                                    {role.name}
                                    {isActive && (
                                        <Check className="h-4 w-4 shrink-0" />
                                    )}
                                </button>
                            );
                        })}
                        <p className="border-t border-border px-3 py-2 text-[11px] leading-snug text-muted">
                            Menu dan data mengikuti peran aktif.
                        </p>
                    </div>
                </>
            )}
        </div>
    );
}
