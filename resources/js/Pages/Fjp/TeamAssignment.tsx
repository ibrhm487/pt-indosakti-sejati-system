import {
    PermitTeamAssignment,
    TeamGroupRoster,
    TeamPersonnelOption,
    FjpTeamMember,
} from '@/Components/FjpTeamAssignmentControls';
import { FilterCard, PageHeader, Pagination } from '@/Components/AppBits';
import { Button, Input, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

type AssignmentRow = {
    id: number;
    event_date: string | null;
    start_time: string | null;
    school_name: string;
    venue: string;
    location: string;
    brand: string | null;
    team_group_id: string | null;
    team_group_label: string | null;
    team_group_fjp_ids: number[];
    team_group_pending_fjp_ids: number[];
    team_members: FjpTeamMember[];
    team_has_event: boolean;
    team_editable: boolean;
    team_assignment_status: string;
    school_order: number | null;
    remark: string | null;
};

type PaginatedRows = {
    data: AssignmentRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};

type Props = {
    rows: PaginatedRows;
    filters: {
        search: string;
        brand_id: number | null;
        date_from: string;
        date_to: string;
        per_page: number;
    };
    totalCount: number;
    brands: { id: number; name: string }[];
    teamPersonnel: TeamPersonnelOption[];
    canAssignTeam: boolean;
    canAssignTeamMembers: boolean;
};

function formatDate(date: string | null): string {
    if (!date) {
        return 'Tanggal belum ditentukan';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

function RemarkEditor({
    fjpId,
    initialRemark,
    editable,
}: {
    fjpId: number;
    initialRemark: string | null;
    editable: boolean;
}) {
    const [remark, setRemark] = useState(initialRemark ?? '');
    const [feedback, setFeedback] = useState('');

    useEffect(() => {
        setRemark(initialRemark ?? '');
    }, [initialRemark]);

    const save = () => {
        if (!editable || remark === (initialRemark ?? '')) {
            return;
        }

        setFeedback('');
        router.patch(
            route('fjp.inline', fjpId),
            { notes: remark },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setFeedback('Tersimpan'),
                onError: () => setFeedback('Gagal menyimpan remark.'),
            },
        );
    };

    return (
        <div className="min-w-52 space-y-1">
            {editable ? (
                <textarea
                    value={remark}
                    maxLength={1000}
                    rows={2}
                    placeholder="Tulis remark PIC..."
                    aria-label={`Remark PIC untuk FJP ${fjpId}`}
                    onChange={(event) => setRemark(event.target.value)}
                    onBlur={save}
                    className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-xs text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
            ) : (
                <span className="text-xs text-muted">{remark || '—'}</span>
            )}
            {feedback && (
                <span className={feedback === 'Tersimpan' ? 'text-[11px] text-success' : 'text-[11px] text-danger'}>
                    {feedback}
                </span>
            )}
        </div>
    );
}

export default function TeamAssignment({
    rows,
    filters,
    totalCount,
    brands,
    teamPersonnel,
    canAssignTeam,
    canAssignTeamMembers,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [brandId, setBrandId] = useState(filters.brand_id ? String(filters.brand_id) : '');
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);

    useEffect(() => {
        setSearch(filters.search);
        setBrandId(filters.brand_id ? String(filters.brand_id) : '');
        setDateFrom(filters.date_from);
        setDateTo(filters.date_to);
    }, [filters]);

    const firstTeamGroupRowByKey = useMemo(() => {
        const firstRows = new Map<string, number>();

        rows.data.forEach((row, index) => {
            if (row.team_group_id) {
                const groupKey = `${row.event_date ?? ''}|${row.team_group_id}`;
                if (!firstRows.has(groupKey)) {
                    firstRows.set(groupKey, index);
                }
            }
        });

        return firstRows;
    }, [rows.data]);

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('fjp.team-assignment'), {
            search,
            brand_id: brandId || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            per_page: filters.per_page,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <AppLayout title="Pembagian Tim">
            <Head title="Pembagian Tim FJP" />
            <PageHeader
                title="Pembagian Tim FJP"
                subtitle="Permitter menetapkan kelompok tim; PIC membagikan anggota dan mengisi remark untuk setiap sekolah."
            >
                <Link
                    href={route('fjp.index')}
                    className="inline-flex h-9 items-center rounded-lg border border-border bg-surface px-3 text-sm font-medium text-text hover:bg-page"
                >
                    Kembali ke FJP
                </Link>
            </PageHeader>

            <FilterCard className="mb-4">
                <form onSubmit={applyFilters} className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari sekolah atau nomor FJP..."
                        aria-label="Cari sekolah atau nomor FJP"
                    />
                    <select
                        value={brandId}
                        onChange={(event) => setBrandId(event.target.value)}
                        aria-label="Filter brand"
                        className="h-10 rounded-lg border border-border bg-surface px-3 text-sm text-text"
                    >
                        <option value="">Semua brand</option>
                        {brands.map((brand) => (
                            <option key={brand.id} value={brand.id}>{brand.name}</option>
                        ))}
                    </select>
                    <Input
                        type="date"
                        value={dateFrom}
                        onChange={(event) => setDateFrom(event.target.value)}
                        aria-label="Tanggal mulai"
                    />
                    <Input
                        type="date"
                        value={dateTo}
                        onChange={(event) => setDateTo(event.target.value)}
                        aria-label="Tanggal sampai"
                    />
                    <div className="flex items-center gap-2">
                        <Button type="submit" variant="primary">Terapkan</Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.get(route('fjp.team-assignment'))}
                        >
                            Reset
                        </Button>
                    </div>
                </form>
            </FilterCard>

            <div className="mb-3 text-sm text-muted">
                {rows.from ?? 0}–{rows.to ?? 0} dari {totalCount} FJP Confirm
            </div>

            <div className="overflow-auto rounded-xl border border-border bg-surface">
                <table className="min-w-[1200px] w-full border-collapse text-sm [&_td]:border [&_td]:border-border [&_td]:px-3 [&_td]:py-3 [&_th]:border [&_th]:border-border [&_th]:bg-emerald-800 [&_th]:px-3 [&_th]:py-3 [&_th]:text-left [&_th]:font-semibold [&_th]:text-white">
                    <THead>
                        <TR>
                            <TH className="w-14">No</TH>
                            <TH className="min-w-40">Tanggal &amp; Jam</TH>
                            <TH className="min-w-64">Venue / Sekolah</TH>
                            <TH className="min-w-32">Brand</TH>
                            <TH className="min-w-44">Tim / Urutan</TH>
                            {canAssignTeamMembers && <TH className="min-w-[580px]">Nama Anggota Tim</TH>}
                            <TH className="min-w-56">Remark PIC</TH>
                            <TH className="min-w-48">Status Pembagian</TH>
                        </TR>
                    </THead>
                    {rows.data.length > 0 ? (
                        <TBody>
                            {rows.data.map((row, index) => {
                                const hasTeam = row.team_group_id !== null;
                                const teamGroupKey = hasTeam
                                    ? `${row.event_date ?? ''}|${row.team_group_id}`
                                    : null;
                                const isFirstTeamGroupRow = teamGroupKey !== null
                                    && firstTeamGroupRowByKey.get(teamGroupKey) === index;

                                return (
                                    <TR key={row.id} className="align-top even:bg-page/40 hover:bg-emerald-50/60">
                                        <TD className="text-xs tabular-nums text-muted">
                                            {String((rows.from ?? 1) + index).padStart(2, '0')}
                                        </TD>
                                        <TD className="whitespace-nowrap">
                                            <div className="font-medium text-text">{formatDate(row.event_date)}</div>
                                            <div className="mt-1 font-mono text-xs text-muted">
                                                {row.start_time ?? 'Jam belum ditentukan'}
                                            </div>
                                        </TD>
                                        <TD>
                                            <div className="font-semibold text-text">{row.venue}</div>
                                            {row.school_name !== row.venue && (
                                                <div className="mt-1 text-xs text-muted">{row.school_name}</div>
                                            )}
                                            {row.location && <div className="mt-1 text-xs text-muted">{row.location}</div>}
                                        </TD>
                                        <TD>{row.brand ?? '—'}</TD>
                                        <TD>
                                            <div className="space-y-2">
                                                <p className="font-semibold text-text">
                                                    {row.team_group_label ?? 'Belum ditentukan'}
                                                </p>
                                                {row.school_order !== null && (
                                                    <p className="text-xs text-muted">Urutan sekolah ke-{row.school_order}</p>
                                                )}
                                                {canAssignTeam && !row.team_has_event && (
                                                    <PermitTeamAssignment
                                                        fjpId={row.id}
                                                        teamGroupLabel={row.team_group_label}
                                                    />
                                                )}
                                            </div>
                                        </TD>
                                        {canAssignTeamMembers && (
                                            <TD>
                                                {hasTeam && isFirstTeamGroupRow ? (
                                                    <TeamGroupRoster
                                                        teamGroupId={String(row.team_group_id)}
                                                        fjpIds={row.team_has_event
                                                            ? row.team_group_fjp_ids
                                                            : row.team_group_pending_fjp_ids}
                                                        teamGroupLabel={row.team_group_label ?? 'Kelompok Tim'}
                                                        members={row.team_members}
                                                        personnel={teamPersonnel}
                                                        editable={row.team_editable}
                                                        hasExistingEvent={row.team_has_event}
                                                    />
                                                ) : hasTeam ? (
                                                    <span className="text-xs text-muted">
                                                        Anggota tim ditampilkan pada kelompok yang sama.
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted">Tim belum ditetapkan Permitter.</span>
                                                )}
                                            </TD>
                                        )}
                                        <TD>
                                            <RemarkEditor
                                                fjpId={row.id}
                                                initialRemark={row.remark}
                                                editable={canAssignTeamMembers}
                                            />
                                        </TD>
                                        <TD>
                                            <span className="inline-flex rounded-full bg-page px-2.5 py-1 text-xs font-medium text-muted">
                                                {row.team_assignment_status}
                                            </span>
                                        </TD>
                                    </TR>
                                );
                            })}
                        </TBody>
                    ) : (
                        <TBody>
                            <TR>
                                <TD colSpan={canAssignTeamMembers ? 8 : 7} className="py-12 text-center text-muted">
                                    Tidak ada FJP Confirm yang sesuai dengan filter.
                                </TD>
                            </TR>
                        </TBody>
                    )}
                </table>
            </div>
            <Pagination paginator={rows} />
        </AppLayout>
    );
}
