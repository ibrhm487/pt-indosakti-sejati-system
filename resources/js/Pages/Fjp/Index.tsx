import {
    CardTable,
    ConfirmButton,
    EmptyState,
    FilterCard,
    ModalForm,
    PageHeader,
    Pagination,
    Tabs,
} from '@/Components/AppBits';
import {
    Button,
    Field,
    Input,
    Modal,
    Select,
    StatusBadge,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import SchoolPicker, { DapoSchoolDetail } from '@/Components/SchoolPicker';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatNumber } from '@/lib/utils';
import { pushToast } from '@/lib/toast';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronDown,
    ChevronRight,
    Columns3,
    Download,
    ImageIcon,
    LoaderCircle,
    MapPin,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { EMPTY_FJP_FORM, FjpFormData, FjpFormFields, FjpFormOptions } from './Form';

type FjpRow = {
    id: number;
    attachments_count: number;
    fjp_number: string;
    event_name: string;
    brand: string | null;
    brand_id: number | null;
    event_type_id: number | null;
    venue_id: number | null;
    event_date: string | null;
    start_time: string | null;
    sampling_target: number | null;
    jug_required: boolean;
    school_name: string | null;
    npsn: string | null;
    address: string | null;
    city: string | null;
    province: string | null;
    kecamatan: string | null;
    kelurahan: string | null;
    maps_url: string | null;
    sekolah: number | null;
    dapodik: number | null;
    difference: number | null;
    jumlah_siswa_sekolah: number | null;
    jumlah_siswa_dapodik: number | null;
    permitter_total_outlet_canteen_sell_drink: number | null;
    permitter_quantity_with_milo: number | null;
    permitter_status: string | null;
    permitter_quantity_without_milo: number | null;
    team_sampling_quantity_with_milo: number | null;
    team_sampling_quantity_without_milo: number | null;
    dapodik_source: string | null;
    planned_team_group_id: string | null;
    planned_team_group_label: string | null;
    planned_team_group_fjp_ids: number[];
    planned_team_group_schedule: {
        fjp_id: number;
        school_name: string;
        start_time: string | null;
    }[];
    assignment_status: string;
    assignment_status_label: string;
    team: FjpTeam | null;
    event_schedule: FjpScheduleDetails | null;
    status: string;
    status_label: string;
    notes: string | null;
    has_event: boolean;
};

type FjpScheduleDetails = {
    event_date: string | null;
    school_name: string;
    brand: string | null;
    location: string;
    team: string | null;
    pic: string | null;
    sampling_target: number | null;
    members: {
        personnel_id: number | null;
        position: string;
        name: string;
        phone: string | null;
    }[];
    start_time: string | null;
};

type FjpTeam = {
    group_id: string;
    team_group_id: string | null;
    event_date: string | null;
    editable: boolean;
    members: {
        personnel_id: number | null;
        user_id: number | null;
        position: string;
        name: string;
        phone: string | null;
    }[];
    schools: string[];
    school_schedule: {
        fjp_id: number;
        school_name: string;
        start_time: string | null;
    }[];
};

type TeamPersonnelOption = {
    id: number;
    user_id: number;
    name: string;
    role: string;
    phone: string | null;
};

type FjpColumn =
    | 'schedule'
    | 'status'
    | 'students'
    | 'city'
    | 'kecamatan'
    | 'kelurahan'
    | 'permitter_total_outlet'
    | 'permitter_with_milo'
    | 'permitter_status'
    | 'permitter_without_milo'
    | 'permitter_percentage'
    | 'team_sampling_with_milo'
    | 'team_sampling_without_milo'
    | 'team_sampling_percentage'
    | 'location'
    | 'notes';

const COLUMN_OPTIONS: { key: FjpColumn; label: string }[] = [
    { key: 'schedule', label: 'Jadwal' },
    { key: 'status', label: 'Status FJP' },
    { key: 'students', label: 'Data siswa' },
    { key: 'city', label: 'Kota' },
    { key: 'kecamatan', label: 'Kecamatan' },
    { key: 'kelurahan', label: 'Kelurahan' },
    { key: 'location', label: 'Lokasi' },
    { key: 'notes', label: 'Catatan' },
    { key: 'permitter_total_outlet', label: 'Total Outlet Canteen (Sell Drink)' },
    { key: 'permitter_with_milo', label: 'Kuantitas with MILO (Permitter)' },
    { key: 'permitter_status', label: 'Status validasi Permitter' },
    { key: 'permitter_without_milo', label: 'Kuantitas without MILO (Permitter)' },
    { key: 'permitter_percentage', label: '% validasi Permitter' },
    { key: 'team_sampling_with_milo', label: 'Kuantitas with MILO (Team Sampling)' },
    { key: 'team_sampling_without_milo', label: 'Kuantitas without MILO (Team Sampling)' },
    { key: 'team_sampling_percentage', label: '% Team Sampling' },
];

type PendingDapodikSchoolRow = {
    id: string;
    detail: DapoSchoolDetail | null;
    schoolStudentCount: string;
    teamNumber: number | null;
    eventDate: string;
    startTime: string;
};

type TeamScheduleImageSchool = {
    name: string;
    city: string | null;
    student_count: number;
};

type TeamScheduleImageGroup = {
    id?: string;
    team_group_id?: string | null;
    event_ids?: number[];
    editable?: boolean;
    members: { position: string; name: string; user_id?: number | null }[];
    schools: TeamScheduleImageSchool[];
    total_students: number;
};

type TeamScheduleImageInput = {
    event_date: string;
    teams: TeamScheduleImageGroup[];
};

type TeamSchedulePreviewResponse = {
    event_date: string;
    teams: TeamScheduleImageGroup[];
};

function formatGroupDate(date: string | null): string {
    if (!date) return 'Tanggal belum ditentukan';

    return new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    })
        .format(new Date(`${date}T00:00:00`))
        .toLocaleUpperCase('id-ID');
}

function teamNumberFromLabel(label: string | null): number | null {
    const match = label?.match(/^tim\s+(\d+)$/i);

    return match ? Number(match[1]) : null;
}

function formatSamplingPercentage(quantity: number | null, total: number | null): string {
    if (quantity === null || total === null || total <= 0) {
        return '—';
    }

    return `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format((quantity / total) * 100)}%`;
}

function wrapCanvasText(
    context: CanvasRenderingContext2D,
    text: string,
    maxWidth: number,
): string[] {
    const words = text.trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return [''];
    }

    const lines: string[] = [];
    let line = '';

    words.forEach((word) => {
        const candidate = line ? `${line} ${word}` : word;

        if (line && context.measureText(candidate).width > maxWidth) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    });

    if (line) {
        lines.push(line);
    }

    return lines;
}

function renderTeamScheduleImage(input: TeamScheduleImageInput): string {
    const width = 1800;
    const padding = 48;
    const contentWidth = width - padding * 2;
    const teamGap = 24;
    const teamColumns = 2;
    const teamWidth = (contentWidth - teamGap) / teamColumns;
    const styles = getComputedStyle(document.documentElement);
    const colors = {
        primary: styles.getPropertyValue('--color-primary').trim() || '#d91e2a',
        primarySoft: styles.getPropertyValue('--color-brand-soft').trim() || '#fdecee',
        page: styles.getPropertyValue('--color-page').trim() || '#f6f7f9',
        surface: styles.getPropertyValue('--color-surface').trim() || '#ffffff',
        text: styles.getPropertyValue('--color-text').trim() || '#12141a',
        muted: styles.getPropertyValue('--color-muted').trim() || '#667085',
        border: styles.getPropertyValue('--color-border').trim() || '#e5e7eb',
        success: styles.getPropertyValue('--color-success').trim() || '#16845b',
    };
    const measureCanvas = document.createElement('canvas');
    const measureContext = measureCanvas.getContext('2d');

    if (!measureContext) {
        throw new Error('Browser tidak dapat menyiapkan konteks gambar jadwal.');
    }

    const preparedTeams = input.teams.map((team) => {
        const rows = team.schools.map((school) => {
            measureContext.font = '700 19px Arial, sans-serif';
            const nameLines = wrapCanvasText(measureContext, school.name, teamWidth - 230);

            return {
                school,
                nameLines,
                height: Math.max(76, nameLines.length * 25 + 42),
            };
        });

        measureContext.font = '600 17px Arial, sans-serif';
        const memberText = team.members.map((member) => `${member.position}: ${member.name}`).join('  ·  ');
        const memberLines = wrapCanvasText(measureContext, memberText, teamWidth - 56);
        const headerHeight = Math.max(100, 64 + memberLines.length * 22);
        const emptySchoolsHeight = rows.length === 0 ? 48 : 0;

        return {
            ...team,
            rows,
            memberLines,
            headerHeight,
            cardHeight: headerHeight + rows.reduce((total, row) => total + row.height, 0) + emptySchoolsHeight + 16,
        };
    });

    const teamRows = Array.from({ length: Math.ceil(preparedTeams.length / teamColumns) }, (_, rowIndex) => (
        preparedTeams.slice(rowIndex * teamColumns, (rowIndex + 1) * teamColumns)
    ));
    const teamRowHeights = teamRows.map((row) => Math.max(...row.map((team) => team.cardHeight)));
    const headerHeight = 210;
    const summaryHeight = 118;
    const summaryGap = 20;
    const cardsTop = padding + headerHeight + 24 + summaryHeight + 28;
    const cardsHeight = teamRowHeights.reduce((total, rowHeight) => total + rowHeight, 0)
        + Math.max(teamRows.length - 1, 0) * teamGap;
    const footerHeight = 72;
    const height = cardsTop + cardsHeight + footerHeight;

    if (height > 32767) {
        throw new Error('Jadwal terlalu panjang untuk dibuat dalam satu gambar PNG.');
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Browser tidak dapat membuat gambar jadwal.');
    }

    const roundedRect = (x: number, y: number, rectWidth: number, rectHeight: number, radius: number) => {
        context.beginPath();
        context.roundRect(x, y, rectWidth, rectHeight, radius);
        context.fill();
    };

    context.fillStyle = colors.page;
    context.fillRect(0, 0, width, height);

    context.fillStyle = colors.primary;
    roundedRect(padding, padding, contentWidth, headerHeight, 28);
    context.fillStyle = colors.primarySoft;
    context.font = '700 20px Arial, sans-serif';
    context.fillText('FJP SAMPLING SEKOLAH', padding + 40, padding + 50);
    context.fillStyle = colors.surface;
    context.font = '700 44px Arial, sans-serif';
    context.fillText('JADWAL PEMBAGIAN TIM', padding + 40, padding + 116);
    context.font = '600 23px Arial, sans-serif';
    context.fillStyle = colors.surface;
    context.fillText(formatGroupDate(input.event_date), padding + 40, padding + 160);

    context.fillStyle = colors.primarySoft;
    roundedRect(padding + 40, padding + 174, 280, 28, 14);
    context.fillStyle = colors.primary;
    context.font = '700 14px Arial, sans-serif';
    context.fillText('STATUS FJP: CONFIRM', padding + 56, padding + 193);

    const totalSchools = input.teams.reduce((total, team) => total + team.schools.length, 0);
    const totalStudents = input.teams.reduce((total, team) => total + team.total_students, 0);
    const summaries = [
        { label: 'JUMLAH TIM', value: String(input.teams.length) },
        { label: 'TOTAL SEKOLAH', value: formatNumber(totalSchools) },
        { label: 'TOTAL SISWA', value: formatNumber(totalStudents) },
    ];
    const summaryWidth = (contentWidth - summaryGap * 2) / 3;
    let y = padding + headerHeight + 24;

    summaries.forEach((summary, index) => {
        const x = padding + index * (summaryWidth + summaryGap);
        context.fillStyle = colors.surface;
        roundedRect(x, y, summaryWidth, summaryHeight, 20);
        context.fillStyle = colors.muted;
        context.font = '700 15px Arial, sans-serif';
        context.fillText(summary.label, x + 28, y + 34);
        context.fillStyle = colors.primary;
        context.font = '700 34px Arial, sans-serif';
        context.fillText(summary.value, x + 28, y + 82);
    });

    y = cardsTop;

    teamRows.forEach((row, rowIndex) => {
        row.forEach((team, columnIndex) => {
            const index = rowIndex * teamColumns + columnIndex;
            const x = padding + columnIndex * (teamWidth + teamGap);

            context.fillStyle = colors.surface;
            roundedRect(x, y, teamWidth, team.cardHeight, 20);
            context.strokeStyle = colors.border;
            context.lineWidth = 2;
            context.stroke();

            context.fillStyle = colors.primarySoft;
            roundedRect(x + 2, y + 2, teamWidth - 4, team.headerHeight - 8, 18);

            context.fillStyle = colors.primary;
            context.font = '700 25px Arial, sans-serif';
            context.fillText(`TIM ${index + 1}`, x + 24, y + 38);
            context.fillStyle = colors.surface;
            roundedRect(x + teamWidth - 242, y + 16, 218, 40, 20);
            context.fillStyle = colors.primary;
            context.font = '700 13px Arial, sans-serif';
            context.textAlign = 'center';
            context.fillText(
                `${team.schools.length} SEKOLAH · ${formatNumber(team.total_students)} SISWA`,
                x + teamWidth - 133,
                y + 41,
            );
            context.textAlign = 'left';

            context.fillStyle = colors.text;
            context.font = '600 16px Arial, sans-serif';
            team.memberLines.forEach((line, lineIndex) => {
                context.fillText(line, x + 24, y + 70 + lineIndex * 21);
            });

            let rowY = y + team.headerHeight;

            if (team.rows.length === 0) {
                context.fillStyle = colors.muted;
                context.font = 'italic 16px Arial, sans-serif';
                context.fillText('Belum ada sekolah pada tim ini.', x + 24, rowY + 32);
            }

            team.rows.forEach((schoolRow, schoolIndex) => {
                context.fillStyle = schoolIndex % 2 === 0 ? colors.surface : colors.page;
                context.fillRect(x + 12, rowY, teamWidth - 24, schoolRow.height);

                const nameX = x + 42;
                const nameY = rowY + 26;
                context.fillStyle = colors.primary;
                context.font = '700 16px Arial, sans-serif';
                context.fillText(`${String(schoolIndex + 1).padStart(2, '0')}.`, x + 22, nameY);
                context.fillStyle = colors.text;
                context.font = '700 17px Arial, sans-serif';
                schoolRow.nameLines.forEach((line, lineIndex) => {
                    context.fillText(line, nameX, nameY + lineIndex * 22);
                });

                const cityY = nameY + schoolRow.nameLines.length * 22 + 2;
                context.fillStyle = colors.muted;
                context.font = '500 14px Arial, sans-serif';
                context.fillText(schoolRow.school.city ?? 'Lokasi belum diisi', nameX, cityY);

                context.fillStyle = colors.primarySoft;
                roundedRect(x + teamWidth - 176, rowY + (schoolRow.height - 36) / 2, 154, 36, 18);
                context.fillStyle = colors.success;
                context.font = '700 13px Arial, sans-serif';
                context.textAlign = 'center';
                context.fillText(
                    `${formatNumber(schoolRow.school.student_count)} SISWA`,
                    x + teamWidth - 99,
                    rowY + schoolRow.height / 2 + 5,
                );
                context.textAlign = 'left';
                rowY += schoolRow.height;
            });
        });

        y += teamRowHeights[rowIndex] + teamGap;
    });

    context.fillStyle = colors.muted;
    context.font = '500 14px Arial, sans-serif';
    context.textAlign = 'center';
    context.fillText('Jadwal operasional tim  ·  Sistem FJP', width / 2, height - 28);

    return canvas.toDataURL('image/png');
}

