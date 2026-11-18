import KitchenForm, { KitchenEventOption, KitchenStaffOption } from '@/Components/KitchenForm';
import { Head } from '@inertiajs/react';

export default function KitchenCreate({
    events,
    staff,
    reportMode = false,
}: {
    events: KitchenEventOption[];
    staff: KitchenStaffOption[];
    reportMode?: boolean;
}) {
    return (
        <>
            <Head title={reportMode ? 'Laporan Kitchen Baru' : 'Tugas Kitchen Baru'} />
            <KitchenForm events={events} staff={staff} reportMode={reportMode} />
        </>
    );
}
