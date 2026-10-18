import QuarterlyTargetsEditor from '@/Components/QuarterlyTargetsEditor';
import { Button } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

type Brand = {
    id: number;
    name: string;
};

type Target = {
    id: number | null;
    quarter: number;
    start_month: number;
    target_cups: number;
};

export default function QuarterlyTargetsIndex({
    brands,
    brandId,
    year,
    years,
    targets,
}: {
    brands: Brand[];
    brandId: number | null;
    year: number;
    years: number[];
    targets: Target[];
}) {
    return (
        <AppLayout title="Target Kuartal">
            <Head title="Target Kuartal" />
            <QuarterlyTargetsEditor
                title="Pengaturan Target Kuartal"
                subtitle="Target cup per brand untuk setiap kuartal, dipakai sebagai acuan realisasi laporan."
                brands={brands}
                brandId={brandId}
                year={year}
                years={years}
                targets={targets}
                indexRoute="admin.quarterly-targets.index"
                updateRoute="admin.quarterly-targets.update"
                headerActions={
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => router.visit(route('fjp.quarterly-targets.index'))}
                    >
                        Halaman FJP
                    </Button>
                }
            />
        </AppLayout>
    );
}