function FjpStatusBadge({ status, label }: { status: string; label: string }) {
    const variants: Record<string, string> = {
        plan: 'bg-info/10 text-info',
        confirm: 'bg-success/10 text-success',
        cancel: 'bg-danger/10 text-danger',
        reschedule: 'bg-warning/10 text-warning',
        done: 'bg-success/10 text-success',
    };
    const variant = variants[status.toLowerCase()];

    return variant ? (
        <span className={cn('inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', variant)}>
            {label}
        </span>
    ) : (
        <StatusBadge status={status} label={label} />
    );
}

function InlineText({
    fjpId,
    field,
    value,
    disabled,
    placeholder,
    className,
    inputType = 'text',
}: {
    fjpId: number;
    field:
        | 'notes'
        | 'address'
        | 'maps_url'
        | 'school_name'
        | 'npsn'
        | 'event_date'
        | 'start_time'
        | 'jumlah_siswa_sekolah'
        | 'city'
        | 'kecamatan'
        | 'kelurahan'
        | 'permitter_total_outlet_canteen_sell_drink'
        | 'permitter_quantity_with_milo'
        | 'permitter_quantity_without_milo'
        | 'team_sampling_quantity_with_milo'
        | 'team_sampling_quantity_without_milo';
    value: string | number | null;
    disabled: boolean;
    placeholder?: string;
    className?: string;
    inputType?: 'text' | 'number' | 'date' | 'time';
}) {
    const normalizedValue = value == null ? '' : String(value);
    const [text, setText] = useState(normalizedValue);
    const [saving, setSaving] = useState(false);
    const [feedback, setFeedback] = useState('');

    useEffect(() => {
        setText(normalizedValue);
        setFeedback('');
    }, [normalizedValue]);

    const save = () => {
        if (normalizedValue === text || saving) return;
        setSaving(true);
        setFeedback('');
        if (field === 'npsn') {
            router.post(
                route('fjp.dapodik', fjpId),
                { npsn: text },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => setFeedback('Dapodik terisi'),
                    onError: () => setFeedback('Gagal sinkron Dapodik'),
                    onFinish: () => setSaving(false),
                },
            );
            return;
        }
        router.patch(
            route('fjp.inline', fjpId),
            { [field]: text },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setFeedback('Tersimpan'),
                onError: (errors) => setFeedback(Object.values(errors)[0] ?? 'Gagal menyimpan pembagian.'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="space-y-1.5">
            <Input
                type={inputType}
                min={inputType === 'number' ? 0 : undefined}
                value={text}
                disabled={disabled || saving}
                placeholder={placeholder}
                className={className}
                onChange={(e) => setText(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        save();
                    }
                }}
            />
            {feedback && (
                <p className={cn('text-[11px]', feedback === 'Tersimpan' ? 'text-success' : 'text-danger')}>
                    {feedback}
                </p>
            )}
            {!disabled && normalizedValue !== text && (
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        size="sm"
                        className="h-7 px-2 text-[11px]"
                        disabled={saving}
                        onClick={save}
                    >
                        <Check className="h-3 w-3" />
                        Simpan
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-7 px-2 text-[11px]"
                        disabled={saving}
                        onClick={() => {
                            setText(normalizedValue);
                            setFeedback('');
                        }}
                    >
                        <X className="h-3 w-3" />
                        Batal
                    </Button>
                </div>
            )}
        </div>
    );
}

