import { Field, Input, Select, Textarea } from '@/Components/ui';
import { InertiaFormProps } from '@inertiajs/react';
import { BarChart3, FileWarning, StickyNote } from 'lucide-react';
import { ReactNode } from 'react';

export type ReportEventOption = {
    id: number;
    event_name: string;
    event_date: string | null;
    sampling_target: number | null;
};

export type ReportFormData = {
    event_id: string;
    sampling_target: string;
    actual_sampling: string;
    issues: string;
    notes: string;
};

export const EMPTY_REPORT_FORM: ReportFormData = {
    event_id: '',
    sampling_target: '',
    actual_sampling: '',
    issues: '',
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

export default function ReportForm({
    form,
    events,
}: {
    form: InertiaFormProps<ReportFormData>;
    events: ReportEventOption[];
}) {
    const { data, setData, errors } = form;

    return (
        <div className="space-y-6">
            <Section icon={<BarChart3 className="h-3.5 w-3.5" />} title="Informasi Laporan">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Event" error={errors.event_id} className="sm:col-span-2">
                        <Select
                            value={data.event_id}
                            onChange={(e) => setData('event_id', e.target.value)}
                        >
                            <option value="">- Pilih Event -</option>
                            {events.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.event_name}
                                    {e.event_date ? ` (${e.event_date})` : ''}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Target Sampling" error={errors.sampling_target}>
                        <Input
                            type="number"
                            min={0}
                            value={data.sampling_target}
                            onChange={(e) => setData('sampling_target', e.target.value)}
                        />
                    </Field>
                    <Field label="Realisasi Sampling" error={errors.actual_sampling}>
                        <Input
                            type="number"
                            min={0}
                            value={data.actual_sampling}
                            onChange={(e) => setData('actual_sampling', e.target.value)}
                        />
                    </Field>
                </div>
            </Section>

            <Section icon={<FileWarning className="h-3.5 w-3.5" />} title="Permasalahan">
                <Field error={errors.issues}>
                    <Textarea
                        value={data.issues}
                        onChange={(e) => setData('issues', e.target.value)}
                        placeholder="Kendala selama pelaksanaan (opsional)"
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
