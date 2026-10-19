import { Field, Input, Select, Textarea } from '@/Components/ui';
import { InertiaFormProps } from '@inertiajs/react';
import { CalendarClock, ClipboardCheck, StickyNote } from 'lucide-react';
import { ReactNode } from 'react';

export type PermitOption = { id: number; name?: string; event_name?: string };

export type PermitFormData = {
    event_id: string;
    venue_id: string;
    deadline: string;
    assigned_to: string;
    notes: string;
};

export const EMPTY_PERMIT_FORM: PermitFormData = {
    event_id: '',
    venue_id: '',
    deadline: '',
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

export default function PermitForm({
    form,
    events,
    venues,
    assignees,
}: {
    form: InertiaFormProps<PermitFormData>;
    events: PermitOption[];
    venues: PermitOption[];
    assignees: PermitOption[];
}) {
    const { data, setData, errors } = form;

    return (
        <div className="space-y-6">
            <Section icon={<ClipboardCheck className="h-3.5 w-3.5" />} title="Informasi Perizinan">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Event" error={errors.event_id} className="sm:col-span-2">
                        <Select
                            value={data.event_id}
                            onChange={(e) => setData('event_id', e.target.value)}
                        >
                            <option value="">- Pilih Event -</option>
                            {events.map((ev) => (
                                <option key={ev.id} value={ev.id}>
                                    {ev.event_name ?? ev.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Lokasi" error={errors.venue_id}>
                        <Select
                            value={data.venue_id}
                            onChange={(e) => setData('venue_id', e.target.value)}
                        >
                            <option value="">- Pilih Lokasi -</option>
                            {venues.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Penanggung Jawab" error={errors.assigned_to}>
                        <Select
                            value={data.assigned_to}
                            onChange={(e) => setData('assigned_to', e.target.value)}
                        >
                            <option value="">- Pilih Penanggung Jawab -</option>
                            {assignees.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
            </Section>

            <Section icon={<CalendarClock className="h-3.5 w-3.5" />} title="Tenggat">
                <Field label="Batas Waktu" error={errors.deadline}>
                    <Input
                        type="date"
                        value={data.deadline}
                        onChange={(e) => setData('deadline', e.target.value)}
                    />
                </Field>
            </Section>

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