function InlineDapodikSchool({
    fjpId,
    schoolName,
    npsn,
    disabled,
}: {
    fjpId: number;
    schoolName: string | null;
    npsn: string | null;
    disabled: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [feedback, setFeedback] = useState('');

    const pick = (detail: DapoSchoolDetail) => {
        if (saving) {
            return;
        }

        setSaving(true);
        setFeedback('');
        router.patch(
            route('fjp.inline', fjpId),
            {
                school_name: detail.school_name || detail.npsn,
                npsn: detail.npsn,
                address: detail.address,
                city: detail.kabupaten,
                province: detail.provinsi,
                kecamatan: detail.kecamatan,
                kelurahan: detail.kelurahan,
                maps_url: detail.maps_url,
                jumlah_siswa_dapodik: detail.jumlah_siswa,
                dapodik_source: 'Dapodik (dapo.kemendikdasmen.go.id)',
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setOpen(false);
                    setFeedback('Dapodik terisi');
                },
                onError: () => setFeedback('Gagal mengisi Dapodik'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="space-y-1.5">
            <div>
                <p className="font-semibold text-text">{schoolName || 'Nama sekolah belum dipilih'}</p>
                <p className="mt-1 font-mono text-[11px] text-muted">NPSN {npsn || '-'}</p>
            </div>
            {!disabled && (
                <div className="relative">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-7"
                        onClick={() => setOpen((current) => !current)}
                        disabled={saving}
                    >
                        <Search className="h-3 w-3" />
                        {open ? 'Tutup pencarian' : 'Pilih dari Dapodik'}
                    </Button>
                    {open && (
                        <div className="absolute left-0 z-40 mt-2 w-80 max-w-[calc(100vw-3rem)] rounded-xl border border-border bg-surface p-2 shadow-lg">
                            <SchoolPicker
                                jabodetabekOnly
                                onPick={pick}
                                onError={(message) => setFeedback(message)}
                            />
                        </div>
                    )}
                    {feedback && (
                        <p className={cn('mt-1 text-[11px]', feedback.includes('Gagal') ? 'text-danger' : 'text-success')}>
                            {feedback}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

function InlineSelect({
    fjpId,
    field,
    value,
    disabled,
    options,
    optionValues,
}: {
    fjpId: number;
    field: 'permitter_status' | 'team_number';
    value: string | number | null;
    disabled: boolean;
    options: string[];
    optionValues?: string[];
}) {
    const currentValue = value ?? '';
    const [selected, setSelected] = useState(currentValue);
    const [saving, setSaving] = useState(false);
    const [feedback, setFeedback] = useState('');

    useEffect(() => {
        setSelected(currentValue);
        setFeedback('');
    }, [currentValue]);

    const save = (nextValue: string) => {
        if (saving || nextValue === currentValue) {
            return;
        }

        setSelected(nextValue);
        setSaving(true);
        setFeedback('');
        router.patch(
            route('fjp.inline', fjpId),
            { [field]: nextValue },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setFeedback('Tersimpan'),
                onError: () => {
                    setSelected(currentValue);
                    setFeedback('Gagal menyimpan');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="space-y-1.5">
            <Select
                value={selected}
                disabled={disabled || saving}
                onChange={(event) => save(event.target.value)}
            >
                <option value="">Pilih status</option>
                {options.map((option, index) => (
                    <option key={option} value={optionValues?.[index] ?? option}>{option}</option>
                ))}
            </Select>
            {feedback && (
                <p className={cn('text-[11px]', feedback === 'Tersimpan' ? 'text-success' : 'text-danger')}>
                    {feedback}
                </p>
            )}
        </div>
    );
}

function PermitTeamAssignment({
    fjpId,
    teamGroupLabel,
}: {
    fjpId: number;
    teamGroupLabel: string | null;
}) {
    const currentTeamNumber = teamGroupLabel?.match(/\d+/)?.[0] ?? '';
    const [open, setOpen] = useState(false);
    const [selectedTeamNumber, setSelectedTeamNumber] = useState(currentTeamNumber);
    const [saving, setSaving] = useState(false);
    const [feedback, setFeedback] = useState('');

    useEffect(() => {
        setSelectedTeamNumber(currentTeamNumber);
    }, [currentTeamNumber]);

    const save = () => {
        if (saving || selectedTeamNumber === '' || selectedTeamNumber === currentTeamNumber) {
            return;
        }

        setSaving(true);
        setFeedback('');
        router.patch(
            route('fjp.inline', fjpId),
            { team_number: Number(selectedTeamNumber) },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setOpen(false),
                onError: () => setFeedback('Tim gagal disimpan. Coba lagi.'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Button
                type="button"
                variant={currentTeamNumber ? 'outline' : 'primary'}
                size="sm"
                onClick={() => setOpen(true)}
            >
                {currentTeamNumber ? 'Ubah Tim' : 'Pembagian Tim'}
            </Button>
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="Pembagian Tim"
                description="Pilih kelompok tim untuk FJP ini. PIC hanya dapat membagikan anggota setelah tim ditetapkan."
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={saving || selectedTeamNumber === '' || selectedTeamNumber === currentTeamNumber}
                            onClick={save}
                        >
                            {saving ? 'Menyimpan...' : 'Simpan tim'}
                        </Button>
                    </>
                }
            >
                <Field label="Tim yang ditugaskan">
                    <Select
                        value={selectedTeamNumber}
                        disabled={saving}
                        onChange={(event) => setSelectedTeamNumber(event.target.value)}
                    >
                        <option value="">Pilih tim</option>
                        {Array.from({ length: 25 }, (_, index) => String(index + 1)).map((teamNumber) => (
                            <option key={teamNumber} value={teamNumber}>Tim {teamNumber}</option>
                        ))}
                    </Select>
                </Field>
                {feedback && <p role="alert" className="mt-3 text-sm text-danger">{feedback}</p>}
            </Modal>
        </>
    );
}

function personnelIdForMember(
    member: FjpTeam['members'][number],
    personnel: TeamPersonnelOption[],
): string {
    const personnelOption = personnel.find((person) => (
        person.id === member.personnel_id || person.user_id === member.user_id
    ));

    return personnelOption ? String(personnelOption.id) : '';
}

function TeamGroupRoster({
    teamGroupId,
    fjpIds,
    teamGroupLabel,
    members,
    personnel,
    editable,
    hasExistingEvent,
    onSaved,
}: {
    teamGroupId: string;
    fjpIds: number[];
    teamGroupLabel: string;
    members: FjpTeam['members'];
    personnel: TeamPersonnelOption[];
    editable: boolean;
    hasExistingEvent: boolean;
    onSaved?: () => void;
}) {
    const [teamLeaderId, setTeamLeaderId] = useState('');
    const [spgPersonnelId, setSpgPersonnelId] = useState('');
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [feedback, setFeedback] = useState('');
    const hasRoster = members.length > 0;

    useEffect(() => {
        const leader = members.find((member) => member.position === 'Team Leader');
        setTeamLeaderId(leader ? personnelIdForMember(leader, personnel) : '');
        const spg = members.find((member) => member.position === 'SPG');
        setSpgPersonnelId(spg ? personnelIdForMember(spg, personnel) : '');
        setFeedback('');
    }, [members, personnel]);

    const save = () => {
        if (saving || !teamLeaderId || !spgPersonnelId || spgPersonnelId === teamLeaderId) {
            return;
        }

        setSaving(true);
        setFeedback('');
        const payload = {
            team_leader_personnel_id: Number(teamLeaderId),
            spg_personnel_ids: [Number(spgPersonnelId)],
        };
        const options = {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setOpen(false);
                onSaved?.();
            },
            onError: () => setFeedback('Gagal menyimpan'),
            onFinish: () => setSaving(false),
        };

        if (hasExistingEvent) {
            router.patch(route('fjp.team-group.update', teamGroupId), payload, options);
        } else {
            router.post(route('fjp.team-group'), { fjp_ids: fjpIds, ...payload }, options);
        }
    };

    return (
        <div className="space-y-2">
            <table className="w-full min-w-[560px] border-collapse text-xs [&_td]:border [&_td]:border-border [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-border [&_th]:bg-page [&_th]:px-2 [&_th]:py-2 [&_th]:text-left">
                <thead>
                    <tr>
                        <th>Kelompok tim</th>
                        <th>Nama personel</th>
                        <th>Jabatan</th>
                        <th>Kontak</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    {hasRoster ? members.map((member, index) => {
                        const personnelId = personnelIdForMember(member, personnel);

                        return (
                            <tr key={`${member.position}-${member.personnel_id ?? member.user_id ?? index}`}>
                                <td>{teamGroupLabel}</td>
                                <td className="font-medium text-text">{member.name}</td>
                                <td>{member.position}</td>
                                <td>{member.phone || '—'}</td>
                                <td>
                                    {editable && (
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                aria-label={`Ubah pembagian ${member.name}`}
                                                onClick={() => setOpen(true)}
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                            </Button>
                                            {personnelId !== '' && (
                                                <ConfirmButton
                                                    title="Hapus Personel"
                                                    description={`Hapus ${member.name} dari ${teamGroupLabel}?`}
                                                    onConfirm={() => router.delete(
                                                        route('fjp.team-group.members.destroy', {
                                                            teamGroupId,
                                                            personnel: personnelId,
                                                        }),
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () => onSaved?.(),
                                                            onError: (errors) => setFeedback(Object.values(errors)[0] ?? 'Gagal menghapus personel.'),
                                                        },
                                                    )}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </ConfirmButton>
                                            )}
                                        </div>
                                    )}
                                </td>
                            </tr>
                        );
                    }) : (
                        <tr>
                            <td colSpan={5} className="text-center text-muted">
                                Belum ada personel yang ditugaskan.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
            <Button type="button" size="sm" disabled={!editable} onClick={() => setOpen(true)}>
                {hasRoster ? 'Ubah Anggota Tim' : 'Pembagian Anggota Tim'}
            </Button>
            {personnel.length === 0 && (
                <p role="alert" className="text-xs text-warning">
                    Belum ada personel aktif yang terhubung ke akun role Tim. Hubungkan akun melalui menu Personel sebelum membagikan anggota.
                </p>
            )}
            {feedback && <p role="alert" className="text-xs text-danger">{feedback}</p>}
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="Pembagian Anggota Tim"
                description={`${teamGroupLabel} · ${fjpIds.length} FJP`}
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={saving || !teamLeaderId || !spgPersonnelId || spgPersonnelId === teamLeaderId}
                            onClick={save}
                        >
                            {saving ? 'Menyimpan...' : 'Simpan pembagian'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    {personnel.length === 0 && (
                        <p role="alert" className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                            Belum ada personel aktif yang terhubung ke akun role Tim. Hubungkan akun melalui menu Personel sebelum membagikan anggota.
                        </p>
                    )}
                    <Field label="Team Leader">
                        <Select
                            value={teamLeaderId}
                            disabled={saving}
                            aria-label="Pilih Team Leader"
                            onChange={(event) => setTeamLeaderId(event.target.value)}
                        >
                            <option value="">Pilih Team Leader</option>
                            {personnel.filter((person) => person.role.trim().toLowerCase() === 'team leader').map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}{person.phone ? ` · ${person.phone}` : ''}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="SPG">
                        <Select
                        value={spgPersonnelId}
                        disabled={saving}
                        aria-label="Pilih SPG"
                        onChange={(event) => setSpgPersonnelId(event.target.value)}
                        >
                        <option value="">Pilih SPG</option>
                        {personnel.filter((person) => person.role.trim().toLowerCase() === 'spg').map((person) => (
                            <option key={person.id} value={person.id} disabled={String(person.id) === teamLeaderId}>
                                {person.name}{person.phone ? ` · ${person.phone}` : ''}
                            </option>
                        ))}
                        </Select>
                    </Field>
                    {feedback && <p role="alert" className="text-sm text-danger">{feedback}</p>}
                </div>
            </Modal>
        </div>
    );
}

export default function FjpIndex({
    fjps,
    filters,
    statusCounts,
    totalCount,
    statusOptions,
    formOptions,
    teamSchedules,
    canAssignTeam,
    canAssignTeamMembers,
    canManageTeamSchedules,
    canUpdateStatus,
    teamPersonnel,
    teamScheduleDate,
}: {
    fjps: { data: FjpRow[] };
    filters: {
        search?: string;
        status?: string;
        brand_id?: number | string;
        date_from?: string;
        date_to?: string;
        city?: string;
        per_page?: number | string;
    };
    statusCounts: Record<string, number>;
    totalCount: number;
    statusOptions: { value: string; label: string }[];
    formOptions?: FjpFormOptions;
    teamSchedules: FjpTeam[];
    canAssignTeam: boolean;
    canAssignTeamMembers: boolean;
    canManageTeamSchedules: boolean;
    canUpdateStatus: boolean;
    teamPersonnel: TeamPersonnelOption[];
    teamScheduleDate: string;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [brandId, setBrandId] = useState(
        filters.brand_id != null ? String(filters.brand_id) : '',
    );
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [perPage, setPerPage] = useState(
        String(filters.per_page ?? 25),
    );
    const [formOpen, setFormOpen] = useState(false);
    const [pendingDapodikRows, setPendingDapodikRows] = useState<PendingDapodikSchoolRow[]>([]);
    const [pendingTeamCount, setPendingTeamCount] = useState(1);
    const [showLegacyDapodikSection] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [selectedDetail, setSelectedDetail] = useState<FjpRow | null>(null);
    const dapodikRowsSectionRef = useRef<HTMLElement | null>(null);
    const [teamScheduleOpen, setTeamScheduleOpen] = useState(false);
    const [scheduleImageDate, setScheduleImageDate] = useState(teamScheduleDate);
    const [savedTeamGroups, setSavedTeamGroups] = useState<TeamScheduleImageGroup[]>([]);
    const [selectedSavedTeamIds, setSelectedSavedTeamIds] = useState<string[]>([]);
    const [savedTeamGroupsLoading, setSavedTeamGroupsLoading] = useState(false);
    const [savedTeamGroupsError, setSavedTeamGroupsError] = useState('');
    const [savedTeamGroupsRevision, setSavedTeamGroupsRevision] = useState(0);
    const [scheduleImageOpen, setScheduleImageOpen] = useState(false);
    const [scheduleImageInput, setScheduleImageInput] = useState<TeamScheduleImageInput | null>(null);
    const [scheduleImageDataUrl, setScheduleImageDataUrl] = useState('');
    const [scheduleImageGenerating, setScheduleImageGenerating] = useState(false);
    const [scheduleImageError, setScheduleImageError] = useState('');
    const [sortColumn, setSortColumn] = useState<'schedule' | 'school' | 'students' | 'status'>('schedule');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
    const [visibleColumns, setVisibleColumns] = useState<Record<FjpColumn, boolean>>({
        schedule: true,
        status: true,
        students: true,
        city: true,
        kecamatan: true,
        kelurahan: true,
        location: true,
        notes: true,
        permitter_total_outlet: true,
        permitter_with_milo: true,
        permitter_status: true,
        permitter_without_milo: true,
        permitter_percentage: true,
        team_sampling_with_milo: true,
        team_sampling_without_milo: true,
        team_sampling_percentage: true,
    });
    const [collapsedDates, setCollapsedDates] = useState<string[]>([]);
    const permissions = usePage().props.auth.permissions;
    const canCreate = permissions.includes('fjp.create');
    const canUpdate = permissions.includes('fjp.update');
    const canDelete = permissions.includes('fjp.delete');
    const availableColumns = COLUMN_OPTIONS;

    const form = useForm<FjpFormData>(EMPTY_FJP_FORM);
    const dapodikRowsForm = useForm<{
        schools: { npsn: string; jumlah_siswa_sekolah: number | null; team_number?: number | null; event_date?: string | null; start_time?: string | null }[];
        brand_id: number | null;
        event_type_id: number | null;
    }>({ schools: [], brand_id: brandId === '' ? null : Number(brandId), event_type_id: null });
    const selectedSavedTeams = savedTeamGroups.filter((group) => (
        group.id !== undefined && selectedSavedTeamIds.includes(group.id)
    ));

    useEffect(() => {
        if (!teamScheduleOpen || !scheduleImageDate) {
            return;
        }

        const controller = new AbortController();
        const url = new URL(route('fjp.team-schedule-preview'), window.location.origin);
        url.searchParams.set('event_date', scheduleImageDate);
        setSavedTeamGroupsLoading(true);
        setSavedTeamGroupsError('');

        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`Jadwal tim tidak dapat dimuat (HTTP ${response.status}).`);
                }

                return await response.json() as TeamSchedulePreviewResponse;
            })
            .then((response) => {
                setSavedTeamGroups(response.teams);
                setSelectedSavedTeamIds(
                    response.teams.flatMap((group) => group.id ? [group.id] : []),
                );
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                console.error('Gagal memuat pembagian tim tersimpan.', error);
                setSavedTeamGroups([]);
                setSelectedSavedTeamIds([]);
                setSavedTeamGroupsError('Jadwal tim tersimpan gagal dimuat. Coba pilih tanggal kembali.');
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setSavedTeamGroupsLoading(false);
                }
            });

        return () => controller.abort();
    }, [teamScheduleOpen, scheduleImageDate, savedTeamGroupsRevision]);

    useEffect(() => {
        if (!scheduleImageOpen || !scheduleImageInput) {
            return;
        }

        let isCurrent = true;

        const generateImage = async () => {
            try {
                await new Promise<void>((resolve) => {
                    window.requestAnimationFrame(() => {
                        window.requestAnimationFrame(() => resolve());
                    });
                });

                if (isCurrent) {
                    setScheduleImageDataUrl(renderTeamScheduleImage(scheduleImageInput));
                }
            } catch (error) {
                console.error('Gagal membuat gambar jadwal tim.', error);
                if (isCurrent) {
                    setScheduleImageError(
                        error instanceof Error
                            ? `Gambar jadwal gagal dibuat: ${error.message}`
                            : 'Gambar jadwal gagal dibuat karena terjadi kesalahan yang tidak diketahui.',
                    );
                }
            } finally {
                if (isCurrent) {
                    setScheduleImageGenerating(false);
                }
            }
        };

        void generateImage();

        return () => {
            isCurrent = false;
        };
    }, [scheduleImageInput, scheduleImageOpen]);

    const applyFilter = (selectedStatus: string, selectedBrandId = brandId) => {
        const params: Record<string, string> = {};
        if (search) params.search = search;
        if (selectedStatus) params.status = selectedStatus;
        if (selectedBrandId) params.brand_id = selectedBrandId;
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;
        params.per_page = perPage;
        router.get(route('fjp.index'), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setBrandId('');
        setDateFrom('');
        setDateTo('');
        setPerPage('25');
        router.get(route('fjp.index'), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        applyFilter(status);
    };

    const changeStatus = (fjpId: number, value: string) => {
        router.patch(
            route('fjp.status', fjpId),
            { status: value },
            { preserveScroll: true, preserveState: true },
        );
    };

    const openEdit = (f: FjpRow) => {
        setEditingId(f.id);
        form.setData({
            school_name: f.school_name ?? '',
            npsn: f.npsn ?? '',
            address: f.address ?? '',
            city: f.city ?? '',
            province: f.province ?? '',
            kecamatan: f.kecamatan ?? '',
            kelurahan: f.kelurahan ?? '',
            maps_url: f.maps_url ?? '',
            jumlah_siswa_sekolah:
                f.jumlah_siswa_sekolah != null ? String(f.jumlah_siswa_sekolah) : '',
            jumlah_siswa_dapodik:
                f.jumlah_siswa_dapodik != null ? String(f.jumlah_siswa_dapodik) : '',
            dapodik_source: f.dapodik_source ?? '',
            notes: f.notes ?? '',
            brand_id: f.brand_id != null ? String(f.brand_id) : '',
            event_type_id: f.event_type_id != null ? String(f.event_type_id) : '',
            event_date: f.event_date ?? '',
            start_time: f.start_time ?? '',
            sampling_target: f.sampling_target != null ? String(f.sampling_target) : '',
            jug_required: Boolean(f.jug_required),
            permitter_total_outlet_canteen_sell_drink:
                f.permitter_total_outlet_canteen_sell_drink != null
                    ? String(f.permitter_total_outlet_canteen_sell_drink)
                    : '',
            permitter_quantity_with_milo:
                f.permitter_quantity_with_milo != null
                    ? String(f.permitter_quantity_with_milo)
                    : '',
            permitter_status: f.permitter_status ?? '',
            permitter_quantity_without_milo:
                f.permitter_quantity_without_milo != null
                    ? String(f.permitter_quantity_without_milo)
                    : '',
            team_sampling_quantity_with_milo:
                f.team_sampling_quantity_with_milo != null
                    ? String(f.team_sampling_quantity_with_milo)
                    : '',
            team_sampling_quantity_without_milo:
                f.team_sampling_quantity_without_milo != null
                    ? String(f.team_sampling_quantity_without_milo)
                    : '',
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const addDapodikRow = () => {
        setPendingDapodikRows((rows) => [
            ...rows,
            {
                id: crypto.randomUUID(),
                detail: null,
                schoolStudentCount: '',
                teamNumber: canAssignTeam ? pendingTeamCount : null,
                eventDate: '',
                startTime: '',
            },
        ]);
        dapodikRowsForm.clearErrors();
        dapodikRowsSectionRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    };

    const updateDapodikRow = (
        rowId: string,
        changes: Partial<Omit<PendingDapodikSchoolRow, 'id'>>,
    ) => {
        setPendingDapodikRows((rows) => rows.map((row) => (
            row.id === rowId ? { ...row, ...changes } : row
        )));
        dapodikRowsForm.clearErrors();
    };

    const addDapodikTeam = () => {
        setPendingTeamCount((count) => Math.min(count + 1, 25));
    };

    const addDapodikTeamWithRow = () => {
        addDapodikTeam();

        if (pendingDapodikRows.length === 0) {
            addDapodikRow();
        }
    };

    const removeDapodikRow = (rowId: string) => {
        setPendingDapodikRows((rows) => rows.filter((row) => row.id !== rowId));
        dapodikRowsForm.clearErrors();
    };

    const submitDapodikRows = (event: FormEvent) => {
        event.preventDefault();

        if (pendingDapodikRows.some((row) => row.detail === null)) {
            pushToast('error', 'Pilih sekolah dari Dapodik pada setiap baris sebelum menyimpan.');
            return;
        }

        const npsns = pendingDapodikRows.map((row) => row.detail?.npsn ?? '');
        if (new Set(npsns).size !== npsns.length) {
            pushToast('error', 'Sekolah yang sama tidak dapat ditambahkan lebih dari sekali dalam satu simpan.');
            return;
        }

        const schools = pendingDapodikRows.map((row) => ({
            npsn: row.detail?.npsn ?? '',
            jumlah_siswa_sekolah: row.schoolStudentCount === ''
                ? null
                : Number(row.schoolStudentCount),
            ...(canAssignTeam ? { team_number: row.teamNumber } : {}),
            event_date: row.eventDate === '' ? null : row.eventDate,
            start_time: row.startTime === '' ? null : row.startTime,
        }));

        const brandEventTypes = (formOptions?.eventTypes ?? []).filter(
            (type) => String(type.brand_id ?? '') === brandId,
        );
        const eventType = brandId === ''
            ? null
            : brandEventTypes.find((type) => type.slug?.endsWith('-school-sampling'))
                ?? brandEventTypes.find((type) => type.name.toLowerCase().includes('school sampling'))
                ?? brandEventTypes[0]
                ?? null;

        dapodikRowsForm.transform(() => ({
            schools,
            brand_id: brandId === '' ? null : Number(brandId),
            event_type_id: eventType?.id ?? null,
        }));
        dapodikRowsForm.post(route('fjp.dapodik-schools.batch'), {
            preserveScroll: true,
            onSuccess: () => {
                setPendingDapodikRows([]);
                setPendingTeamCount(1);
                dapodikRowsForm.reset();
            },
            onFinish: () => dapodikRowsForm.transform((data) => data),
        });
    };

    const closeForm = () => {
        setFormOpen(false);
        setEditingId(null);
    };

    const submitForm = (e: FormEvent) => {
        e.preventDefault();

        if (!editingId) {
            return;
        }

        form.put(route('fjp.update', editingId), {
            preserveScroll: true,
            onSuccess: () => closeForm(),
        });
    };

    const openTeamSchedule = () => {
        setScheduleImageDate(teamScheduleDate);
        setSavedTeamGroups([]);
        setSavedTeamGroupsError('');
        setTeamScheduleOpen(true);
    };

    const showScheduleImage = (input: TeamScheduleImageInput) => {
        setScheduleImageInput(input);
        setScheduleImageDataUrl('');
        setScheduleImageError('');
        setScheduleImageGenerating(true);
        setScheduleImageOpen(true);
    };

    const downloadScheduleImage = () => {
        if (!scheduleImageDataUrl || !scheduleImageInput) {
            return;
        }

        const link = document.createElement('a');
        link.href = scheduleImageDataUrl;
        link.download = `FJP-${scheduleImageInput.event_date}.png`;
        link.click();
    };

    const shareScheduleImage = async () => {
        if (!scheduleImageDataUrl || !scheduleImageInput) {
            return;
        }

        if (typeof navigator.share !== 'function' || typeof navigator.canShare !== 'function') {
            downloadScheduleImage();
            pushToast('info', 'Gambar diunduh. Lampirkan PNG tersebut saat mengirim lewat WhatsApp.');
            return;
        }

        try {
            const blob = await (await fetch(scheduleImageDataUrl)).blob();
            const file = new File([blob], `Jadwal-Tim-${scheduleImageInput.event_date}.png`, {
                type: 'image/png',
            });

            if (!navigator.canShare({ files: [file] })) {
                downloadScheduleImage();
                pushToast('info', 'Gambar diunduh. Lampirkan PNG tersebut saat mengirim lewat WhatsApp.');
                return;
            }

            await navigator.share({
                title: `Jadwal Tim ${formatGroupDate(scheduleImageInput.event_date)}`,
                files: [file],
            });
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            console.error('Gagal membagikan gambar jadwal tim.', error);
            pushToast('error', 'Gambar jadwal gagal dibagikan. Unduh PNG lalu kirim lewat WhatsApp.');
        }
    };

    const tabs = [
        { key: '', label: 'Semua', count: totalCount },
        ...statusOptions.map((s) => ({
            key: s.value,
            label: s.label,
            count: statusCounts[s.value] ?? 0,
        })),
    ];

    const teamScheduleTimesByFjpId = useMemo(() => {
        const times = new Map<number, string | null>();

        teamSchedules.forEach((team) => {
            team.school_schedule.forEach((school) => {
                times.set(school.fjp_id, school.start_time);
            });
        });

        return times;
    }, [teamSchedules]);

    const dateGroups = useMemo(() => {
        const compareRows = (first: FjpRow, second: FjpRow) => {
            let comparison = 0;

            if (sortColumn === 'schedule') {
                const firstStartTime = teamScheduleTimesByFjpId.get(first.id) ?? first.start_time;
                const secondStartTime = teamScheduleTimesByFjpId.get(second.id) ?? second.start_time;
                comparison = (firstStartTime ?? '99:99').localeCompare(secondStartTime ?? '99:99');
                if (comparison === 0) {
                    comparison = (first.school_name || first.event_name).localeCompare(
                        second.school_name || second.event_name,
                        'id',
                    );
                }
            } else if (sortColumn === 'school') {
                comparison = (first.school_name || first.event_name).localeCompare(
                    second.school_name || second.event_name,
                    'id',
                );
            } else if (sortColumn === 'students') {
                comparison = (first.sekolah ?? -1) - (second.sekolah ?? -1);
            } else {
                comparison = first.status_label.localeCompare(second.status_label, 'id');
            }

            return sortDirection === 'asc' ? comparison : -comparison;
        };
        const rowsByTeam = new Map<string, FjpRow[]>();

        fjps.data.forEach((fjp) => {
            const groupId = fjp.team?.group_id ?? fjp.planned_team_group_id ?? `fjp:${fjp.id}`;
            rowsByTeam.set(groupId, [...(rowsByTeam.get(groupId) ?? []), fjp]);
        });
        const sortedRows = [...rowsByTeam.entries()]
            .map(([groupId, rows]) => ({
                groupId,
                rows: [...rows].sort(compareRows),
            }))
            .sort((first, second) => (
                compareRows(first.rows[0], second.rows[0])
                || first.groupId.localeCompare(second.groupId)
            ))
            .flatMap(({ rows }) => rows);
        const groups = new Map<string, FjpRow[]>();

        sortedRows.forEach((fjp) => {
            const groupDate = fjp.event_date ?? '';
            groups.set(groupDate, [...(groups.get(groupDate) ?? []), fjp]);
        });

        return [...groups.entries()]
            .sort(([firstDate], [secondDate]) => {
                if (!firstDate) return 1;
                if (!secondDate) return -1;
                return firstDate.localeCompare(secondDate);
            })
            .map(([date, rows]) => ({ date, rows }));
    }, [fjps.data, sortColumn, sortDirection, teamScheduleTimesByFjpId]);

    const teamDays = useMemo(() => {
        const uniqueTeams = new Map<string, FjpTeam>();

        teamSchedules.forEach((team) => {
            uniqueTeams.set(team.group_id, team);
        });

        const teamsByDate = new Map<string, FjpTeam[]>();
        [...uniqueTeams.values()]
            .sort((first, second) => {
                const dateComparison = (first.event_date ?? '').localeCompare(second.event_date ?? '');
                if (dateComparison !== 0) return dateComparison;

                const firstStart = first.school_schedule
                    .map((school) => school.start_time)
                    .filter((time): time is string => time !== null)
                    .sort()[0] ?? '99:99';
                const secondStart = second.school_schedule
                    .map((school) => school.start_time)
                    .filter((time): time is string => time !== null)
                    .sort()[0] ?? '99:99';

                return firstStart.localeCompare(secondStart) || first.group_id.localeCompare(second.group_id);
            })
            .forEach((team) => {
                const date = team.event_date ?? '';
                teamsByDate.set(date, [...(teamsByDate.get(date) ?? []), team]);
            });

        return [...teamsByDate.entries()].map(([date, teams]) => ({
            date,
            teams: teams.map((team, index) => ({ ...team, number: index + 1 })),
        }));
    }, [teamSchedules]);

    const visibleColumnCount =
        3 +
        availableColumns.filter(({ key }) => visibleColumns[key]).length;

    const toggleSort = (column: typeof sortColumn) => {
        if (sortColumn === column) {
            setSortDirection((direction) => (direction === 'asc' ? 'desc' : 'asc'));
            return;
        }

        setSortColumn(column);
        setSortDirection('asc');
    };

    return (
        <AppLayout title="FJP">
            <Head title="FJP" />
            <PageHeader
                title="FJP Sekolah"
                subtitle="Confirm berarti pihak sekolah sudah menyetujui kegiatan. Done ditetapkan setelah kegiatan selesai."
            >
                {canAssignTeamMembers && (
                    <Button variant="outline" onClick={openTeamSchedule}>
                        <ImageIcon className="h-4 w-4" />
                        Gambar FJP
                    </Button>
                )}
                {canCreate && (
                    <>
                        <Button variant="primary" onClick={addDapodikRow}>
                            <Plus className="h-4 w-4" /> Tambah baris
                        </Button>

                    </>
                )}
            </PageHeader>

            <section className="mb-5 border-b border-border" aria-label="Navigasi brand">
                <div className="flex gap-1 overflow-x-auto">
                    {[
                        { id: '', name: 'Semua Brand' },
                        ...(formOptions?.brands ?? []).map((brand) => ({
                            id: String(brand.id),
                            name: brand.name,
                        })),
                    ].map((brand) => {
                        const active = brandId === brand.id;

                        return (
                            <button
                                key={brand.id || 'all-brands'}
                                type="button"
                                onClick={() => {
                                    setBrandId(brand.id);
                                    applyFilter(status, brand.id);
                                }}
                                className={cn(
                                    'shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition-colors',
                                    active
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted hover:border-border hover:text-text',
                                )}
                                aria-current={active ? 'page' : undefined}
                            >
                                {brand.name}
                            </button>
                        );
                    })}
                </div>
            </section>

            <section
                className="mb-5 overflow-hidden rounded-xl border border-emerald-900/20 bg-surface"
                aria-label="Navigasi kota FJP"
            >
                <div className="border-b border-emerald-900/20 bg-emerald-800 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-white">
                    Sheet Kota
                </div>
                <div className="flex gap-1 overflow-x-auto bg-page/60 px-2 pt-2">
                    <span
                        className="shrink-0 border-b-2 border-emerald-700 bg-surface px-3 py-2 text-xs font-semibold text-emerald-900 dark:text-emerald-200"
                        aria-current="page"
                    >
                        JABODETABEK
                    </span>
                </div>
            </section>

            <FilterCard className="mb-4">
                <form onSubmit={submit} className="flex flex-col gap-3">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label className="flex min-w-64 flex-1 flex-col gap-1.5 text-xs font-medium text-muted">
                            Cari sekolah atau nomor FJP
                            <span className="relative">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                                <Input
                                    className="pl-9"
                                    placeholder="Nama sekolah, event, atau nomor FJP…"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </span>
                        </label>
                        <label className="flex flex-col gap-1.5 text-xs font-medium text-muted">
                            Dari tanggal
                            <Input
                                type="date"
                                className="lg:w-44"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </label>
                        <label className="flex flex-col gap-1.5 text-xs font-medium text-muted">
                            Sampai tanggal
                            <Input
                                type="date"
                                className="lg:w-44"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </label>
                        <div className="flex gap-2">
                            <Button type="submit" className="flex-1 lg:flex-none">
                                Terapkan
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetFilters}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </form>
            </FilterCard>

            {showLegacyDapodikSection && (
                <section
                    ref={dapodikRowsSectionRef}
                    className="mb-4 overflow-hidden rounded-xl border border-border bg-surface"
                >
                    <div className="flex flex-col gap-3 border-b border-border bg-page/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-sm font-semibold text-text">Baris FJP baru</h2>
                            <p className="mt-1 text-xs text-muted">
                                Pilih sekolah dari Dapodik dan kelompokkan ke tim. Anggota tim baru ditentukan setelah FJP berstatus Confirm.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {pendingDapodikRows.length > 0 && (
                                <span className="text-xs text-muted">
                                    {pendingDapodikRows.length} baris
                                </span>
                            )}
                            {canAssignTeam && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addDapodikTeam}
                                    disabled={pendingTeamCount >= 25 || dapodikRowsForm.processing}
                                >
                                    <Plus className="h-4 w-4" />
                                    Tambah tim
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addDapodikRow}
                                disabled={pendingDapodikRows.length >= 25 || dapodikRowsForm.processing}
                            >
                                <Plus className="h-4 w-4" />
                                Tambah baris
                            </Button>
                            {pendingDapodikRows.length > 0 && (
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    form="fjp-dapodik-batch-form"
                                    disabled={
                                        dapodikRowsForm.processing ||
                                        pendingDapodikRows.some((row) => row.detail === null)
                                    }
                                >
                                    {dapodikRowsForm.processing
                                        ? 'Menyimpan…'
                                        : `Simpan ${pendingDapodikRows.length} FJP draft`}
                                </Button>
                            )}
                        </div>
                    </div>
                    {pendingDapodikRows.length === 0 ? (
                        <p className="px-4 py-4 text-sm text-muted">
                            Tekan <span className="font-semibold text-text">Tambah baris</span>, lalu pilih sekolah Dapodik pada baris tersebut.
                        </p>
                    ) : (
                        <form id="fjp-dapodik-batch-form" onSubmit={submitDapodikRows}>
                            <div className="max-h-80 overflow-auto">
                                <table className="min-w-[760px] w-full border-collapse text-xs [&_td]:border [&_td]:border-border [&_td]:px-2 [&_td]:py-2 [&_th]:border [&_th]:border-emerald-900/30 [&_th]:bg-emerald-800 [&_th]:px-2 [&_th]:py-2 [&_th]:text-left [&_th]:text-white">
                                    <thead>
                                        <tr>
                                            <th className="w-12">No</th>
                                            <th className="min-w-[360px]">Sekolah (Dapodik)</th>
                                            <th className="min-w-[240px]">NPSN &amp; lokasi otomatis</th>
                                            <th className="min-w-40">Jumlah siswa sekolah</th>
                                            <th className="min-w-32">Jam mulai</th>
                                            {canAssignTeam && <th className="min-w-32">Kelompok tim</th>}
                                            <th className="w-14 text-center">Hapus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pendingDapodikRows.map((row, index) => {
                                            const rowError = dapodikRowsForm.errors[
                                                `schools.${index}.npsn` as keyof typeof dapodikRowsForm.errors
                                            ];
                                            const studentCountError = dapodikRowsForm.errors[
                                                `schools.${index}.jumlah_siswa_sekolah` as keyof typeof dapodikRowsForm.errors
                                            ];

                                            return (
                                                <tr key={row.id} className="align-top even:bg-page/40">
                                                    <td className="tabular-nums text-muted">
                                                        {String(index + 1).padStart(2, '0')}
                                                    </td>
                                                    <td>
                                                        {row.detail ? (
                                                            <div>
                                                                <p className="font-semibold text-text">
                                                                    {row.detail.school_name || 'Nama sekolah tidak tersedia'}
                                                                </p>
                                                                <p className="mt-1 text-[11px] text-muted">
                                                                    Data sekolah dikonfirmasi melalui Dapodik
                                                                </p>
                                                            </div>
                                                        ) : (
                                                            <SchoolPicker
                                                                jabodetabekOnly
                                                                onPick={(detail) => updateDapodikRow(row.id, {
                                                                    detail,
                                                                })}
                                                                onError={(message) => pushToast('error', message)}
                                                            />
                                                        )}
                                                        {rowError && (
                                                            <p role="alert" className="mt-1 text-[11px] text-danger">
                                                                {rowError}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td>
                                                        {row.detail ? (
                                                            <div className="space-y-1">
                                                                <p className="font-mono text-text">{row.detail.npsn}</p>
                                                                <p className="text-[11px] text-muted">
                                                                    {[
                                                                        row.detail.kabupaten,
                                                                        row.detail.kecamatan,
                                                                        row.detail.kelurahan,
                                                                    ].filter(Boolean).join(' · ') || 'Lokasi tidak tersedia'}
                                                                </p>
                                                                <p className="text-[11px] text-muted">
                                                                    Siswa Dapodik: {row.detail.jumlah_siswa ?? '—'}
                                                                </p>
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted">Pilih sekolah untuk mengisi otomatis</span>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={row.schoolStudentCount}
                                                            placeholder="Opsional"
                                                            aria-label={`Jumlah siswa sekolah baris ${index + 1}`}
                                                            onChange={(event) => updateDapodikRow(row.id, {
                                                                schoolStudentCount: event.target.value,
                                                            })}
                                                        />
                                                        {studentCountError && (
                                                            <p role="alert" className="mt-1 text-[11px] text-danger">
                                                                {studentCountError}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <Input
                                                            type="time"
                                                            value={row.startTime}
                                                            aria-label={`Jam mulai baris ${index + 1}`}
                                                            onChange={(event) => updateDapodikRow(row.id, {
                                                                startTime: event.target.value,
                                                            })}
                                                        />
                                                    </td>
                                                    {canAssignTeam && (
                                                        <td>
                                                            <Select
                                                                value={row.teamNumber === null ? '' : String(row.teamNumber)}
                                                                aria-label={`Kelompok tim baris ${index + 1}`}
                                                                onChange={(event) => updateDapodikRow(row.id, {
                                                                    teamNumber: event.target.value === ''
                                                                        ? null
                                                                        : Number(event.target.value),
                                                                })}
                                                            >
                                                                <option value="">Belum ditentukan</option>
                                                                {Array.from({ length: pendingTeamCount }, (_, teamIndex) => (
                                                                    <option key={teamIndex + 1} value={teamIndex + 1}>
                                                                        Tim {teamIndex + 1}
                                                                    </option>
                                                                ))}
                                                            </Select>
                                                        </td>
                                                    )}
                                                    <td className="text-center">
                                                        <button
                                                            type="button"
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-muted hover:bg-danger/10 hover:text-danger"
                                                            aria-label={`Hapus baris ${index + 1}`}
                                                            onClick={() => removeDapodikRow(row.id)}
                                                            disabled={dapodikRowsForm.processing}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            {dapodikRowsForm.errors.schools && (
                                <p role="alert" className="px-4 pt-3 text-xs text-danger">
                                    {dapodikRowsForm.errors.schools}
                                </p>
                            )}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addDapodikRow}
                                    disabled={pendingDapodikRows.length >= 25 || dapodikRowsForm.processing}
                                >
                                    <Plus className="h-4 w-4" />
                                    Tambah baris
                                </Button>
                                <p className="text-[11px] text-muted">
                                    Maksimal 25 sekolah per simpan. Data disimpan sebagai FJP draft; anggota tim ditambahkan setelah Confirm.
                                </p>
                            </div>
                        </form>
                    )}
                </section>
            )}

            <section className="mb-4" aria-label="Filter status FJP">
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                    Status FJP
                </p>
                <Tabs
                    items={tabs}
                    active={status}
                    onChange={(key) => {
                        setStatus(key);
                        applyFilter(key);
                    }}
                />
            </section>

            <CardTable>
                {fjps.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada FJP"
                        description="Buat FJP baru untuk memulai."
                    />
                ) : (
                    <>
                        <div className="flex flex-col gap-3 border-b border-border bg-page/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-muted">
                                Menampilkan <span className="font-semibold text-text">{fjps.data.length}</span> baris pada halaman ini
                                <span className="mx-1.5 text-border">·</span>
                                Dikelompokkan berdasarkan tanggal
                            </p>
                            <div className="flex flex-wrap items-center justify-end gap-2">
                                {(canCreate || canAssignTeam) && (
                                    <div className="flex items-center gap-2 rounded-lg border border-border bg-surface p-1">
                                        {canCreate && (
                                            <Button
                                                type="button"
                                                variant="primary"
                                                size="sm"
                                                onClick={addDapodikRow}
                                                disabled={pendingDapodikRows.length >= 25 || dapodikRowsForm.processing}
                                            >
                                                <Plus className="h-4 w-4" />
                                                Tambah baris
                                            </Button>
                                        )}
                                        {canAssignTeam && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={addDapodikTeamWithRow}
                                                disabled={pendingTeamCount >= 25 || dapodikRowsForm.processing}
                                            >
                                                <Plus className="h-4 w-4" />
                                                Tambah tim
                                            </Button>
                                        )}
                                    </div>
                                )}
                                <label className="flex h-10 items-center gap-2 rounded-lg border border-border bg-surface px-2 text-xs text-muted">
                                    <span>Baris</span>
                                    <Select
                                        className="h-8 min-w-16 border-0 bg-transparent px-1"
                                        value={perPage}
                                        onChange={(event) => {
                                            setPerPage(event.target.value);
                                            const params: Record<string, string> = { per_page: event.target.value };
                                            if (search) params.search = search;
                                            if (status) params.status = status;
                                            if (brandId) params.brand_id = brandId;
                                            if (dateFrom) params.date_from = dateFrom;
                                            if (dateTo) params.date_to = dateTo;
                                            router.get(route('fjp.index'), params, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </Select>
                                </label>
                                <details className="relative self-start sm:self-auto">
                                <summary className="flex h-9 cursor-pointer list-none items-center gap-2 rounded-lg border border-border bg-surface px-3 text-xs font-medium text-text hover:bg-page">
                                    <Columns3 className="h-4 w-4 text-muted" />
                                    Kolom
                                    <ChevronDown className="h-3.5 w-3.5 text-muted" />
                                </summary>
                                <div className="absolute right-0 z-30 mt-2 w-52 rounded-xl border border-border bg-surface p-2 shadow-lg">
                                    {availableColumns.map(({ key, label }) => (
                                        <label
                                            key={key}
                                            className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-text hover:bg-page"
                                        >
                                            <input
                                                type="checkbox"
                                                className="accent-primary"
                                                checked={visibleColumns[key]}
                                                onChange={(event) =>
                                                    setVisibleColumns((columns) => ({
                                                        ...columns,
                                                        [key]: event.target.checked,
                                                    }))
                                                }
                                            />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                                </details>
                            </div>
                        </div>
                        {pendingDapodikRows.length > 0 && (
                            <form
                                id="fjp-dapodik-inline-form"
                                onSubmit={submitDapodikRows}
                                className="border-b border-border bg-surface px-4 py-3"
                            >
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold text-text">
                                            Baris FJP baru
                                        </p>
                                        <p className="mt-0.5 text-[11px] text-muted">
                                            Pilih sekolah dan lengkapi jadwal
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="rounded-full bg-page px-2.5 py-1 text-xs font-medium text-muted">
                                            {pendingDapodikRows.length} baris
                                        </span>
                                        <Button
                                            type="submit"
                                            variant="primary"
                                            size="sm"
                                            disabled={
                                                dapodikRowsForm.processing ||
                                                pendingDapodikRows.some((row) => row.detail === null)
                                            }
                                        >
                                            Simpan {pendingDapodikRows.length} baris
                                        </Button>
                                    </div>
                                </div>
                                <div className="max-h-72 space-y-2 overflow-auto">
                                    {pendingDapodikRows.map((row, index) => (
                                        <div
                                            key={row.id}
                                            className="grid items-start gap-2 rounded-lg border border-border p-2 md:grid-cols-[minmax(280px,2fr)_minmax(150px,.8fr)_minmax(140px,.7fr)_minmax(130px,.7fr)_minmax(130px,.7fr)_40px]"
                                        >
                                            <div>
                                                <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-muted">
                                                    Sekolah
                                                </p>
                                                {row.detail ? (
                                                    <div>
                                                        <p className="text-xs font-semibold text-text">{row.detail.school_name}</p>
                                                        <p className="mt-1 text-[11px] text-muted">
                                                            NPSN {row.detail.npsn} · {[row.detail.kecamatan, row.detail.kelurahan].filter(Boolean).join(' · ') || row.detail.kabupaten}
                                                        </p>
                                                        <p className="mt-1 text-[11px] font-medium text-primary">
                                                            Siswa Dapodik: {formatNumber(row.detail.jumlah_siswa)}
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <SchoolPicker
                                                        jabodetabekOnly
                                                        onPick={(detail) => updateDapodikRow(row.id, { detail })}
                                                        onError={(message) => pushToast('error', message)}
                                                    />
                                                )}
                                            </div>
                                            <label className="block">
                                                <span className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Jumlah siswa</span>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={row.schoolStudentCount}
                                                    placeholder="Jumlah siswa"
                                                    aria-label={`Jumlah siswa baris ${index + 1}`}
                                                    onChange={(event) => updateDapodikRow(row.id, {
                                                        schoolStudentCount: event.target.value,
                                                    })}
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Tanggal</span>
                                                <Input
                                                    type="date"
                                                    value={row.eventDate}
                                                    aria-label={`Tanggal pelaksanaan baris ${index + 1}`}
                                                    onChange={(event) => updateDapodikRow(row.id, {
                                                        eventDate: event.target.value,
                                                    })}
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Jam mulai</span>
                                                <Input
                                                    type="time"
                                                    value={row.startTime}
                                                    aria-label={`Jam mulai baris ${index + 1}`}
                                                    onChange={(event) => updateDapodikRow(row.id, {
                                                        startTime: event.target.value,
                                                    })}
                                                />
                                            </label>
                                            {canAssignTeam ? (
                                                <label className="block">
                                                    <span className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-muted">Tim</span>
                                                    <Select
                                                        value={row.teamNumber === null ? '' : String(row.teamNumber)}
                                                        aria-label={`Kelompok tim baris ${index + 1}`}
                                                        onChange={(event) => updateDapodikRow(row.id, {
                                                            teamNumber: event.target.value === '' ? null : Number(event.target.value),
                                                        })}
                                                    >
                                                        <option value="">Pilih tim</option>
                                                        {Array.from({ length: pendingTeamCount }, (_, teamIndex) => (
                                                            <option key={teamIndex + 1} value={teamIndex + 1}>
                                                                Tim {teamIndex + 1}
                                                            </option>
                                                        ))}
                                                    </Select>
                                                </label>
                                            ) : <span />}
                                            <button
                                                type="button"
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-muted hover:bg-danger/10 hover:text-danger"
                                                aria-label={`Hapus baris ${index + 1}`}
                                                onClick={() => removeDapodikRow(row.id)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </form>
                        )}
                        <div className="max-h-[70vh] overflow-auto">
                            <table className="min-w-max w-full border-collapse caption-bottom text-xs [&_td]:border [&_td]:border-border [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-emerald-900/30 [&_th]:px-2 [&_th]:py-2">
                                <THead className="sticky top-0 z-20 [&_th]:text-white">
                                    <TR className="bg-emerald-800 hover:bg-emerald-800">
                                        <TH rowSpan={2} className="w-12 whitespace-nowrap">No</TH>
                                        <TH rowSpan={2} className="min-w-64">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1"
                                                onClick={() => toggleSort('school')}
                                            >
                                                Identitas sekolah
                                                <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', sortColumn === 'school' && sortDirection === 'asc' && 'rotate-180')} />
                                            </button>
                                        </TH>
                                        {(visibleColumns.schedule || visibleColumns.status) && (
                                            <TH
                                                colSpan={
                                                    Number(visibleColumns.schedule) +
                                                    Number(visibleColumns.status)
                                                }
                                                className="text-center"
                                            >
                                                Periode &amp; identitas
                                            </TH>
                                        )}
                                        {visibleColumns.students && (
                                            <TH colSpan={1} className="text-center">Data siswa</TH>
                                        )}
                                        {(visibleColumns.city || visibleColumns.kecamatan || visibleColumns.kelurahan) && (
                                            <TH
                                                colSpan={
                                                    Number(visibleColumns.city) +
                                                    Number(visibleColumns.kecamatan) +
                                                    Number(visibleColumns.kelurahan)
                                                }
                                                className="text-center"
                                            >
                                                Lokasi administratif
                                            </TH>
                                        )}
                                        {(visibleColumns.location || visibleColumns.notes) && (
                                            <TH
                                                colSpan={
                                                    Number(visibleColumns.location) +
                                                    Number(visibleColumns.notes)
                                                }
                                                className="text-center"
                                            >
                                                Informasi &amp; tracking
                                            </TH>
                                        )}
                                        {(
                                            visibleColumns.permitter_total_outlet ||
                                            visibleColumns.permitter_with_milo ||
                                            visibleColumns.permitter_status ||
                                            visibleColumns.permitter_without_milo ||
                                            visibleColumns.permitter_percentage
                                        ) && (
                                            <TH
                                                colSpan={
                                                    Number(visibleColumns.permitter_total_outlet) +
                                                    Number(visibleColumns.permitter_with_milo) +
                                                    Number(visibleColumns.permitter_status) +
                                                    Number(visibleColumns.permitter_without_milo) +
                                                    Number(visibleColumns.permitter_percentage)
                                                }
                                                className="text-center"
                                            >
                                                Validasi Permitter (pre-sampling)
                                            </TH>
                                        )}
                                        {(visibleColumns.team_sampling_with_milo || visibleColumns.team_sampling_without_milo || visibleColumns.team_sampling_percentage) && (
                                            <TH
                                                colSpan={
                                                    Number(visibleColumns.team_sampling_with_milo) +
                                                    Number(visibleColumns.team_sampling_without_milo) +
                                                    Number(visibleColumns.team_sampling_percentage)
                                                }
                                                className="text-center"
                                            >
                                                Validasi Team Sampling (during sampling)
                                            </TH>
                                        )}
                                        <TH rowSpan={2} className="text-right">Aksi</TH>
                                    </TR>
                                    <TR className="bg-emerald-700 hover:bg-emerald-700">
                                        {visibleColumns.schedule && (
                                            <TH className="min-w-36 whitespace-nowrap">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1"
                                                    onClick={() => toggleSort('schedule')}
                                                >
                                                    Tanggal &amp; waktu
                                                    <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', sortColumn === 'schedule' && sortDirection === 'asc' && 'rotate-180')} />
                                                </button>
                                            </TH>
                                        )}
                                        {visibleColumns.status && (
                                            <TH className="whitespace-nowrap">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1"
                                                    onClick={() => toggleSort('status')}
                                                >
                                                    Status FJP
                                                    <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', sortColumn === 'status' && sortDirection === 'asc' && 'rotate-180')} />
                                                </button>
                                            </TH>
                                        )}
                                        {visibleColumns.students && (
                                            <TH className="text-right">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1"
                                                    onClick={() => toggleSort('students')}
                                                >
                                                    Jumlah siswa
                                                    <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', sortColumn === 'students' && sortDirection === 'asc' && 'rotate-180')} />
                                                </button>
                                            </TH>
                                        )}
                                        {visibleColumns.city && <TH className="min-w-36">Kota</TH>}
                                        {visibleColumns.kecamatan && <TH className="min-w-36">Kecamatan</TH>}
                                        {visibleColumns.kelurahan && <TH className="min-w-36">Kelurahan</TH>}
                                        {visibleColumns.location && <TH className="min-w-64">Lokasi</TH>}
                                        {visibleColumns.notes && <TH className="min-w-48">Catatan</TH>}
                                        {visibleColumns.permitter_total_outlet && (
                                            <TH className="min-w-36">Total Outlet Canteen (Sell Drink)</TH>
                                        )}
                                        {visibleColumns.permitter_with_milo && (
                                            <TH className="min-w-32">Kuantitas with MILO</TH>
                                        )}
                                        {visibleColumns.permitter_status && (
                                            <TH className="min-w-32">Status</TH>
                                        )}
                                        {visibleColumns.permitter_without_milo && (
                                            <TH className="min-w-32">Kuantitas without MILO</TH>
                                        )}
                                        {visibleColumns.permitter_percentage && (
                                            <TH className="min-w-16">%</TH>
                                        )}
                                        {visibleColumns.team_sampling_with_milo && (
                                            <TH className="min-w-32">Kuantitas with MILO</TH>
                                        )}
                                        {visibleColumns.team_sampling_without_milo && (
                                            <TH className="min-w-32">Kuantitas without MILO</TH>
                                        )}
                                        {visibleColumns.team_sampling_percentage && (
                                            <TH className="min-w-16">%</TH>
                                        )}
                                    </TR>
                                </THead>
                                {dateGroups.map(({ date, rows }) => {
                                    const groupKey = date || 'undated';
                                    const collapsed = collapsedDates.includes(groupKey);

                                    return (
                                        <TBody key={groupKey}>
                                            <TR className="bg-emerald-100 hover:bg-emerald-100 dark:bg-emerald-950">
                                                <TD colSpan={visibleColumnCount} className="border-y border-emerald-700/40 py-2 text-emerald-950 dark:text-emerald-100">
                                                    <button
                                                        type="button"
                                                        className="flex w-full items-center gap-2 text-left"
                                                        onClick={() =>
                                                            setCollapsedDates((dates) =>
                                                                collapsed
                                                                    ? dates.filter((item) => item !== groupKey)
                                                                    : [...dates, groupKey],
                                                            )
                                                        }
                                                        aria-expanded={!collapsed}
                                                    >
                                                        {collapsed ? (
                                                            <ChevronRight className="h-4 w-4 text-muted" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4 text-primary" />
                                                        )}
                                                        <CalendarDays className="h-4 w-4 text-primary" />
                                                        <span className="text-xs font-bold uppercase tracking-wide text-emerald-950 dark:text-emerald-100">
                                                            {formatGroupDate(date || null)}
                                                        </span>
                                                        <span className="rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-semibold text-emerald-900 dark:bg-emerald-900 dark:text-emerald-100">
                                                            {rows.length} sekolah
                                                        </span>
                                                    </button>
                                                </TD>
                                            </TR>
                                            {!collapsed &&
                                                rows.map((f, index) => (
                                                    <TR key={f.id} className="align-top even:bg-page/40 hover:bg-emerald-50/70 dark:hover:bg-emerald-950/30">
                                                        <TD className="text-xs tabular-nums text-muted">
                                                            {String(index + 1).padStart(2, '0')}
                                                        </TD>
                                                        <TD>
                                                            {canUpdate ? (
                                                                <InlineDapodikSchool
                                                                    fjpId={f.id}
                                                                    schoolName={f.school_name || f.event_name}
                                                                    npsn={f.npsn}
                                                                    disabled={!canUpdate}
                                                                />
                                                            ) : (
                                                                <Link
                                                                    href={route('fjp.show', f.id)}
                                                                    className="font-semibold text-text hover:text-primary"
                                                                >
                                                                    {f.school_name || f.event_name}
                                                                </Link>
                                                            )}
                                                            <p className="mt-1 font-mono text-[11px] text-muted">
                                                                {canUpdate ? (
                                                                    <>
                                                                        <span className="mr-1 font-sans text-[10px] text-muted">NPSN</span>
                                                                    <InlineText
                                                                        fjpId={f.id}
                                                                        field="npsn"
                                                                        value={f.npsn}
                                                                        disabled={!canUpdate}
                                                                        placeholder="NPSN"
                                                                        className="inline-flex h-7 w-28 font-mono"
                                                                    />
                                                                    </>
                                                                ) : `NPSN ${f.npsn || '-'}`}
                                                            </p>
                                                            {f.brand && (
                                                                <span className="mt-1 inline-flex rounded-md bg-page px-2 py-0.5 text-[11px] font-medium text-muted">
                                                                    {f.brand}
                                                                </span>
                                                            )}
                                                            {f.dapodik_source && String(f.dapodik_source).toLowerCase().includes('simulasi') && (
                                                                <span className="mt-1 block text-[10px] font-medium text-warning">
                                                                    Data Simulasi/Demo
                                                                </span>
                                                            )}
                                                        </TD>
                                                        {visibleColumns.schedule && (
                                                            <TD>
                                                                <div className="space-y-1">
                                                                    <InlineText
                                                                        fjpId={f.id}
                                                                        field="event_date"
                                                                        value={f.event_date}
                                                                        disabled={!canUpdate}
                                                                        inputType="date"
                                                                        className="min-w-36"
                                                                    />
                                                                    <InlineText
                                                                        fjpId={f.id}
                                                                        field="start_time"
                                                                        value={f.start_time}
                                                                        disabled={!canUpdate}
                                                                        inputType="time"
                                                                        className="min-w-28"
                                                                    />
                                                                </div>
                                                            </TD>
                                                        )}
                                                        {visibleColumns.status && (
                                                            <TD>
                                                                {canUpdateStatus && f.status !== 'done' ? (
                                                                    <div className="space-y-1.5">
                                                                        <FjpStatusBadge status={f.status} label={f.status_label} />
                                                                        <Select
                                                                            className="min-w-36"
                                                                            value={f.status}
                                                                            onChange={(e) => changeStatus(f.id, e.target.value)}
                                                                        >
                                                                            {statusOptions.map((option) => (
                                                                                <option key={option.value} value={option.value}>
                                                                                    {option.label}
                                                                                </option>
                                                                            ))}
                                                                        </Select>
                                                                    </div>
                                                                ) : (
                                                                    <FjpStatusBadge status={f.status} label={f.status_label} />
                                                                )}
                                                            </TD>
                                                        )}
                                                        {visibleColumns.students && (
                                                            <TD className="text-right">
                                                                <div className="space-y-1 font-mono text-xs">
                                                                    <p>
                                                                        <span className="mr-1 font-sans text-[10px] text-muted">Sekolah</span>
                                                                        {canUpdate ? (
                                                                            <InlineText
                                                                                fjpId={f.id}
                                                                                field="jumlah_siswa_sekolah"
                                                                                value={f.jumlah_siswa_sekolah}
                                                                                disabled={!canUpdate}
                                                                                inputType="number"
                                                                                className="mt-1 min-w-24"
                                                                            />
                                                                        ) : formatNumber(f.sekolah)}
                                                                    </p>
                                                                    <p>
                                                                        <span className="mr-1 font-sans text-[10px] text-muted">Dapodik</span>
                                                                        {formatNumber(f.dapodik)}
                                                                    </p>
                                                                    {f.difference !== null && f.difference !== 0 && (
                                                                        <p className={cn('text-[10px]', f.difference > 0 ? 'text-warning' : 'text-info')}>
                                                                            Selisih {f.difference > 0 ? '+' : ''}{formatNumber(f.difference)}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </TD>
                                                        )}
                                                        {visibleColumns.city && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="city"
                                                                    value={f.city}
                                                                    disabled={!canUpdate}
                                                                    placeholder="Kota"
                                                                    className="min-w-32"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.kecamatan && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="kecamatan"
                                                                    value={f.kecamatan}
                                                                    disabled={!canUpdate}
                                                                    placeholder="Kecamatan"
                                                                    className="min-w-32"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.kelurahan && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="kelurahan"
                                                                    value={f.kelurahan}
                                                                    disabled={!canUpdate}
                                                                    placeholder="Kelurahan"
                                                                    className="min-w-32"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.location && (
                                                            <TD>
                                                                <div className="space-y-2">
                                                                    <InlineText
                                                                        fjpId={f.id}
                                                                        field="address"
                                                                        value={f.address}
                                                                        disabled={!canUpdate}
                                                                        placeholder="Alamat lokasi…"
                                                                        className="min-w-56"
                                                                    />
                                                                    {f.province && (
                                                                        <p className="text-[11px] text-muted">
                                                                            {f.province}
                                                                        </p>
                                                                    )}
                                                                    <div className="flex items-start gap-1">
                                                                        <InlineText
                                                                            fjpId={f.id}
                                                                            field="maps_url"
                                                                            value={f.maps_url}
                                                                            disabled={!canUpdate}
                                                                            placeholder="Link Google Maps…"
                                                                            className="min-w-56"
                                                                        />
                                                                        {f.maps_url && (
                                                                            <a
                                                                                href={f.maps_url}
                                                                                target="_blank"
                                                                                rel="noreferrer"
                                                                                title="Buka di Google Maps"
                                                                                className="mt-2 text-primary hover:text-primary-hover"
                                                                            >
                                                                                <MapPin className="h-4 w-4" />
                                                                            </a>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </TD>
                                                        )}
                                                        {visibleColumns.notes && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="notes"
                                                                    value={f.notes}
                                                                    disabled={!canUpdate}
                                                                    placeholder="Tambah catatan…"
                                                                    className="min-w-48"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.permitter_total_outlet && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="permitter_total_outlet_canteen_sell_drink"
                                                                    value={f.permitter_total_outlet_canteen_sell_drink}
                                                                    disabled={!canUpdate}
                                                                    inputType="number"
                                                                    placeholder="0"
                                                                    className="min-w-28"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.permitter_with_milo && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="permitter_quantity_with_milo"
                                                                    value={f.permitter_quantity_with_milo}
                                                                    disabled={!canUpdate}
                                                                    inputType="number"
                                                                    placeholder="0"
                                                                    className="min-w-24"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.permitter_status && (
                                                            <TD>
                                                                <InlineSelect
                                                                    fjpId={f.id}
                                                                    field="permitter_status"
                                                                    value={f.permitter_status}
                                                                    disabled={!canUpdate}
                                                                    options={['MILO SACHET', 'MILO UHT', 'NO MILO']}
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.permitter_without_milo && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="permitter_quantity_without_milo"
                                                                    value={f.permitter_quantity_without_milo}
                                                                    disabled={!canUpdate}
                                                                    inputType="number"
                                                                    placeholder="0"
                                                                    className="min-w-24"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.permitter_percentage && (
                                                            <TD className="text-center font-semibold text-text">
                                                                {formatSamplingPercentage(
                                                                    f.permitter_quantity_with_milo,
                                                                    f.permitter_total_outlet_canteen_sell_drink,
                                                                )}
                                                            </TD>
                                                        )}
                                                        {visibleColumns.team_sampling_with_milo && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="team_sampling_quantity_with_milo"
                                                                    value={f.team_sampling_quantity_with_milo}
                                                                    disabled={!canUpdate}
                                                                    inputType="number"
                                                                    placeholder="0"
                                                                    className="min-w-24"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.team_sampling_without_milo && (
                                                            <TD>
                                                                <InlineText
                                                                    fjpId={f.id}
                                                                    field="team_sampling_quantity_without_milo"
                                                                    value={f.team_sampling_quantity_without_milo}
                                                                    disabled={!canUpdate}
                                                                    inputType="number"
                                                                    placeholder="0"
                                                                    className="min-w-24"
                                                                />
                                                            </TD>
                                                        )}
                                                        {visibleColumns.team_sampling_percentage && (
                                                            <TD className="text-center font-semibold text-text">
                                                                {formatSamplingPercentage(
                                                                    f.team_sampling_quantity_with_milo,
                                                                    f.team_sampling_quantity_with_milo !== null &&
                                                                        f.team_sampling_quantity_without_milo !== null
                                                                        ? f.team_sampling_quantity_with_milo +
                                                                              f.team_sampling_quantity_without_milo
                                                                        : null,
                                                                )}
                                                            </TD>
                                                        )}
                                                        <TD className="text-right">
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Lihat Jadwal ${f.school_name || f.event_name}`}
                                                                    title="Lihat Jadwal"
                                                                    onClick={() => setSelectedDetail(f)}
                                                                >
                                                                    <CalendarDays className="h-4 w-4" />
                                                                </Button>
                                                                {canUpdate && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        aria-label={`Edit ${f.school_name || f.event_name}`}
                                                                        title="Edit FJP"
                                                                        onClick={() => openEdit(f)}
                                                                    >
                                                                        <Pencil className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                {canDelete && (
                                                                    <ConfirmButton
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        title="Hapus FJP"
                                                                        description={`Hapus FJP ${f.school_name || f.event_name}?`}
                                                                        confirmLabel="Hapus"
                                                                        onConfirm={() => router.delete(route('fjp.destroy', f.id))}
                                                                    >
                                                                        <Trash2 className="h-4 w-4 text-danger" />
                                                                    </ConfirmButton>
                                                                )}
                                                            </div>
                                                        </TD>
                                                    </TR>
                                                ))}
                                        </TBody>
                                    );
                                })}
                            </table>
                        </div>
                        <Pagination paginator={fjps} />
                    </>
                )}
            </CardTable>

            <Modal
                open={teamScheduleOpen}
                onClose={() => setTeamScheduleOpen(false)}
                size="xl"
                title="Gambar FJP"
                description="Pilih tanggal FJP untuk membuat gambar pembagian tim. Anggota tim diatur langsung dari tabel FJP setelah status Confirm."
                footer={
                    <div className="flex w-full flex-wrap justify-between gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={savedTeamGroupsLoading || selectedSavedTeams.length === 0}
                            onClick={() => showScheduleImage({
                                event_date: scheduleImageDate,
                                teams: selectedSavedTeams,
                            })}
                        >
                            <ImageIcon className="h-4 w-4" />
                            Buat Gambar FJP ({selectedSavedTeams.length} tim)
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => setTeamScheduleOpen(false)}>
                            Tutup
                        </Button>
                    </div>
                }
            >
                <div className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] sm:items-end">
                        <Field label="Tanggal pelaksanaan">
                            <Input
                                type="date"
                                min={teamScheduleDate}
                                value={scheduleImageDate}
                                onChange={(event) => setScheduleImageDate(event.target.value)}
                                required
                            />
                        </Field>
                        <p className="rounded-lg border border-warning/30 bg-warning/10 p-3 text-xs leading-relaxed text-text">
                            Gambar dibuat dari tim yang sudah dibagi PIC di tabel FJP. Centang tim yang ingin dimasukkan, lalu unduh atau bagikan PNG-nya.
                        </p>
                    </div>

                    <section className="space-y-3 rounded-xl border border-border bg-page/50 p-4">
                        <h3 className="text-sm font-semibold text-text">
                            Tim pada {formatGroupDate(scheduleImageDate)}
                        </h3>

                        {savedTeamGroupsError && (
                            <p role="alert" className="text-xs text-danger">{savedTeamGroupsError}</p>
                        )}
                        {!savedTeamGroupsLoading && !savedTeamGroupsError && savedTeamGroups.length === 0 && (
                            <p className="text-xs text-muted">
                                Belum ada tim dengan anggota pada tanggal ini. Pilih anggota tim langsung di tabel FJP terlebih dahulu.
                            </p>
                        )}
                        {savedTeamGroups.length > 0 && (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {savedTeamGroups.map((group, index) => {
                                    const groupId = group.id;

                                    return (
                                        <label
                                            key={groupId ?? `team-${index}`}
                                            className="space-y-2 rounded-lg border border-border bg-surface p-3"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <p className="text-xs font-semibold text-text">
                                                    Tim {index + 1} · {group.schools.length} sekolah · {formatNumber(group.total_students)} siswa
                                                </p>
                                                {groupId && (
                                                    <input
                                                        type="checkbox"
                                                        className="mt-0.5 accent-primary"
                                                        checked={selectedSavedTeamIds.includes(groupId)}
                                                        onChange={(event) => {
                                                            setSelectedSavedTeamIds((selected) => (
                                                                event.target.checked
                                                                    ? [...selected, groupId]
                                                                    : selected.filter((id) => id !== groupId)
                                                            ));
                                                        }}
                                                    />
                                                )}
                                            </div>
                                            <p className="text-[11px] text-muted">
                                                {group.members.map((member) => `${member.position}: ${member.name}`).join(' · ')}
                                            </p>
                                            <p className="text-[11px] leading-relaxed text-text">
                                                {group.schools.map((school) => school.name).join(', ')}
                                            </p>
                                        </label>
                                    );
                                })}
                            </div>
                        )}
                    </section>
                </div>
            </Modal>
            <Modal
                open={scheduleImageOpen}
                onClose={() => {
                    setScheduleImageOpen(false);
                    setScheduleImageGenerating(false);
                }}
                size="xl"
                title="Gambar FJP"
                description={scheduleImageInput
                    ? formatGroupDate(scheduleImageInput.event_date)
                    : undefined}
                footer={
                    <div className="flex w-full flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={!scheduleImageDataUrl}
                            onClick={downloadScheduleImage}
                        >
                            <Download className="h-4 w-4" />
                            Unduh PNG
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            disabled={!scheduleImageDataUrl}
                            onClick={shareScheduleImage}
                        >
                            <ImageIcon className="h-4 w-4" />
                            Bagikan gambar
                        </Button>
                    </div>
                }
            >
                {scheduleImageGenerating && (
                    <div role="status" className="flex items-center justify-center gap-2 py-12 text-sm text-muted">
                        <LoaderCircle className="h-5 w-5 animate-spin" />
                        Membuat gambar jadwal…
                    </div>
                )}
                {scheduleImageError && (
                    <p role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                        {scheduleImageError}
                    </p>
                )}
                {scheduleImageDataUrl && (
                    <img
                        src={scheduleImageDataUrl}
                        alt={`Gambar FJP ${scheduleImageInput ? formatGroupDate(scheduleImageInput.event_date) : ''}`}
                        className="max-h-[65vh] w-full rounded-lg border border-border bg-white object-contain"
                    />
                )}
            </Modal>

            <Modal
                open={selectedDetail !== null}
                onClose={() => setSelectedDetail(null)}
                size="lg"
                title={selectedDetail?.school_name || selectedDetail?.event_name || 'Detail sekolah'}
                description={selectedDetail?.brand ?? 'Tanpa brand'}
                footer={
                    selectedDetail && (
                        <>
                            <Button variant="outline" onClick={() => setSelectedDetail(null)}>
                                Tutup
                            </Button>
                            <Button asChild>
                                <Link href={route('fjp.show', selectedDetail.id)}>
                                    Buka detail lengkap
                                </Link>
                            </Button>
                        </>
                    )
                }
            >
                {selectedDetail && (
                    <div className="space-y-5">
                        <div className="flex flex-wrap items-center gap-2">
                            <FjpStatusBadge
                                status={selectedDetail.status}
                                label={selectedDetail.status_label}
                            />
                            <span className="rounded-full bg-info/10 px-2.5 py-1 text-xs font-medium text-info">
                                {selectedDetail.assignment_status_label}
                            </span>
                        </div>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            {[
                                { label: 'Nama sekolah', value: selectedDetail.school_name || selectedDetail.event_name },
                                { label: 'Nama kegiatan', value: selectedDetail.event_name },
                                { label: 'NPSN', value: selectedDetail.npsn || '—' },
                                { label: 'Brand', value: selectedDetail.brand || '—' },
                                { label: 'Tanggal pelaksanaan', value: selectedDetail.event_date || 'Belum dijadwalkan' },
                                { label: 'Jam mulai', value: selectedDetail.start_time || '—' },
                                { label: 'Jumlah siswa sekolah', value: formatNumber(selectedDetail.jumlah_siswa_sekolah) },
                                { label: 'Jumlah siswa Dapodik', value: formatNumber(selectedDetail.jumlah_siswa_dapodik) },
                                { label: 'Selisih siswa', value: formatNumber(selectedDetail.difference) },
                                { label: 'Sumber data Dapodik', value: selectedDetail.dapodik_source || '—' },
                                { label: 'Target sampling', value: formatNumber(selectedDetail.sampling_target) },
                                { label: 'Kebutuhan jug', value: selectedDetail.jug_required ? 'Ya' : 'Tidak' },
                                { label: 'Kota', value: selectedDetail.city || '—' },
                                { label: 'Kecamatan', value: selectedDetail.kecamatan || '—' },
                                { label: 'Kelurahan', value: selectedDetail.kelurahan || '—' },
                                { label: 'Provinsi', value: selectedDetail.province || '—' },
                                { label: 'Lampiran', value: `${selectedDetail.attachments_count} berkas` },
                            ].map(({ label, value }) => (
                                <div key={label} className="rounded-lg border border-border bg-page/50 p-3">
                                    <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted">
                                        {label}
                                    </dt>
                                    <dd className="mt-1 break-words text-sm font-medium text-text">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                            <div className="rounded-lg border border-border bg-page/50 p-3 sm:col-span-2">
                                <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted">
                                    Alamat
                                </dt>
                                <dd className="mt-1 text-sm text-text">
                                    {selectedDetail.address || '—'}
                                </dd>
                            </div>
                            <div className="rounded-lg border border-border bg-page/50 p-3 sm:col-span-2">
                                <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted">
                                    Catatan
                                </dt>
                                <dd className="mt-1 whitespace-pre-wrap text-sm text-text">
                                    {selectedDetail.notes || '—'}
                                </dd>
                            </div>
                            <div className="rounded-lg border border-border bg-page/50 p-3 sm:col-span-2">
                                <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted">
                                    Lokasi peta
                                </dt>
                                <dd className="mt-1 break-all text-sm text-text">
                                    {selectedDetail.maps_url ? (
                                        <a
                                            href={selectedDetail.maps_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            {selectedDetail.maps_url}
                                        </a>
                                    ) : '—'}
                                </dd>
                            </div>
                        </dl>
                        {selectedDetail.event_schedule && (
                            <section className="space-y-3 rounded-xl border border-border p-4">
                                <h2 className="text-sm font-semibold text-text">Jadwal Event</h2>
                                <dl className="grid gap-3 sm:grid-cols-2">
                                    {[
                                        { label: 'Tanggal', value: selectedDetail.event_schedule.event_date || '—' },
                                        { label: 'Jam mulai', value: selectedDetail.event_schedule.start_time || '—' },
                                        { label: 'Sekolah', value: selectedDetail.event_schedule.school_name },
                                        { label: 'Brand', value: selectedDetail.event_schedule.brand || '—' },
                                        { label: 'Lokasi', value: selectedDetail.event_schedule.location || '—' },
                                        { label: 'Target siswa', value: selectedDetail.event_schedule.sampling_target?.toLocaleString('id-ID') || '—' },
                                        { label: 'Tim', value: selectedDetail.event_schedule.team || 'Belum ditetapkan' },
                                        { label: 'PIC penanggung jawab', value: selectedDetail.event_schedule.pic || '—' },
                                    ].map(({ label, value }) => (
                                        <div key={label} className="rounded-lg bg-page/50 p-3">
                                            <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted">
                                                {label}
                                            </dt>
                                            <dd className="mt-1 break-words text-sm font-medium text-text">{value}</dd>
                                        </div>
                                    ))}
                                    {canAssignTeamMembers && (
                                        <div className="rounded-lg bg-page/50 p-3 sm:col-span-2">
                                            <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted">
                                                Ketua dan anggota tim
                                            </dt>
                                            <dd className="mt-1 text-sm text-text">
                                                {selectedDetail.event_schedule.members.length > 0
                                                    ? selectedDetail.event_schedule.members
                                                        .map((member) => (
                                                            `${member.position}: ${member.name}${member.phone ? ` · ${member.phone}` : ''}`
                                                        ))
                                                        .join(' · ')
                                                    : 'Belum ada anggota yang ditugaskan'}
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                            </section>
                        )}
                    </div>
                )}
            </Modal>

            <ModalForm
                open={formOpen}
                onClose={closeForm}
                size="xl"
                title={editingId ? 'Ubah FJP' : 'FJP Baru'}
                description={
                    editingId
                        ? 'Perbarui data sekolah dan jumlah siswa.'
                        : 'Cari sekolah lalu lengkapi data FJP.'
                }
                onSubmit={submitForm}
                submitDisabled={form.processing}
            >
                <FjpFormFields form={form} options={formOptions} />
            </ModalForm>
        </AppLayout>
    );
}
