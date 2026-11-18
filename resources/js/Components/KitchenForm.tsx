import { Button, Card, CardContent, Field, Input, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, InertiaFormProps, useForm } from '@inertiajs/react';
import { CalendarClock, StickyNote, UtensilsCrossed } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';

export type KitchenEventOption = {
    id: number;
    event_name: string;
    event_date: string | null;
    sampling_target?: number | null;
};

export type KitchenStaffOption = { id: number; name: string };

export type KitchenFormData = {
    event_id: string;
    jug_quantity: string;
    preparation_date: string;
    preparation_time: string;
    assigned_to: string;
    notes: string;
};

export const EMPTY_KITCHEN_FORM: KitchenFormData = {
    event_id: '',
    jug_quantity: '',
    preparation_date: '',
    preparation_time: '',
    assigned_to: '',
    notes: '',
};

function Section({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
                <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-page text-muted">
                    {icon}
                </span>
                {title}
            </div>
            {children}
        </div>
    );
}

export function KitchenFormFields({
    form,
    events,
    staff,
    reportMode = false,
}: {
    form: InertiaFormProps<KitchenFormData>;
    events: KitchenEventOption[];
    staff: KitchenStaffOption[];
    reportMode?: boolean;
}) {
    const { data, setData, errors } = form;

    return (
        <div className="space-y-6">
            <Section
                icon={<UtensilsCrossed className="h-3.5 w-3.5" />}
                title={reportMode ? 'Informasi Laporan' : 'Informasi Tugas'}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Event" error={errors.event_id} className="sm:col-span-2">
                        <Select value={data.event_id} onChange={(e) => setData('event_id', e.target.value)}>
                            <option value="">- Pilih Event -</option>
                            {events.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.event_name} {e.event_date ? `(${e.event_date})` : ''}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field
                        label={reportMode ? 'Jumlah JUG Didokumentasikan' : 'Jumlah JUG'}
                        error={errors.jug_quantity}
                    >
                        <Input
                            type="number"
                            min={1}
                            value={data.jug_quantity}
                            onChange={(e) => setData('jug_quantity', e.target.value)}
                        />
                    </Field>
                </div>
            </Section>

            {!reportMode && (
                <Section icon={<CalendarClock className="h-3.5 w-3.5" />} title="Jadwal Persiapan">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Tanggal Persiapan" error={errors.preparation_date}>
                            <Input
                                type="date"
                                value={data.preparation_date}
                                onChange={(e) => setData('preparation_date', e.target.value)}
                            />
                        </Field>
                        <Field label="Waktu Persiapan" error={errors.preparation_time}>
                            <Input
                                type="time"
                                value={data.preparation_time}
                                onChange={(e) => setData('preparation_time', e.target.value)}
                            />
                        </Field>
                        <Field label="Penanggung Jawab" error={errors.assigned_to}>
                            <Select value={data.assigned_to} onChange={(e) => setData('assigned_to', e.target.value)}>
                                <option value="">- Pilih Staff -</option>
                                {staff.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </div>
                </Section>
            )}

            <Section icon={<StickyNote className="h-3.5 w-3.5" />} title="Catatan">
                <Field error={errors.notes}>
                    <Textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="Catatan tambahan (opsional)"
                    />
                </Field>
            </Section>
        </div>
    );
}

export default function KitchenForm({
    events,
    staff,
    reportMode = false,
}: {
    events: KitchenEventOption[];
    staff: KitchenStaffOption[];
    reportMode?: boolean;
}) {
    const form = useForm<KitchenFormData>(EMPTY_KITCHEN_FORM);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('kitchen.store'));
    };

    const title = reportMode ? 'Buat Laporan Kitchen' : 'Buat Tugas Kitchen';

    return (
        <AppLayout title={title}>
            <Head title={title} />
            <form onSubmit={submit}>
                <Card className="mb-6">
                    <CardContent className="pt-6">
                        <KitchenFormFields form={form} events={events} staff={staff} reportMode={reportMode} />
                    </CardContent>
                </Card>

                <div className="flex items-center gap-2">
                    <Button type="submit" variant="primary" disabled={form.processing}>
                        {form.processing ? 'Menyimpan…' : reportMode ? 'Buat Laporan' : 'Simpan'}
                    </Button>
                    <Link href={route('kitchen.index')}>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
