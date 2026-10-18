import { Field, Input, Select, Textarea } from '@/Components/ui';
import { InertiaFormProps } from '@inertiajs/react';
import { Camera, PenLine } from 'lucide-react';
import { ReactNode } from 'react';

export type QuickReportEventOption = {
    id: number;
    event_name: string;
    event_date: string | null;
};

export type QuickReportFormData = {
    event_id: string;
    title: string;
    body: string;
    photo: File | null;
};

export const EMPTY_QUICK_REPORT_FORM: QuickReportFormData = {
    event_id: '',
    title: '',
    body: '',
    photo: null,
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

export default function QuickReportForm({
    form,
    events,
}: {
    form: InertiaFormProps<QuickReportFormData>;
    events: QuickReportEventOption[];
}) {
    const { data, setData, errors } = form;

    return (
        <div className="space-y-6">
            <Section icon={<PenLine className="h-3.5 w-3.5" />} title="Laporan Kegiatan Lapangan">
                <div className="grid gap-4">
                    <Field label="Event" error={errors.event_id}>
                        <Select
                            value={data.event_id}
                            onChange={(e) => setData('event_id', e.target.value)}
                        >
                            <option value="">Pilih event yang ditugaskan</option>
                            {events.map((event) => (
                                <option key={event.id} value={event.id}>
                                    {event.event_name}
                                    {event.event_date ? ` (${event.event_date})` : ''}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Judul" error={errors.title}>
                        <Input
                            required
                            maxLength={180}
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Contoh: Kegiatan sampling sesi pagi"
                        />
                    </Field>
                    <Field label="Catatan" error={errors.body}>
                        <Textarea
                            maxLength={3000}
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Tuliskan ringkasan kegiatan atau kendala."
                        />
                    </Field>
                </div>
            </Section>

            <Section icon={<Camera className="h-3.5 w-3.5" />} title="Bukti Foto">
                <Field
                    label="Foto kegiatan (JPG, PNG, atau WEBP; maks. 10 MB)"
                    error={errors.photo}
                >
                    <Input
                        required
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(e) => setData('photo', e.target.files?.[0] ?? null)}
                    />
                </Field>
                {events.length === 0 && (
                    <p className="text-sm text-amber-700">
                        Belum ada event aktif yang ditugaskan kepada Anda.
                    </p>
                )}
            </Section>
        </div>
    );
}
