import { CardTable, EmptyState, PageHeader } from '@/Components/AppBits';
import {
    Button,
    Card,
    Field,
    Input,
    Modal,
    Select,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

type BrandTarget = {
    id: number;
    name: string;
    target_cups: number;
    actual_cups: number;
    percentage: number;
    is_configured: boolean;
};

function EditTargetModal({
    brand,
    year,
    quarter,
    onClose,
}: {
    brand: BrandTarget | null;
    year: number;
    quarter: number;
    onClose: () => void;
}) {
    const { data, setData, put, processing, errors, reset } = useForm({
        target_cups: String(brand?.target_cups ?? 0),
        year,
        quarter,
    });

    useEffect(() => {
        if (brand) {
            reset();
            setData({
                target_cups: String(brand.target_cups),
                year,
                quarter,
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [brand, year, quarter]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!brand) return;
        put(route('quarterly-targets.update', brand.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Modal
            open={brand !== null}
            onClose={onClose}
            size="sm"
            title="Atur Target Kuartal"
            description={
                brand
                    ? `Target cup brand ${brand.name} untuk kuartal yang dipilih.`
                    : undefined
            }
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onClose}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="edit-quarterly-target-form"
                        variant="primary"
                        size="sm"
                        disabled={processing}
                    >
                        {processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                </>
            }
        >
            <form id="edit-quarterly-target-form" onSubmit={submit}>
                <Field
                    label="Target Cup"
                    error={errors.target_cups}
                >
                    <Input
                        type="number"
                        min={0}
                        step={1}
                        autoFocus
                        value={data.target_cups}
                        onChange={(event) =>
                            setData('target_cups', event.target.value)
                        }
                        aria-invalid={Boolean(errors.target_cups)}
                    />
                </Field>
                <p className="text-xs text-slate-500">
                    Berlaku untuk Q{quarter} {year}. Realisasi saat ini{' '}
                    {brand ? formatNumber(brand.actual_cups) : 0} cup.
                </p>
            </form>
        </Modal>
    );
}

export default function QuarterlyTargets({
    brands,
    filters,
    quarters,
}: {
    brands: BrandTarget[];
    filters: { year: number; quarter: number };
    quarters: { value: number; label: string }[];
}) {
    const [editing, setEditing] = useState<BrandTarget | null>(null);

    const submitFilter = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get(
            route('quarterly-targets.index'),
            {
                year: Number(form.get('year')),
                quarter: Number(form.get('quarter')),
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout title="Target Kuartal">
            <Head title="Target Kuartal" />
            <PageHeader
                title="Target Kuartal per Brand"
                subtitle="Realisasi dihitung dari laporan event terverifikasi pada kuartal terpilih."
            />

            <Card className="mb-4 p-4">
                <form
                    onSubmit={submitFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <Field label="Tahun" className="mb-0 sm:w-40">
                        <Input
                            name="year"
                            type="number"
                            min={2000}
                            max={2100}
                            defaultValue={filters.year}
                        />
                    </Field>
                    <Field label="Kuartal" className="mb-0 sm:w-52">
                        <Select
                            name="quarter"
                            defaultValue={filters.quarter}
                        >
                            {quarters.map((quarter) => (
                                <option
                                    key={quarter.value}
                                    value={quarter.value}
                                >
                                    {quarter.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Button type="submit" variant="default">
                        Tampilkan
                    </Button>
                </form>
            </Card>

            <CardTable>
                {brands.length === 0 ? (
                    <EmptyState
                        title="Belum ada brand aktif"
                        description="Aktifkan brand di Data Master untuk mengatur targetnya."
                    />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Brand</TH>
                                <TH className="text-right">
                                    Realisasi Terverifikasi (cup)
                                </TH>
                                <TH className="text-right">Pencapaian</TH>
                                <TH className="text-right">Target (cup)</TH>
                                <TH className="w-20" />
                            </TR>
                        </THead>
                        <TBody>
                            {brands.map((brand) => {
                                const reached =
                                    brand.percentage >= 100 &&
                                    brand.target_cups > 0;
                                return (
                                    <TR key={brand.id}>
                                        <TD>
                                            <p className="font-medium text-slate-800">
                                                {brand.name}
                                            </p>
                                            {!brand.is_configured && (
                                                <p className="text-xs text-slate-400">
                                                    Menggunakan target default
                                                    brand
                                                </p>
                                            )}
                                        </TD>
                                        <TD className="text-right font-mono">
                                            {formatNumber(brand.actual_cups)}
                                        </TD>
                                        <TD className="min-w-40">
                                            <div className="flex items-center justify-end gap-2">
                                                <div
                                                    className="h-2 w-20 overflow-hidden rounded-full bg-slate-100"
                                                    role="progressbar"
                                                    aria-label={`Pencapaian ${brand.name}`}
                                                    aria-valuenow={
                                                        brand.percentage
                                                    }
                                                    aria-valuemin={0}
                                                    aria-valuemax={100}
                                                >
                                                    <div
                                                        className={`h-full rounded-full transition-[width] duration-500 ${
                                                            reached
                                                                ? 'bg-emerald-500'
                                                                : 'bg-indigo-600'
                                                        }`}
                                                        style={{
                                                            width: `${Math.min(
                                                                brand.percentage,
                                                                100,
                                                            )}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="w-14 text-right text-sm tabular-nums">
                                                    {brand.target_cups > 0
                                                        ? `${brand.percentage.toFixed(1)}%`
                                                        : '-'}
                                                </span>
                                            </div>
                                        </TD>
                                        <TD className="text-right font-mono text-slate-800">
                                            {formatNumber(brand.target_cups)}
                                        </TD>
                                        <TD className="text-right">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setEditing(brand)
                                                }
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                                Edit
                                            </Button>
                                        </TD>
                                    </TR>
                                );
                            })}
                        </TBody>
                    </Table>
                )}
            </CardTable>

            <EditTargetModal
                brand={editing}
                year={filters.year}
                quarter={filters.quarter}
                onClose={() => setEditing(null)}
            />
        </AppLayout>
    );
}
