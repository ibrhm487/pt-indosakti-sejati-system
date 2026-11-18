import {
    dismissToast,
    getToasts,
    pushToast,
    subscribe,
    type Toast,
    type ToastTone,
} from '@/lib/toast';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Info,
    X,
    XCircle,
} from 'lucide-react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';

const TONE_ICON: Record<ToastTone, typeof Info> = {
    success: CheckCircle2,
    warning: AlertTriangle,
    error: XCircle,
    info: Info,
};

const TONE_ACCENT: Record<ToastTone, string> = {
    success: 'border-success/30',
    warning: 'border-warning/30',
    error: 'border-danger/30',
    info: 'border-info/30',
};

const TONE_ICON_WRAPPER: Record<ToastTone, string> = {
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/10 text-warning',
    error: 'bg-danger/10 text-danger',
    info: 'bg-info/10 text-info',
};

function ToastCard({ toast }: { toast: Toast }) {
    const [paused, setPaused] = useState(false);
    const Icon = TONE_ICON[toast.tone];

    useEffect(() => {
        if (toast.duration === null || paused) {
            return;
        }

        const timer = window.setTimeout(
            () => dismissToast(toast.id),
            toast.duration,
        );

        return () => window.clearTimeout(timer);
    }, [toast.id, toast.duration, paused]);

    return (
        <div
            role={toast.tone === 'error' ? 'alert' : 'status'}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            className={cn(
                'pointer-events-auto flex w-full items-start gap-3 rounded-xl border bg-surface p-3.5 shadow-lg shadow-slate-900/10 animate-in fade-in slide-in-from-right-4 duration-200',
                TONE_ACCENT[toast.tone],
            )}
        >
            <span
                className={cn(
                    'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                    TONE_ICON_WRAPPER[toast.tone],
                )}
            >
                <Icon className="h-4 w-4" aria-hidden />
            </span>
            <p className="flex-1 pt-1 text-sm font-medium leading-snug text-text">
                {toast.message}
            </p>
            <button
                type="button"
                onClick={() => dismissToast(toast.id)}
                aria-label="Tutup notifikasi"
                className="-mr-1 -mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-page hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}

export default function Toaster() {
    const toasts = useSyncExternalStore(subscribe, getToasts, getToasts);
    const page = usePage();
    const lastSignature = useRef<string | null>(null);

    useEffect(() => {
        const flash = page.props.flash as
            | Partial<Record<ToastTone, string | null>>
            | undefined;

        const entries = (
            ['success', 'error', 'info', 'warning'] as ToastTone[]
        )
            .map((tone) => ({ tone, message: flash?.[tone] }))
            .filter(
                (entry): entry is { tone: ToastTone; message: string } =>
                    typeof entry.message === 'string' &&
                    entry.message.length > 0,
            );

        if (entries.length === 0) {
            lastSignature.current = null;

            return;
        }

        const signature = entries
            .map((entry) => `${entry.tone}:${entry.message}`)
            .join('|');

        if (signature === lastSignature.current) {
            return;
        }

        lastSignature.current = signature;
        entries.forEach((entry) => pushToast(entry.tone, entry.message));
    }, [page.props.flash, page.url, page.component]);

    if (toasts.length === 0) {
        return null;
    }

    return (
        <div
            aria-label="Notifikasi"
            className="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-end gap-2 px-4 sm:inset-x-auto sm:right-4 sm:w-full sm:max-w-sm sm:px-0 print:hidden"
        >
            {toasts.map((toast) => (
                <ToastCard key={toast.id} toast={toast} />
            ))}
        </div>
    );
}
