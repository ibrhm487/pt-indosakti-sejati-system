import QuarterlyTargetsEditor from '@/Components/QuarterlyTargetsEditor';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

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

export default function FjpQuarterlyTargets({
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
                title="Target Kuartal"
                subtitle="Ruang kerja permitter — atur target cup per brand untuk tiap kuartal."
                brands={brands}
                brandId={brandId}
                year={year}
                years={years}
                targets={targets}
                indexRoute="fjp.quarterly-targets.index"
                updateRoute="fjp.quarterly-targets.update"
            />
        </AppLayout>
    );
}
