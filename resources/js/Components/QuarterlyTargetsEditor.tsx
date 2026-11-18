import { EmptyState, PageHeader } from '@/Components/AppBits';
import {
    Button,
    Card,
    CardHeader,
    CardTitle,
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
import { formatNumber } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { FormEvent, ReactNode, useEffect, useState } from 'react';

type Brand = {
    id: number;
    name: string;
};

type Target = {
    id: number | null;
    quarter: number;
    start_month: number;
    target_cups: number;
    target_shifts: number;
};

function EditTargetsModal({
    open,
    onClose,
    brandId,
    year,
    targets,
    updateRoute,
}: {
    open: boolean;
    onClose: () => void;
    brandId: number | null;
    year: number;
    targets: Target[];
    updateRoute: string;
}) {
    const { data, setData, put, processing, errors, reset } = useForm<{
        brand_id: number | null;
        year: number;
        targets: Target[];
    }>({
        brand_id: brandId,
        year,
        targets,
    });

    useEffect(() => {
        if (open) {
            reset();
            setData({ brand_id: brandId, year, targets });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, brandId, year, targets]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route(updateRoute), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={`Edit Target Kuartal ${year}`}
            description="Atur bulan mulai serta target shift dan cups untuk tiap kuartal."
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
                        form="quarterly-targets-form"
                        variant="primary"
                        size="sm"
                        disabled={
                            processing || !data.brand_id
                        }
                    >
                        {processing ? 'Menyimpan…' : 'Simpan Target'}
                    </Button>
                </>
            }
        >
            <form id="quarterly-targets-form" onSubmit={submit}>
                <div className="space-y-3">
                    {data.targets.map((t, idx) => (
                        <div
                            key={t.quarter}
                            className="grid items-end gap-3 rounded-xl border border-border bg-page/50 p-3 sm:grid-cols-[auto_1fr_1fr_1fr]"
                        >
                            <span className="mb-2 inline-flex h-9 w-12 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary">
                                Q{t.quarter}
                            </span>
                            <Field
                                label="Bulan Mulai"
                                className="mb-0"
                                error={
                                    errors[
                                        `targets.${idx}.start_month` as keyof typeof errors
                                    ] as string
                                }
                            >
                                <Input
                                    type="number"
                                    min={1}
                                    max={12}
                                    value={t.start_month}
                                    onChange={(event) =>
                                        setData(
                                            `targets.${idx}.start_month` as any,
                                            parseInt(event.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Target Shift"
                                className="mb-0"
                                error={
                                    errors[
                                        `targets.${idx}.target_shifts` as keyof typeof errors
                                    ] as string
                                }
                            >
                                <Input
                                    type="number"
                                    min={0}
                                    value={t.target_shifts}
                                    onChange={(event) =>
                                        setData(
                                            `targets.${idx}.target_shifts` as any,
                                            parseInt(event.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Target Cups"
                                className="mb-0"
                                error={
                                    errors[
                                        `targets.${idx}.target_cups` as keyof typeof errors
                                    ] as string
                                }
                            >
                                <Input
                                    type="number"
                                    min={0}
                                    value={t.target_cups}
                                    onChange={(event) =>
                                        setData(
                                            `targets.${idx}.target_cups` as any,
                                            parseInt(event.target.value),
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    ))}
                </div>

                {errors.brand_id && (
                    <p className="mt-3 text-xs text-danger">{errors.brand_id}</p>
                )}
                {!data.brand_id && (
                    <p className="mt-3 text-xs text-warning">
                        Pilih brand terlebih dahulu sebelum menyimpan target.
                    </p>
                )}
            </form>
        </Modal>
    );
}

export default function QuarterlyTargetsEditor({
    title,
    subtitle,
    brands,
    brandId,
    year,
    years,
    targets,
    indexRoute,
    updateRoute,
    headerActions,
}: {
    title: string;
    subtitle?: string;
    brands: Brand[];
    brandId: number | null;
    year: number;
    years: number[];
    targets: Target[];
    indexRoute: string;
    updateRoute: string;
    headerActions?: ReactNode;
}) {
    const [modalOpen, setModalOpen] = useState(false);
    const brandName = brands.find((b) => b.id === brandId)?.name ?? '—';
    const totalTargetCups = targets.reduce((sum, t) => sum + (t.target_cups || 0), 0);
    const totalTargetShifts = targets.reduce((sum, t) => sum + (t.target_shifts || 0), 0);

    const navigate = (params: { brand?: number | null; year?: number }) => {
        router.get(
            route(indexRoute),
            {
                brand: params.brand ?? brandId,
                year: params.year ?? year,
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <PageHeader title={title} subtitle={subtitle}>
                <Select
                    value={brandId ?? ''}
                    onChange={(event) => navigate({ brand: Number(event.target.value) })}
                    aria-label="Pilih brand"
                    className="w-auto min-w-40"
                >
                    {brands.map((brand) => (
                        <option key={brand.id} value={brand.id}>
                            {brand.name}
                        </option>
                    ))}
                    {brands.length === 0 && (
                        <option value="">Belum ada brand aktif</option>
                    )}
                </Select>
                <Select
                    value={year}
                    onChange={(event) => navigate({ year: Number(event.target.value) })}
                    aria-label="Pilih tahun"
                    className="w-auto"
                >
                    {years.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </Select>
                {headerActions}
            </PageHeader>

            <Card>
                <CardHeader className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle>Target Shift &amp; Cups per Kuartal</CardTitle>
                        <p className="mt-0.5 text-xs text-muted">
                            {brandName} · {year}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        disabled={!brandId || brands.length === 0}
                        onClick={() => setModalOpen(true)}
                    >
                        <Pencil className="h-3.5 w-3.5" />
                        Edit Target
                    </Button>
                </CardHeader>

                {brands.length === 0 ? (
                    <EmptyState
                        title="Belum ada brand aktif"
                        description="Aktifkan brand di Data Master untuk mengatur targetnya."
                    />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Kuartal</TH>
                                <TH>Bulan Mulai</TH>
                                <TH className="text-right">Target Shift</TH>
                                <TH className="text-right">Target (cup)</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {targets.map((target) => (
                                <TR key={target.quarter}>
                                    <TD>
                                        <span className="inline-flex h-7 w-10 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary">
                                            Q{target.quarter}
                                        </span>
                                    </TD>
                                    <TD className="text-muted">
                                        Bulan {target.start_month}
                                    </TD>
                                    <TD className="text-right font-mono text-text">
                                        {formatNumber(target.target_shifts)}
                                    </TD>
                                    <TD className="text-right font-mono text-text">
                                        {formatNumber(target.target_cups)}
                                    </TD>
                                </TR>
                            ))}
                        </TBody>
                        <tfoot>
                            <TR className="hover:bg-transparent">
                                <TD
                                    colSpan={2}
                                    className="text-xs font-semibold uppercase tracking-wide text-muted"
                                >
                                    Total {year}
                                </TD>
                                <TD className="text-right font-mono font-semibold text-text">
                                    {formatNumber(totalTargetShifts)} shift
                                </TD>
                                <TD className="text-right font-mono font-semibold text-text">
                                    {formatNumber(totalTargetCups)} cup
                                </TD>
                            </TR>
                        </tfoot>
                    </Table>
                )}
            </Card>

            <EditTargetsModal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                brandId={brandId}
                year={year}
                targets={targets}
                updateRoute={updateRoute}
            />
        </>
    );
}
