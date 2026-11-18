import { router } from '@inertiajs/react';

export type ToastTone = 'success' | 'error' | 'info' | 'warning';

export type Toast = {
    id: number;
    tone: ToastTone;
    message: string;
    duration: number | null;
};

const DEFAULT_DURATION: Record<ToastTone, number | null> = {
    success: 4000,
    info: 4000,
    warning: 6000,
    error: null,
};

let toasts: Toast[] = [];
let nextId = 1;

const listeners = new Set<() => void>();

function emit() {
    listeners.forEach((listener) => listener());
}

export function pushToast(
    tone: ToastTone,
    message: string,
    options?: { duration?: number | null },
): number {
    const id = nextId++;
    const duration = options?.duration ?? DEFAULT_DURATION[tone];

    toasts = [...toasts, { id, tone, message, duration }];
    emit();

    return id;
}

export function dismissToast(id: number) {
    const next = toasts.filter((toast) => toast.id !== id);

    if (next.length === toasts.length) {
        return;
    }

    toasts = next;
    emit();
}

export function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function getToasts(): Toast[] {
    return toasts;
}

export function installGlobalToastListeners() {
    router.on('error', (event) => {
        const errors = Object.values(event.detail.errors ?? {}).filter(
            (message): message is string => typeof message === 'string' && message.length > 0,
        );
        const count = errors.length;

        if (count === 0) {
            return;
        }

        pushToast(
            'warning',
            errors[0] ?? (count === 1
                ? 'Periksa kembali isian yang ditandai.'
                : `Periksa kembali ${count} isian yang ditandai.`),
        );
    });

    router.on('httpException', (event) => {
        const status = event.detail.response.status;

        if (import.meta.env.DEV) {
            return;
        }

        event.preventDefault();
        pushToast(...httpExceptionToast(status));
    });

    router.on('networkError', (event) => {
        event.preventDefault();
        pushToast(
            'error',
            'Koneksi terputus. Periksa jaringan Anda lalu coba lagi.',
        );
    });
}

function httpExceptionToast(status: number): [ToastTone, string] {
    switch (status) {
        case 403:
            return ['warning', 'Anda tidak memiliki izin untuk melakukan tindakan ini.'];
        case 404:
            return ['info', 'Data yang Anda cari tidak ditemukan.'];
        case 419:
            return [
                'warning',
                'Sesi Anda telah berakhir. Muat ulang halaman untuk melanjutkan.',
            ];
        case 429:
            return [
                'warning',
                'Terlalu banyak percobaan. Silakan coba lagi nanti.',
            ];
    }

    if (status >= 500) {
        return ['error', 'Terjadi kesalahan pada server. Silakan coba lagi.'];
    }

    return ['error', `Terjadi kesalahan (kode ${status}).`];
}
