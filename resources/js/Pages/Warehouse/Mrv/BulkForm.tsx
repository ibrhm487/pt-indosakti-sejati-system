import { PageHeader } from '@/Components/AppBits';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Field,
    Input,
    Textarea,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatNumber } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Package, Users } from 'lucide-react';
import { FormEvent } from 'react';

type LotOption = {
    lot_code: string;
    quantity: number;
    expired_date: string | null;
    is_expired: boolean;
};

type Product = {
    id: number;
    name: string;
    sku: string;
    unit: string;
    lots: LotOption[];
    active_quantity: number;
    expired_quantity: number;
};

type School = {
    event_id: number;
    name: string;
    venue: string | null;
    student_count: number | null;
};

type TeamGroup = {
    id: string;
    sequence: number;
    date: string | null;
    total_students: number;
    ready: boolean;
    schools: School[];
    members: { user_id: number; name: string }[];
};

type LotAllocation = {
    lot_code: string;
    quantity: number;
};

type BulkMrvData = {
    activity_date: string;
    team_group_ids: string[];
    notes: string;
};

function allocateLots(groups: TeamGroup[], lots: LotOption[]): Record<string, LotAllocation[]> {
    const remainingLots = lots.map((lot) => ({ ...lot, remaining: lot.quantity }));
    const allocations: Record<string, LotAllocation[]> = {};

    groups.forEach((group) => {
        let remainingQuantity = group.total_students;
        const groupAllocations: LotAllocation[] = [];

        remainingLots.forEach((lot) => {
            if (remainingQuantity <= 0 || lot.remaining <= 0) {
                return;
            }

            const quantity = Math.min(remainingQuantity, lot.remaining);
            groupAllocations.push({ lot_code: lot.lot_code, quantity });
            lot.remaining -= quantity;
            remainingQuantity -= quantity;
        });

        allocations[group.id] = groupAllocations;
    });

    return allocations;
}

function lotSummary(allocations: LotAllocation[] | undefined, lots: LotOption[]): string {
    if (!allocations?.length) {
        return 'Lot belum tersedia';
    }

    const expiredCodes = new Set(lots.filter((lot) => lot.is_expired).map((lot) => lot.lot_code));

    return allocations
        .map((allocation) => {
            const marker = expiredCodes.has(allocation.lot_code) ? ' · kedaluwarsa' : '';

            return `${allocation.lot_code} (${formatNumber(allocation.quantity)}${marker})`;
        })
        .join(' · ');
}

