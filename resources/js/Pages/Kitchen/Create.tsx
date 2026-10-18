import KitchenForm, { KitchenEventOption, KitchenStaffOption } from '@/Components/KitchenForm';

export default function KitchenCreate({
    events,
    staff,
}: {
    events: KitchenEventOption[];
    staff: KitchenStaffOption[];
}) {
    return <KitchenForm events={events} staff={staff} />;
}
