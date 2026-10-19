import KitchenForm, { KitchenEventOption, KitchenStaffOption } from '@/Components/KitchenForm';
import { Head } from '@inertiajs/react';

export default function KitchenCreate({
    events,
    staff,
}: {
    events: KitchenEventOption[];
    staff: KitchenStaffOption[];
}) {
    return (
        <>
            <Head title="Tugas Kitchen Baru" />
            <KitchenForm events={events} staff={staff} />
        </>
    );
}
