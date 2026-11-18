import { Badge, Button, Input, Modal } from '@/Components/ui';
import { formatNumber } from '@/lib/utils';
import {
    ExternalLink,
    GraduationCap,
    Loader2,
    MapPin,
    School,
    Search,
    SearchX,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';

type SchoolResult = {
    npsn: string;
    nama: string | null;
    jenjang: string | null;
    status_sekolah: string | null;
    provinsi: string | null;
    kabupaten: string | null;
    kecamatan: string | null;
};

type SchoolDetail = {
    npsn: string;
    school_name: string | null;
    jenjang: string | null;
    status_sekolah: string | null;
    provinsi: string | null;
    kabupaten: string | null;
    kecamatan: string | null;
    address: string | null;
    jumlah_siswa: number | null;
    maps_url: string | null;
};

/**
 * Global "Portal Dapodik" shortcut. Lets any authenticated user look up a
 * school straight from Kemendikdasmen without leaving the current page.
 */
export default function DapodikPortal() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SchoolResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [detail, setDetail] = useState<SchoolDetail | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) return;
        const q = query.trim();
        const controller = new AbortController();
        setError(null);
        if (q.length < 5) {
            setResults([]);
            setLoading(false);
            return;
        }

        const id = setTimeout(async () => {
            setLoading(true);
            try {
                const res = await fetch(
                    `${route('dapodik.search')}?q=${encodeURIComponent(q)}&jabodetabek=1&jenjang=SD&limit=200`,
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
                    return;
                }
                setResults(json.data ?? []);
            } catch {
                if (!controller.signal.aborted) {
                    setError(
                        'Layanan Dapodik tidak dapat dihubungi. Silakan coba lagi.',
                    );
                    setResults([]);
                }
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 300);

        return () => {
            clearTimeout(id);
            controller.abort();
        };
    }, [query, open]);

    const showDetail = async (npsn: string) => {
        setDetailLoading(true);
        setError(null);
        try {
            const res = await fetch(route('dapodik.show', npsn), {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();
            if (!res.ok) {
                setError(json.message ?? 'Detail sekolah tidak dapat dimuat.');
                setDetail(null);
                return;
            }
            setDetail(json.data as SchoolDetail);
        } catch {
            setError(
                'Layanan Dapodik tidak dapat dihubungi. Silakan coba lagi.',
            );
            setDetail(null);
        } finally {
            setDetailLoading(false);
        }
    };

    const close = () => {
        setOpen(false);
        setQuery('');
        setResults([]);
        setDetail(null);
        setError(null);
    };

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setOpen(true)}
            >
                <Search className="h-4 w-4" />
                <span className="hidden sm:inline">Portal Dapodik</span>
            </Button>

            <Modal
                open={open}
                onClose={close}
                size="lg"
                title="Portal Dapodik"
                description="Cari sekolah dasar (Negeri & Swasta) langsung dari portal Kemendikdasmen."
            >
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                    <Input
                        autoFocus
                        className="h-11 rounded-xl pl-10 pr-10"
                        placeholder="Nama sekolah atau NPSN (min. 5 karakter)…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    {loading && (
                        <Loader2 className="absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-primary" />
                    )}
                </div>

                <p className="mt-2 text-xs text-muted">
                    Hasil berasal dari portal Dapodik publik. Verifikasi kembali
                    sebelum dipakai; data simulasi demo diberi label tersendiri.
                </p>

                {error && (
                    <p
                        role="alert"
                        className="mt-3 rounded-xl border border-danger/20 bg-danger/10 px-3.5 py-2.5 text-sm text-danger"
                    >
                        {error}
                    </p>
                )}

                <div className="mt-3">
                    {loading && results.length === 0 && (
                        <div className="space-y-2">
                            {[0, 1, 2].map((row) => (
                                <div
                                    key={row}
                                    className="flex items-center gap-3 rounded-xl border border-border p-3"
                                >
                                    <div className="h-9 w-9 animate-pulse rounded-lg bg-page" />
                                    <div className="flex-1 space-y-1.5">
                                        <div className="h-3 w-1/2 animate-pulse rounded bg-page" />
                                        <div className="h-2.5 w-2/3 animate-pulse rounded bg-page" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {!loading &&
                        query.trim().length >= 3 &&
                        !error &&
                        results.length === 0 && (
                            <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border py-10 text-center">
                                <SearchX className="h-6 w-6 text-muted/60" />
                                <p className="text-sm font-medium text-muted">
                                    Tidak ada sekolah yang cocok
                                </p>
                                <p className="max-w-xs text-xs text-muted">
                                    Coba kata kunci lain atau periksa kembali
                                    penulisan NPSN.
                                </p>
                            </div>
                        )}

                    {results.length > 0 && (
                        <div className="max-h-72 space-y-1.5 overflow-y-auto pr-1">
                            {results.map((r) => (
                                <button
                                    key={r.npsn}
                                    type="button"
                                    onClick={() => showDetail(r.npsn)}
                                    className="flex w-full items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 text-left transition-colors hover:border-border hover:bg-page"
                                >
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
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
                                                r.kecamatan,
                                                r.kabupaten,
                                                r.provinsi,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1">
                                        {r.jenjang && (
                                            <Badge variant="info">
                                                {r.jenjang}
                                            </Badge>
                                        )}
                                        {r.status_sekolah && (
                                            <Badge variant="default">
                                                {r.status_sekolah}
                                            </Badge>
                                        )}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {detailLoading && (
                    <div className="mt-4 flex items-center gap-2 rounded-xl border border-border bg-page px-3.5 py-2.5 text-sm text-muted">
                        <Loader2 className="h-4 w-4 animate-spin" /> Memuat
                        detail…
                    </div>
                )}

                {detail && !detailLoading && (
                    <div className="mt-4 overflow-hidden rounded-xl border border-primary/10 bg-gradient-to-br from-primary/5 via-surface to-surface">
                        <div className="flex items-start justify-between gap-3 border-b border-primary/10 px-4 py-3.5">
                            <div className="flex items-start gap-3">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface text-primary shadow-sm ring-1 ring-primary/15">
                                    <GraduationCap className="h-5 w-5" />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold text-text">
                                        {detail.school_name}
                                    </p>
                                    <p className="font-mono text-xs text-muted">
                                        NPSN {detail.npsn}
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-wrap justify-end gap-1">
                                {detail.jenjang && (
                                    <Badge variant="info">
                                        {detail.jenjang}
                                    </Badge>
                                )}
                                {detail.status_sekolah && (
                                    <Badge variant="accent">
                                        {detail.status_sekolah}
                                    </Badge>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-2.5 px-4 py-3.5 text-xs text-text sm:grid-cols-2">
                            {detail.address && (
                                <p className="flex items-start gap-1.5 sm:col-span-2">
                                    <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted" />
                                    {detail.address}
                                </p>
                            )}
                            <p className="flex items-center gap-1.5">
                                <Users className="h-3.5 w-3.5 text-muted" />
                                {formatNumber(detail.jumlah_siswa)} siswa
                                (portal Dapodik)
                            </p>
                            <p className="flex items-center gap-1.5">
                                <GraduationCap className="h-3.5 w-3.5 text-muted" />
                                {[
                                    detail.kecamatan,
                                    detail.kabupaten,
                                    detail.provinsi,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>

                        {detail.maps_url && (
                            <div className="border-t border-primary/10 px-4 py-3">
                                <a
                                    href={detail.maps_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:text-primary hover:underline"
                                >
                                    <ExternalLink className="h-3.5 w-3.5" />
                                    Buka di Google Maps
                                </a>
                            </div>
                        )}
                    </div>
                )}
            </Modal>
        </>
    );
}