export default function BulkMrvForm({
    activityDate,
    teamGroups,
    samplingProduct,
    supportingProduct,
}: {
    activityDate: string;
    teamGroups: TeamGroup[];
    samplingProduct: Product | null;
    supportingProduct: Product | null;
}) {
    const form = useForm<BulkMrvData>({
        activity_date: activityDate,
        team_group_ids: [],
        notes: '',
    });
    const dateGroups = teamGroups.filter((group) => group.date === form.data.activity_date);
    const selectedGroups = [...form.data.team_group_ids]
        .map((groupId) => dateGroups.find((group) => group.id === groupId))
        .filter((group): group is TeamGroup => Boolean(group))
        .sort((left, right) => left.sequence - right.sequence);
    const totalStudents = selectedGroups.reduce((total, group) => total + group.total_students, 0);
    const samplingLotsByTeam = allocateLots(selectedGroups, samplingProduct?.lots ?? []);
    const supportingLotsByTeam = allocateLots(selectedGroups, supportingProduct?.lots ?? []);
    const samplingAvailable = (samplingProduct?.lots ?? []).reduce((total, lot) => total + lot.quantity, 0);
    const supportingAvailable = (supportingProduct?.lots ?? []).reduce((total, lot) => total + lot.quantity, 0);
    const productsAvailable = Boolean(samplingProduct && supportingProduct);
    const enoughStock = totalStudents > 0
        && samplingAvailable >= totalStudents
        && supportingAvailable >= totalStudents;
    const usesExpiredLots = [
        { allocations: samplingLotsByTeam, product: samplingProduct },
        { allocations: supportingLotsByTeam, product: supportingProduct },
    ].some(({ allocations, product }) =>
        Object.values(allocations).some((teamAllocations) =>
            teamAllocations.some((allocation) =>
                product?.lots.some((lot) => lot.lot_code === allocation.lot_code && lot.is_expired),
            ),
        ),
    );

    const setSelectedTeamGroups = (teamGroupIds: string[]) => {
        form.setData('team_group_ids', teamGroupIds);
    };

    const toggleTeamGroup = (groupId: string, checked: boolean) => {
        const teamGroupIds = checked
            ? [...form.data.team_group_ids, groupId]
            : form.data.team_group_ids.filter((id) => id !== groupId);

        setSelectedTeamGroups(teamGroupIds);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('warehouse.mrv.bulk.store'));
    };

    return (
        <AppLayout title="MRV Banyak Tim">
            <Head title="MRV Banyak Tim" />
            <PageHeader
                title="MRV Milo School Sampling · Banyak Tim"
                subtitle="Ajukan MILO 3IN1 ACTIV-GO dan MILO CUP 4OZ untuk beberapa tim pada satu tanggal."
            >
                <Link href={route('warehouse.mrv.index')}>
                    <Button type="button" variant="outline">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke MRV
                    </Button>
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-4 w-4 text-primary" />
                            Pilih tanggal dan tim FJP Confirm
                        </CardTitle>
                        <p className="text-sm text-muted">
                            Sekolah dalam satu tim digabung ke satu MRV. Jumlah kedua produk otomatis mengikuti total siswa semua sekolah tim tersebut.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Field label="Tanggal kegiatan" required error={form.errors.activity_date}>
                            <Input
                                type="date"
                                value={form.data.activity_date}
                                onChange={(event) => form.setData({
                                    ...form.data,
                                    activity_date: event.target.value,
                                    team_group_ids: [],
                                })}
                                required
                            />
                        </Field>

                        {dateGroups.some((group) => group.ready) && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setSelectedTeamGroups(dateGroups
                                        .filter((group) => group.ready)
                                        .map((group) => group.id))}
                                >
                                    Pilih semua tim di tanggal ini
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSelectedTeamGroups([])}
                                >
                                    Kosongkan pilihan
                                </Button>
                            </div>
                        )}

                        {dateGroups.length === 0 ? (
                            <p className="rounded-lg border border-border bg-page/50 p-4 text-sm text-muted">
                                Tidak ada tim MILO dengan FJP Confirm pada tanggal ini.
                            </p>
                        ) : (
                            <div className="grid gap-3 lg:grid-cols-2">
                                {dateGroups.map((group, index) => (
                                    <label
                                        key={group.id}
                                        className={`rounded-xl border p-4 ${
                                            form.data.team_group_ids.includes(group.id)
                                                ? 'border-primary bg-brand-soft'
                                                : 'border-border bg-surface'
                                        } ${group.ready ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'}`}
                                    >
                                        <span className="flex items-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="mt-1 accent-primary"
                                                checked={form.data.team_group_ids.includes(group.id)}
                                                disabled={!group.ready}
                                                onChange={(event) => toggleTeamGroup(group.id, event.target.checked)}
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-semibold text-text">
                                                    Tim {index + 1} · {group.schools.length} sekolah · {formatNumber(group.total_students)} siswa
                                                </span>
                                                <span className="mt-1 block text-xs font-medium text-primary">
                                                    MILO 3IN1 ACTIV-GO: {formatNumber(group.total_students)} pcs · MILO CUP 4OZ: {formatNumber(group.total_students)} pcs
                                                </span>
                                                <span className="mt-1 block text-xs text-muted">
                                                    {group.schools.map((school) => school.name).join(' · ')}
                                                </span>
                                                <span className="mt-1 block text-xs text-muted">
                                                    Anggota: {group.members.map((member) => member.name).join(' · ') || 'Belum ada roster'}
                                                </span>
                                                {!group.ready && (
                                                    <span className="mt-2 block text-xs font-medium text-danger">
                                                        Lengkapi tanggal, roster tim, dan jumlah siswa FJP terlebih dahulu.
                                                    </span>
                                                )}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}
                        {form.errors.team_group_ids && (
                            <p role="alert" className="text-sm text-danger">{form.errors.team_group_ids}</p>
                        )}
                        <div className="rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm text-text">
                            {selectedGroups.length} tim dipilih · masing-masing produk: {formatNumber(totalStudents)} pcs total.
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-4 w-4 text-primary" />
                            Produk dan lot code barang otomatis
                        </CardTitle>
                        <p className="text-sm text-muted">
                            Produk diambil langsung dari master barang. Lot aktif dialokasikan lebih dulu, lalu lot kedaluwarsa bila stok aktif kurang; stok berkurang setelah gudang menyetujui dan menerbitkan MRV.
                        </p>
                    </CardHeader>
                    <CardContent className="grid gap-4 lg:grid-cols-2">
                        {[
                            {
                                title: 'Produk sampling',
                                product: samplingProduct,
                                allocations: samplingLotsByTeam,
                                available: samplingAvailable,
                            },
                            {
                                title: 'Barang pendukung',
                                product: supportingProduct,
                                allocations: supportingLotsByTeam,
                                available: supportingAvailable,
                            },
                        ].map(({ title, product, allocations, available }) => (
                            <section key={title} className="space-y-3 rounded-xl border border-border p-4">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted">{title}</p>
                                    {product ? (
                                        <>
                                            <p className="mt-1 font-semibold text-text">{product.name}</p>
                                            <p className="text-xs text-muted">SKU {product.sku} · {product.unit}</p>
                                        </>
                                    ) : (
                                        <p role="alert" className="mt-1 text-sm text-danger">
                                            Produk wajib tidak ditemukan pada master barang aktif.
                                        </p>
                                    )}
                                </div>
                                <p className="text-sm text-text">
                                    Quantity: {formatNumber(totalStudents)} {product?.unit ?? 'pcs'} · Lot tersedia: {formatNumber(available)}
                                </p>
                                {product && (
                                    <p className="text-xs text-muted">
                                        Aktif: {formatNumber(product.active_quantity)} · Kedaluwarsa: {formatNumber(product.expired_quantity)}
                                    </p>
                                )}
                                <div className="space-y-2">
                                    <p className="text-xs font-medium text-muted">
                                        {title === 'Barang pendukung'
                                            ? 'Lot code barang terisi otomatis per tim'
                                            : 'Lot code produk terisi otomatis per tim'}
                                    </p>
                                    {selectedGroups.length === 0 ? (
                                        <p className="text-xs text-muted">Pilih tim untuk melihat lot code yang dialokasikan.</p>
                                    ) : (
                                        selectedGroups.map((group, index) => (
                                            <p key={group.id} className="text-xs text-text">
                                                Tim {index + 1}: {lotSummary(allocations[group.id], product?.lots ?? [])}
                                            </p>
                                        ))
                                    )}
                                </div>
                            </section>
                        ))}
                        {totalStudents > 0 && !enoughStock && (
                            <p role="alert" className="text-sm font-medium text-danger lg:col-span-2">
                                Stok lot salah satu produk tidak cukup untuk jumlah siswa tim yang dipilih.
                            </p>
                        )}
                        {usesExpiredLots && (
                            <p className="text-sm font-medium text-warning lg:col-span-2">
                                Sebagian alokasi memakai lot kedaluwarsa karena stok lot aktif tidak mencukupi. Pastikan hal ini disetujui sebelum pengajuan.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Catatan MRV</CardTitle>
                        <p className="text-sm text-muted">
                            Satu MRV dibuat untuk setiap tim. Kuantitas MILO 3IN1 ACTIV-GO dan MILO CUP 4OZ sama dengan jumlah siswa tim tersebut.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <Field label="Catatan (opsional)" error={form.errors.notes}>
                            <Textarea
                                value={form.data.notes}
                                onChange={(event) => form.setData('notes', event.target.value)}
                                rows={3}
                            />
                        </Field>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted">
                        Pengajuan ini membuat {selectedGroups.length} MRV terpisah. Stok fisik dan lot baru berkurang setelah persetujuan gudang.
                    </p>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={form.processing || selectedGroups.length === 0 || !productsAvailable || !enoughStock}
                    >
                        {form.processing ? 'Mengajukan MRV…' : `Ajukan ${selectedGroups.length} MRV`}
                    </Button>
                </div>
                <p className="text-xs text-muted">
                    Tanggal aktif: {formatDate(form.data.activity_date)}. Lot dipilih dari saldo tersedia (lot aktif lebih dulu, lalu lot kedaluwarsa).
                </p>
            </form>
        </AppLayout>
    );
}
