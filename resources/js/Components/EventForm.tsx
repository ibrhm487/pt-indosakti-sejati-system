import { Field, Input, Select, Textarea } from '@/Components/ui';
import { InertiaFormProps } from '@inertiajs/react';
import { ClipboardList, MapPin, Target } from 'lucide-react';
import { ReactNode } from 'react';

export type EventOption = { id: number; name: string };
export type EventTypeOption = {
    id: number;
    name: string;
    requires_school?: boolean;
    kitchen_default?: boolean;
};

export type EventFormData = {
    event_name: string;
    brand_id: string;
    event_type_id: string;
    venue_id: string;
    event_date: string;
    start_time: string;
    end_time: string;
    sampling_target: string;
    notes: string;
    jug_required: boolean;
    pic_id: string;
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
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    {icon}
                </span>
                {title}
            </div>
            {children}
        </div>
    );
}

export default function EventForm({
    form,
    brands,
    eventTypes,
    venues,
    pics,
}: {
    form: InertiaFormProps<EventFormData>;
    brands: EventOption[];
    eventTypes: EventTypeOption[];
    venues: EventOption[];
    pics: EventOption[];
}) {
    const { data, setData, errors } = form;

    return (
        <div className="space-y-6">
            <Section icon={<ClipboardList className="h-3.5 w-3.5" />} title="Informasi Event">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Nama Event"
                        error={errors.event_name}
                        className="sm:col-span-2"
                    >
                        <Input
                            value={data.event_name}
                            onChange={(e) => setData('event_name', e.target.value)}
                            placeholder="Mis. Sampling di SDN 1 Sukamaju"
                        />
                    </Field>
                    <Field label="Brand" error={errors.brand_id}>
                        <Select
                            value={data.brand_id}
                            onChange={(e) => setData('brand_id', e.target.value)}
                        >
                            <option value="">- Pilih -</option>
                            {brands.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Jenis Event" error={errors.event_type_id}>
                        <Select
                            value={data.event_type_id}
                            onChange={(e) => setData('event_type_id', e.target.value)}
                        >
                            <option value="">- Pilih -</option>
                            {eventTypes.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="PIC" error={errors.pic_id}>
                        <Select
                            value={data.pic_id}
                            onChange={(e) => setData('pic_id', e.target.value)}
                        >
                            <option value="">- Pilih -</option>
                            {pics.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Lokasi" error={errors.venue_id}>
                        <Select
                            value={data.venue_id}
                            onChange={(e) => setData('venue_id', e.target.value)}
                        >
                            <option value="">- Pilih -</option>
                            {venues.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
            </Section>

            <Section icon={<Target className="h-3.5 w-3.5" />} title="Jadwal & Target">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Tanggal" error={errors.event_date}>
                        <Input
                            type="date"
                            value={data.event_date}
                            onChange={(e) => setData('event_date', e.target.value)}
                        />
                    </Field>
                    <Field label="Target Sampling" error={errors.sampling_target}>
                        <Input
                            type="number"
                            min={0}
                            value={data.sampling_target}
                            onChange={(e) => setData('sampling_target', e.target.value)}
                        />
                    </Field>
                    <Field label="Jam Mulai" error={errors.start_time}>
                        <Input
                            type="time"
                            value={data.start_time}
                            onChange={(e) => setData('start_time', e.target.value)}
                        />
                    </Field>
                    <Field label="Jam Selesai" error={errors.end_time}>
                        <Input
                            type="time"
                            value={data.end_time}
                            onChange={(e) => setData('end_time', e.target.value)}
                        />
                    </Field>
                    <label className="flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 sm:col-span-2">
                        <input
                            type="checkbox"
                            className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            checked={data.jug_required}
                            onChange={(e) => setData('jug_required', e.target.checked)}
                        />
                        <span className="text-sm text-slate-700">
                            Butuh JUG (cuci gelas)
                        </span>
                    </label>
                </div>
            </Section>

            <Section icon={<MapPin className="h-3.5 w-3.5" />} title="Catatan">
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
