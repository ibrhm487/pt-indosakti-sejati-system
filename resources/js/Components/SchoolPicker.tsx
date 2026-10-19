import { Input } from '@/Components/ui';
import { Loader2, School, Search, SearchX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type SchoolResult = {
    npsn: string;
    nama: string | null;
    jenjang: string | null;
    status_sekolah: string | null;
    provinsi: string | null;
    kabupaten: string | null;
    kecamatan: string | null;
};

export type DapoSchoolDetail = {
    npsn: string;
    school_name: string | null;
    jenjang: string | null;
    status_sekolah: string | null;
    provinsi: string | null;
    kabupaten: string | null;
    kecamatan: string | null;
    address: string | null;
    jumlah_siswa: number | null;
    latitude: number | null;
    longitude: number | null;
    maps_url: string | null;
};

export default function SchoolPicker({
    onPick,
    onError,
}: {
    onPick: (detail: DapoSchoolDetail) => void;
    onError?: (message: string) => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SchoolResult[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [picking, setPicking] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const boxRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const q = query.trim();
        const controller = new AbortController();
        setError(null);
        if (q.length < 5) {
            setResults([]);
            setOpen(false);
            setLoading(false);
            return;
        }

        const id = setTimeout(async () => {
            setLoading(true);
            try {
                const res = await fetch(
                    `${route('dapodik.search')}?q=${encodeURIComponent(q)}&limit=100`,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                const json = await res.json();
                if (controller.signal.aborted) return;
                if (!res.ok) {
                    setError(
                        json.message ??
                            'Pencarian Dapodik gagal. Silakan coba lagi.',
                    );
                    setResults([]);
                    setOpen(true);
                    return;
                }
                setResults(json.data ?? []);
                setOpen(true);
            } catch {
                if (!controller.signal.aborted) {
                    setError(
                        'Layanan Dapodik tidak dapat dihubungi. Silakan coba lagi.',
                    );
                    setResults([]);
                    setOpen(true);
                }
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 300);

        return () => {
            clearTimeout(id);
            controller.abort();
        };
    }, [query]);

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (
                boxRef.current &&
                !boxRef.current.contains(e.target as Node)
            ) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const pick = async (result: SchoolResult) => {
        setPicking(true);
        try {
            const res = await fetch(route('dapodik.show', result.npsn), {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();
            if (!res.ok)
                throw new Error(
                    json.message ??
                        'Gagal mengambil detail sekolah dari Dapodik.',
                );
            onPick(json.data as DapoSchoolDetail);
            setQuery('');
            setResults([]);
            setOpen(false);
        } catch (exception) {
            const message =
                exception instanceof Error
                    ? exception.message
                    : 'Gagal mengambil detail sekolah dari Dapodik.';
            setError(message);
            onError?.(message);
        } finally {
            setPicking(false);
        }
    };

    return (
        <div ref={boxRef} className="relative">
            <div className="relative">
                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <Input
                    className="h-11 rounded-xl pl-10 pr-10"
                    placeholder="Cari sekolah di portal Dapodik (nama / NPSN)…"
                    value={query}
                    onChange={(e) => {
                        setError(null);
                        setQuery(e.target.value);
                    }}
                    onFocus={() => results.length > 0 && setOpen(true)}
                />
                {(loading || picking) && (
                    <Loader2 className="absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-primary" />
                )}
            </div>

            {error && (
                <p role="alert" className="mt-1.5 text-xs text-danger">
                    {error}
                </p>
            )}

            {open && results.length > 0 && (
                <div className="absolute z-20 mt-2 max-h-72 w-full space-y-0.5 overflow-y-auto rounded-xl border border-border bg-surface p-1 shadow-xl shadow-slate-900/5 ring-1 ring-border">
                    {results.map((r) => (
                        <button
                            key={r.npsn}
                            type="button"
                            onClick={() => pick(r)}
                            className="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition-colors hover:bg-page"
                        >
                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <School className="h-4 w-4" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium text-text">
                                    {r.nama}
                                </span>
                                <span className="mt-0.5 block truncate text-xs text-muted">
                                    <span className="font-mono">
                                        {r.npsn}
                                    </span>
                                    {' · '}
                                    {[
                                        r.jenjang,
                                        r.status_sekolah,
                                        r.kecamatan,
                                        r.kabupaten,
                                        r.provinsi,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </span>
                        </button>
                    ))}
                </div>
            )}

            {open &&
                !loading &&
                !error &&
                query.trim().length >= 3 &&
                results.length === 0 && (
                    <div className="absolute z-20 mt-2 flex w-full items-center gap-2 rounded-xl border border-border bg-surface px-3.5 py-3 text-sm text-muted shadow-xl shadow-slate-900/5 ring-1 ring-border">
                        <SearchX className="h-4 w-4 text-muted/60" />
                        Tidak ada sekolah yang cocok.
                    </div>
                )}
        </div>
    );
}
