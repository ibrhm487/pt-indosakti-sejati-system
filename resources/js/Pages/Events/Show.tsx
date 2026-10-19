import {
    ConfirmButton,
    EmptyState,
    ModalForm,
    PageHeader,
    Tabs,
} from '@/Components/AppBits';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Field,
    Input,
    Select,
    StatusBadge,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime, formatNumber } from '@/lib/utils';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    CheckCircle2,
    ClipboardList,
    FileText,
    FolderOpen,
    History,
    ImageIcon,
    Lock,
    Package,
    Pencil,
    Plus,
    Soup,
    Trash2,
    Users,
    XCircle,
} from 'lucide-react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';

type TabItem = { key: string; label: string; icon: ReactNode; count?: number };

const MODULE_LABELS: Record<string, string> = {
    permission: 'Perizinan',
    team: 'Tim',
    warehouse: 'Gudang',
    kitchen: 'Kitchen',
};

const MODULE_VARIANTS: Record<
    string,
    'success' | 'warning' | 'danger' | 'outline'
> = {
    READY: 'success',
    PENDING: 'warning',
    BLOCKED: 'danger',
    NOT_REQUIRED: 'outline',
};

type Option = { value: string; label: string };

export default function EventsShow({
    event,
    readiness,
    summary,
    fjp,
    permit,
    team,
    attendances,
    stockRequests,
    kitchen,
    reports,
    quickReports,
    documents,
    auditTrail,
    transitions,
    assignmentPool,
    can,
}: {
    event: {
        id: number;
        event_code: string;
        event_name: string;
        brand: string | null;
        event_type: string | null;
        venue: string | null;
        event_date: string | null;
        start_time: string | null;
        end_time: string | null;
        sampling_target: number | null;
        notes: string | null;
        jug_required: boolean;
        status: string;
        status_label: string;
        pic: string | null;
    };
    readiness: { modules: Record<string, string>; percentage: number };
    summary: { target: number | null; realisasi: number | null; achievement: number | null; has_data: boolean };
    fjp: any | null;
    permit: any | null;
    team: {
        id: number;
        name: string;
        position: string | null;
        user_id: number | null;
        attendance: {
            check_in_at: string | null;
            check_out_at: string | null;
            status: string | null;
            is_complete: boolean;
        } | null;
    }[];
    attendances: {
        id: number;
        user: string | null;
        planned_start: string | null;
        planned_end: string | null;
        check_in_at: string | null;
        check_out_at: string | null;
        status: string | null;
        is_complete: boolean;
    }[];
    stockRequests: any[];
    kitchen: any | null;
    reports: any[];
    quickReports: any[];
    documents: {
        id: number;
        category: string | null;
        original_name: string;
        mime: string | null;
        size: number | null;
        uploaded_by: string | null;
        created_at: string | null;
        url: string;
    }[];
    auditTrail: {
        id: number;
        action: string;
        module: string | null;
        user: string | null;
        role: string | null;
        created_at: string | null;
    }[];
    transitions: Option[];
    assignmentPool: {
        users: { id: number; name: string }[];
        personnel: { id: number; name: string; role: string }[];
    };
    can: {
        assign_team: boolean;
        manage: boolean;
        close: boolean;
        verify: boolean;
        revision: boolean;
        upload_document: boolean;
        create_report: boolean;
        view_fjp: boolean;
        view_mrv: boolean;
        view_kitchen: boolean;
        view_quick: boolean;
        view_audit: boolean;
    };
}) {
    const { errors } = usePage().props as any;
    const [tab, setTab] = useState('ringkasan');
    const [statusOpen, setStatusOpen] = useState(false);
    const [statusValue, setStatusValue] = useState(event.status);
    const [assignOpen, setAssignOpen] = useState(false);

    const assignForm = useForm<{
        user_id: string;
        personnel_id: string;
        position: string;
    }>({ user_id: '', personnel_id: '', position: '' });

    const tabs = useMemo(() => {
        const items: TabItem[] = [
            { key: 'ringkasan', label: 'Ringkasan', icon: <ClipboardList className="h-4 w-4" /> },
        ];

        if (can.view_fjp) {
            items.push({ key: 'fjp', label: 'FJP & Perizinan', icon: <FileText className="h-4 w-4" /> });
        }

        items.push({
            key: 'tim',
            label: 'Tim & Absensi',
            icon: <Users className="h-4 w-4" />,
            count: team.length,
        });

        if (can.view_mrv) {
            items.push({
                key: 'mrv',
                label: 'MRV & Barang',
                icon: <Package className="h-4 w-4" />,
                count: stockRequests.length,
            });
        }

        if (can.view_kitchen) {
            items.push({ key: 'kitchen', label: 'Kitchen', icon: <Soup className="h-4 w-4" /> });
        }

        items.push({
            key: 'laporan',
            label: 'Laporan PIC',
            icon: <FileText className="h-4 w-4" />,
            count: reports.length,
        });

        items.push({
            key: 'dokumen',
            label: 'Foto & Dokumen',
            icon: <ImageIcon className="h-4 w-4" />,
            count: documents.length,
        });

        if (can.view_quick) {
            items.push({
                key: 'quick',
                label: 'Quick Report',
                icon: <Activity className="h-4 w-4" />,
                count: quickReports.length,
            });
        }

        if (can.view_audit) {
            items.push({ key: 'riwayat', label: 'Riwayat Aktivitas', icon: <History className="h-4 w-4" /> });
        }

        return items;
    }, [
        can.view_fjp,
        can.view_mrv,
        can.view_kitchen,
        can.view_quick,
        can.view_audit,
        team.length,
        stockRequests.length,
        reports.length,
        documents.length,
        quickReports.length,
    ]);

    const submitStatus = (e: FormEvent) => {
        e.preventDefault();
        router.patch(
            route('events.status', event.id),
            { status: statusValue },
            { preserveScroll: true, onSuccess: () => setStatusOpen(false) },
        );
    };

    const openAssign = () => {
        assignForm.clearErrors();
        assignForm.reset();
        setAssignOpen(true);
    };

    const submitAssign = (e: FormEvent) => {
        e.preventDefault();
        assignForm.post(route('events.team.assign', event.id), {
            preserveScroll: true,
            onSuccess: () => {
                setAssignOpen(false);
                assignForm.reset();
            },
        });
    };

    const removeMember = (memberId: number) => {
        router.delete(route('events.team.remove', [event.id, memberId]), {
            preserveScroll: true,
        });
    };

    const closeEvent = () => {
        router.post(route('events.close', event.id), {}, { preserveScroll: true });
    };

    const isTerminal = ['completed', 'cancelled'].includes(event.status);

    return (
        <AppLayout title="Detail Event">
            <Head title={event.event_name} />
            <PageHeader
                title={event.event_name}
                subtitle={`${event.event_code} · ${event.brand ?? '-'}`}
            >
                {can.manage && !isTerminal && (
                    <>
                        <Link href={route('events.edit', event.id)}>
                            <Button variant="outline">
                                <Pencil className="h-4 w-4" /> Ubah
                            </Button>
                        </Link>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setStatusValue(event.status);
                                setStatusOpen(true);
                            }}
                        >
                            <Activity className="h-4 w-4" /> Ubah Status
                        </Button>
                    </>
                )}
                {can.close && !isTerminal && (
                    <ConfirmButton
                        variant="success"
                        title="Tutup Event"
                        description="Event akan ditandai selesai. Pastikan seluruh laporan sudah terverifikasi dan barang telah dikembalikan."
                        confirmLabel="Tutup Event"
                        onConfirm={closeEvent}
                    >
                        <Lock className="h-4 w-4" /> Tutup Event
                    </ConfirmButton>
                )}
            </PageHeader>

            {errors?.close && (
                <div className="mb-4 rounded-md border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                    {errors.close}
                </div>
            )}

            <div className="mb-4">
                <Tabs items={tabs} active={tab} onChange={setTab} />
            </div>

            {tab === 'ringkasan' && (
                <RingkasanTab event={event} readiness={readiness} summary={summary} />
            )}

            {tab === 'fjp' && can.view_fjp && <FjpTab fjp={fjp} permit={permit} />}

            {tab === 'tim' && (
                <TimTab
                    team={team}
                    attendances={attendances}
                    can={can}
                    onAssign={openAssign}
                    onRemove={removeMember}
                />
            )}

            {tab === 'mrv' && can.view_mrv && <MrvTab stockRequests={stockRequests} />}

            {tab === 'kitchen' && can.view_kitchen && <KitchenTab kitchen={kitchen} />}

            {tab === 'laporan' && <LaporanTab reports={reports} />}

            {tab === 'dokumen' && (
                <DokumenTab
                    eventId={event.id}
                    documents={documents}
                    canUpload={can.upload_document}
                />
            )}

            {tab === 'quick' && can.view_quick && <QuickTab quickReports={quickReports} />}

            {tab === 'riwayat' && can.view_audit && <RiwayatTab auditTrail={auditTrail} />}

            <ModalForm
                open={statusOpen}
                onClose={() => setStatusOpen(false)}
                title="Ubah Status Event"
                description="Hanya transisi status yang sah yang dapat dipilih."
                size="sm"
                onSubmit={submitStatus}
                submitDisabled={statusValue === event.status}
            >
                <Field label="Status" error={errors?.status}>
                    <Select
                        value={statusValue}
                        onChange={(e) => setStatusValue(e.target.value)}
                    >
                        <option value={event.status}>{event.status_label}</option>
                        {transitions.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </Select>
                </Field>
            </ModalForm>

            <ModalForm
                open={assignOpen}
                onClose={() => setAssignOpen(false)}
                title="Tugaskan Anggota Tim"
                description="Pilih pengguna atau personel untuk ditugaskan."
                onSubmit={submitAssign}
                submitLabel="Tugaskan"
                submitDisabled={assignForm.processing}
            >
                <Field label="Pengguna" error={assignForm.errors.user_id}>
                    <Select
                        value={assignForm.data.user_id}
                        onChange={(e) => assignForm.setData('user_id', e.target.value)}
                    >
                        <option value="">- Pilih pengguna -</option>
                        {assignmentPool.users.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Personel" error={assignForm.errors.personnel_id}>
                    <Select
                        value={assignForm.data.personnel_id}
                        onChange={(e) => assignForm.setData('personnel_id', e.target.value)}
                    >
                        <option value="">- Pilih personel -</option>
                        {assignmentPool.personnel.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name} ({p.role})
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Posisi" error={assignForm.errors.position}>
                    <Input
                        value={assignForm.data.position}
                        onChange={(e) => assignForm.setData('position', e.target.value)}
                        placeholder="Mis. Sampler"
                    />
                </Field>
            </ModalForm>
        </AppLayout>
    );
}

function RingkasanTab({
    event,
    readiness,
    summary,
}: {
    event: any;
    readiness: { modules: Record<string, string>; percentage: number };
    summary: { target: number | null; realisasi: number | null; achievement: number | null; has_data: boolean };
}) {
    return (
        <div className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Detail Event</CardTitle>
                    <StatusBadge status={event.status} label={event.status_label} />
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <Detail label="Jenis Event" value={event.event_type} />
                        <Detail label="Lokasi" value={event.venue} />
                        <Detail label="Tanggal" value={formatDate(event.event_date)} />
                        <Detail
                            label="Waktu"
                            value={`${event.start_time ?? '-'} - ${event.end_time ?? '-'}`}
                        />
                        <Detail label="Target Sampling" value={formatNumber(event.sampling_target)} />
                        <Detail label="Butuh JUG" value={event.jug_required ? 'Ya' : 'Tidak'} />
                        <Detail label="PIC" value={event.pic} />
                        <Detail label="Catatan" value={event.notes} className="col-span-2" />
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Kesiapan Event</CardTitle>
                    <span className="text-sm font-semibold text-text">
                        {readiness.percentage}%
                    </span>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <div className="h-2 w-full overflow-hidden rounded-full bg-page">
                        <div
                            className="h-full rounded-full bg-success/100 transition-all"
                            style={{ width: `${readiness.percentage}%` }}
                        />
                    </div>
                    {Object.entries(readiness.modules).map(([key, value]) => (
                        <div key={key} className="flex items-center justify-between">
                            <span className="text-muted">{MODULE_LABELS[key] ?? key}</span>
                            <Badge variant={MODULE_VARIANTS[value] ?? 'default'}>
                                {value.toLowerCase()}
                            </Badge>
                        </div>
                    ))}
                </CardContent>
            </Card>

            <Card className="lg:col-span-3">
                <CardHeader>
                    <CardTitle>Target &amp; Realisasi Sampling</CardTitle>
                </CardHeader>
                <CardContent>
                    {!summary.has_data ? (
                        <EmptyState
                            title="Belum ada data"
                            description="Realisasi akan muncul setelah laporan event terverifikasi."
                        />
                    ) : (
                        <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                            <Detail label="Target" value={formatNumber(summary.target)} />
                            <Detail label="Realisasi" value={formatNumber(summary.realisasi)} />
                            <Detail
                                label="Capaian"
                                value={summary.achievement != null ? `${summary.achievement}%` : null}
                            />
                        </dl>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function FjpTab({ fjp, permit }: { fjp: any | null; permit: any | null }) {
    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Formulir Jalur Pendek (FJP)</CardTitle>
                </CardHeader>
                <CardContent>
                    {!fjp ? (
                        <EmptyState title="Belum ada FJP" description="Event ini tidak terhubung ke FJP." />
                    ) : (
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <Detail label="Nomor FJP" value={fjp.fjp_number} />
                            <Detail label="Sekolah" value={fjp.school_name} />
                            <Detail label="NPSN" value={fjp.npsn} />
                            <Detail label="Jumlah Siswa (Sekolah)" value={formatNumber(fjp.jumlah_siswa_sekolah)} />
                            <Detail label="Jumlah Siswa (Dapodik)" value={formatNumber(fjp.jumlah_siswa_dapodik)} />
                            <Detail label="Selisih" value={formatNumber(fjp.difference)} />
                            <Detail label="Sumber Dapodik" value={fjp.dapodik_source} />
                            {fjp.dapodik_source && String(fjp.dapodik_source).toLowerCase().includes('simulasi') && (
                                <div className="col-span-2">
                                    <span className="inline-flex items-center rounded-full bg-warning/10 px-2.5 py-1 text-xs font-medium text-warning ring-1 ring-inset ring-warning/20">
                                        Data Simulasi/Demo (bukan data resmi Dapodik)
                                    </span>
                                </div>
                            )}
                            <Detail label="Sinkron Dapodik" value={formatDateTime(fjp.dapodik_synced_at)} />
                            <Detail
                                label="Alamat"
                                value={fjp.address}
                                className="col-span-2"
                            />
                            {fjp.maps_url && (
                                <Detail
                                    label="Maps"
                                    value={
                                        <a
                                            href={fjp.maps_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            Buka lokasi
                                        </a>
                                    }
                                    className="col-span-2"
                                />
                            )}
                            <Detail
                                label="Status Perizinan"
                                value={
                                    <StatusBadge
                                        status={fjp.permit_status}
                                        label={fjp.permit_status_label}
                                    />
                                }
                                className="col-span-2"
                            />
                            {fjp.rejection_reason && (
                                <Detail label="Alasan Penolakan" value={fjp.rejection_reason} className="col-span-2" />
                            )}
                            {fjp.cancellation_reason && (
                                <Detail label="Alasan Pembatalan" value={fjp.cancellation_reason} className="col-span-2" />
                            )}
                        </dl>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Perizinan</CardTitle>
                    {permit && <StatusBadge status={permit.status} label={permit.status_label} />}
                </CardHeader>
                <CardContent>
                    {!permit ? (
                        <EmptyState title="Belum ada perizinan" description="Perizinan belum diajukan untuk event ini." />
                    ) : (
                        <div className="space-y-4 text-sm">
                            <dl className="grid grid-cols-2 gap-4">
                                <Detail label="Nomor Izin" value={permit.permit_number} />
                                <Detail label="Tenggat" value={formatDate(permit.deadline)} />
                                {permit.reason && (
                                    <Detail label="Keterangan" value={permit.reason} className="col-span-2" />
                                )}
                            </dl>
                            <div>
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                                    Dokumen
                                </p>
                                {permit.documents.length === 0 ? (
                                    <p className="text-muted">Belum ada dokumen.</p>
                                ) : (
                                    <ul className="divide-y divide-border/70 rounded-lg border border-border">
                                        {permit.documents.map((d: any) => (
                                            <li
                                                key={d.id}
                                                className="flex items-center justify-between px-3 py-2"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-text">
                                                        {d.original_name}
                                                    </p>
                                                    <p className="text-xs text-muted">
                                                        {d.category} · v{d.version}
                                                    </p>
                                                </div>
                                                <a
                                                    href={d.url}
                                                    className="text-xs font-medium text-primary hover:underline"
                                                >
                                                    Unduh
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            {permit.status === 'submitted' && (
                                <Link
                                    href={route('permits.show', permit.id)}
                                    className="inline-block text-xs font-medium text-primary hover:underline"
                                >
                                    Buka halaman perizinan
                                </Link>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function TimTab({
    team,
    attendances,
    can,
    onAssign,
    onRemove,
}: {
    team: any[];
    attendances: any[];
    can: { assign_team: boolean };
    onAssign: () => void;
    onRemove: (id: number) => void;
}) {
    return (
        <div className="space-y-6">
            <Card>
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Anggota Tim</CardTitle>
                    {can.assign_team && (
                        <Button variant="primary" size="sm" onClick={onAssign}>
                            <Plus className="h-4 w-4" /> Tugaskan
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    {team.length === 0 ? (
                        <EmptyState
                            title="Belum ada anggota tim"
                            description="Tugaskan anggota tim untuk event ini."
                        />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nama</TH>
                                    <TH>Posisi</TH>
                                    <TH>Kehadiran</TH>
                                    <TH className="w-16 text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {team.map((m) => (
                                    <TR key={m.id}>
                                        <TD>{m.name}</TD>
                                        <TD>
                                            <Badge variant="outline">{m.position ?? '-'}</Badge>
                                        </TD>
                                        <TD>
                                            {m.attendance ? (
                                                <div className="flex items-center gap-2 text-xs">
                                                    {m.attendance.is_complete ? (
                                                        <CheckCircle2 className="h-4 w-4 text-success" />
                                                    ) : (
                                                        <XCircle className="h-4 w-4 text-warning" />
                                                    )}
                                                    <span className="text-muted">
                                                        {m.attendance.check_in_at
                                                            ? `Masuk ${formatDateTime(m.attendance.check_in_at)}`
                                                            : 'Belum absen'}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted">-</span>
                                            )}
                                        </TD>
                                        <TD className="text-right">
                                            {can.assign_team && (
                                                <div className="flex justify-end">
                                                    <ConfirmButton
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Hapus anggota"
                                                        description={`Hapus ${m.name} dari tim event ini?`}
                                                        confirmLabel="Hapus"
                                                        onConfirm={() => onRemove(m.id)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-danger" />
                                                    </ConfirmButton>
                                                </div>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Absensi</CardTitle>
                </CardHeader>
                <CardContent>
                    {attendances.length === 0 ? (
                        <EmptyState title="Belum ada absensi" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Petugas</TH>
                                    <TH>Rencana</TH>
                                    <TH>Masuk</TH>
                                    <TH>Keluar</TH>
                                    <TH>Status</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {attendances.map((a) => (
                                    <TR key={a.id}>
                                        <TD>{a.user ?? '-'}</TD>
                                        <TD className="text-xs text-muted">
                                            {formatDateTime(a.planned_start)} –{' '}
                                            {formatDateTime(a.planned_end)}
                                        </TD>
                                        <TD>{formatDateTime(a.check_in_at)}</TD>
                                        <TD>{formatDateTime(a.check_out_at)}</TD>
                                        <TD>
                                            {a.status ? (
                                                <StatusBadge status={a.status} />
                                            ) : (
                                                <span className="text-muted">-</span>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function MrvTab({ stockRequests }: { stockRequests: any[] }) {
    if (stockRequests.length === 0) {
        return (
            <Card>
                <CardContent>
                    <EmptyState
                        title="Belum ada permintaan stok"
                        description="Warehouse belum membuat MRV untuk event ini."
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {stockRequests.map((r) => (
                <Card key={r.id}>
                    <CardHeader className="flex items-center justify-between">
                        <div>
                            <CardTitle className="font-mono">{r.mrv_number}</CardTitle>
                            <p className="mt-1 text-xs text-muted">
                                Dibuat {formatDateTime(r.created_at)}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <StatusBadge status={r.status} label={r.status_label} />
                            <Link
                                href={route('warehouse.mrv.show', r.id)}
                                className="text-xs font-medium text-primary hover:underline"
                            >
                                Detail
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {(r.notes || r.rejection_reason) && (
                            <div className="space-y-1 text-sm">
                                {r.notes && (
                                    <p className="text-muted">Catatan: {r.notes}</p>
                                )}
                                {r.rejection_reason && (
                                    <p className="text-danger">
                                        Alasan penolakan: {r.rejection_reason}
                                    </p>
                                )}
                            </div>
                        )}
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Produk</TH>
                                    <TH>Diminta</TH>
                                    <TH>Dikeluarkan</TH>
                                    <TH>Dikembalikan</TH>
                                    <TH>Rusak</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {r.items.map((i: any) => (
                                    <TR key={i.id}>
                                        <TD>
                                            <div>{i.product ?? '-'}</div>
                                            <div className="text-xs text-muted">{i.sku}</div>
                                        </TD>
                                        <TD>
                                            {formatNumber(i.quantity_requested)} {i.unit}
                                        </TD>
                                        <TD>{formatNumber(i.quantity_issued)}</TD>
                                        <TD>{formatNumber(i.quantity_returned)}</TD>
                                        <TD>{formatNumber(i.quantity_damaged)}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        {r.returns.length > 0 && (
                            <div>
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                                    Pengembalian
                                </p>
                                <ul className="space-y-1 text-sm">
                                    {r.returns.map((rt: any) => (
                                        <li
                                            key={rt.id}
                                            className="flex items-center justify-between rounded-lg border border-border px-3 py-2"
                                        >
                                            <span className="font-mono text-xs text-muted">
                                                {rt.return_number}
                                            </span>
                                            <StatusBadge status={rt.status} />
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function KitchenTab({ kitchen }: { kitchen: any | null }) {
    if (!kitchen) {
        return (
            <Card>
                <CardContent>
                    <EmptyState
                        title="Belum ada permintaan kitchen"
                        description="Event ini tidak memerlukan persiapan kitchen atau permintaan belum dibuat."
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Permintaan Kitchen</CardTitle>
                    <StatusBadge status={kitchen.status} label={kitchen.status_label} />
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <Detail label="Jumlah JUG" value={formatNumber(kitchen.jug_quantity)} />
                        <Detail label="Penanggung Jawab" value={kitchen.assignee} />
                        <Detail label="Tanggal Persiapan" value={formatDate(kitchen.preparation_date)} />
                        <Detail label="Waktu Persiapan" value={kitchen.preparation_time} />
                        <Detail label="Catatan" value={kitchen.notes} className="col-span-2" />
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Laporan Persiapan</CardTitle>
                </CardHeader>
                <CardContent>
                    {kitchen.reports.length === 0 ? (
                        <EmptyState title="Belum ada laporan persiapan" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Jumlah Disiapkan</TH>
                                    <TH>Kondisi</TH>
                                    <TH>Catatan</TH>
                                    <TH>Oleh</TH>
                                    <TH>Waktu</TH>
                                    <TH>Foto</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {kitchen.reports.map((rep: any) => (
                                    <TR key={rep.id}>
                                        <TD>{formatNumber(rep.prepared_quantity)}</TD>
                                        <TD>{rep.condition ?? '-'}</TD>
                                        <TD className="max-w-xs truncate text-muted">
                                            {rep.notes ?? '-'}
                                        </TD>
                                        <TD>{rep.prepared_by ?? '-'}</TD>
                                        <TD className="text-xs text-muted">
                                            {formatDateTime(rep.prepared_at)}
                                        </TD>
                                        <TD>
                                            {rep.photo_url ? (
                                                <a
                                                    href={rep.photo_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-xs font-medium text-primary hover:underline"
                                                >
                                                    Lihat
                                                </a>
                                            ) : (
                                                <span className="text-muted">-</span>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function LaporanTab({ reports }: { reports: any[] }) {
    if (reports.length === 0) {
        return (
            <Card>
                <CardContent>
                    <EmptyState title="Belum ada laporan" description="PIC belum mengirim laporan untuk event ini." />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardContent className="p-0">
                <Table>
                    <THead>
                        <TR>
                            <TH>Status</TH>
                            <TH>Target</TH>
                            <TH>Realisasi</TH>
                            <TH>Capaian</TH>
                            <TH>Dikirim</TH>
                            <TH>Diverifikasi</TH>
                            <TH className="text-right">Aksi</TH>
                        </TR>
                    </THead>
                    <TBody>
                        {reports.map((r) => (
                            <TR key={r.id}>
                                <TD>
                                    <StatusBadge status={r.status} label={r.status_label} />
                                </TD>
                                <TD>{formatNumber(r.sampling_target)}</TD>
                                <TD>{formatNumber(r.actual_sampling)}</TD>
                                <TD>{r.achievement}%</TD>
                                <TD className="text-xs text-muted">
                                    {r.submitted_by ?? '-'}
                                    <br />
                                    {formatDateTime(r.submitted_at)}
                                </TD>
                                <TD className="text-xs text-muted">
                                    {r.verified_by ?? '-'}
                                    <br />
                                    {formatDateTime(r.verified_at)}
                                </TD>
                                <TD className="text-right">
                                    <Link
                                        href={route('reports.show', r.id)}
                                        className="text-xs font-medium text-primary hover:underline"
                                    >
                                        Detail
                                    </Link>
                                </TD>
                            </TR>
                        ))}
                    </TBody>
                </Table>
            </CardContent>
        </Card>
    );
}

function DokumenTab({
    eventId,
    documents,
    canUpload,
}: {
    eventId: number;
    documents: any[];
    canUpload: boolean;
}) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const form = useForm<{
        documentable_type: string;
        documentable_id: number;
        category: string;
        file: File | null;
    }>({
        documentable_type: 'event',
        documentable_id: eventId,
        category: 'photo',
        file: null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('documents.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset('file');
                setUploadOpen(false);
            },
        });
    };

    return (
        <Card>
            <CardHeader className="flex items-center justify-between">
                <CardTitle>Foto & Dokumen Team</CardTitle>
                {canUpload && (
                    <Button variant="primary" size="sm" onClick={() => setUploadOpen(true)}>
                        <Plus className="h-4 w-4" /> Unggah
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {documents.length === 0 ? (
                    <EmptyState
                        title="Belum ada dokumen"
                        description="Unggah foto hasil sampling atau dokumen pendukung."
                    />
                ) : (
                    <ul className="divide-y divide-border/70">
                        {documents.map((d) => (
                            <li key={d.id} className="flex items-center justify-between py-3">
                                <div className="flex min-w-0 items-center gap-3">
                                    <FolderOpen className="h-5 w-5 shrink-0 text-muted" />
                                    <div className="min-w-0">
                                        <p className="truncate text-sm text-text">
                                            {d.original_name}
                                        </p>
                                        <p className="text-xs text-muted">
                                            {d.category} · {formatNumber(Math.round((d.size ?? 0) / 1024))} KB ·{' '}
                                            {d.uploaded_by ?? '-'} · {formatDateTime(d.created_at)}
                                        </p>
                                    </div>
                                </div>
                                <a
                                    href={d.url}
                                    className="shrink-0 text-xs font-medium text-primary hover:underline"
                                >
                                    Unduh
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>

            <ModalForm
                open={uploadOpen}
                onClose={() => setUploadOpen(false)}
                title="Unggah Dokumen"
                description="PDF, gambar, atau dokumen kantor (maks. 10 MB)."
                size="sm"
                onSubmit={submit}
                submitLabel="Unggah"
                submitDisabled={form.processing || !form.data.file}
            >
                <Field label="Kategori">
                    <Select
                        value={form.data.category}
                        onChange={(e) => form.setData('category', e.target.value)}
                    >
                        <option value="photo">Foto</option>
                        <option value="document">Dokumen</option>
                        <option value="supporting">Pendukung</option>
                        <option value="evidence">Bukti</option>
                        <option value="other">Lainnya</option>
                    </Select>
                </Field>
                <Field label="Berkas" error={form.errors.file}>
                    <Input
                        type="file"
                        onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                    />
                </Field>
            </ModalForm>
        </Card>
    );
}

function QuickTab({ quickReports }: { quickReports: any[] }) {
    if (quickReports.length === 0) {
        return (
            <Card>
                <CardContent>
                    <EmptyState title="Belum ada quick report" />
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {quickReports.map((q) => (
                <Card key={q.id}>
                    <CardHeader>
                        <CardTitle>{q.title}</CardTitle>
                        <p className="mt-1 text-xs text-muted">
                            {q.user ?? '-'} · {formatDateTime(q.created_at)}
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <p className="whitespace-pre-line text-text">{q.body}</p>
                        {q.photo_url && (
                            <a href={q.photo_url} target="_blank" rel="noreferrer">
                                <img
                                    src={q.photo_url}
                                    alt={q.title}
                                    className="max-h-56 w-full rounded-lg border border-border object-cover"
                                />
                            </a>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function RiwayatTab({ auditTrail }: { auditTrail: any[] }) {
    if (auditTrail.length === 0) {
        return (
            <Card>
                <CardContent>
                    <EmptyState title="Belum ada aktivitas" />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardContent>
                <ol className="relative space-y-5 border-l border-border pl-5">
                    {auditTrail.map((log) => (
                        <li key={log.id} className="relative">
                            <span className="absolute -left-[26px] top-1 flex h-2.5 w-2.5 rounded-full bg-primary" />
                            <p className="text-sm font-medium text-text">
                                {log.action}
                                {log.module && (
                                    <span className="ml-2 text-xs font-normal text-muted">
                                        {log.module}
                                    </span>
                                )}
                            </p>
                            <p className="text-xs text-muted">
                                {log.user ?? 'Sistem'}
                                {log.role ? ` (${log.role})` : ''} ·{' '}
                                {formatDateTime(log.created_at)}
                            </p>
                        </li>
                    ))}
                </ol>
            </CardContent>
        </Card>
    );
}

function Detail({
    label,
    value,
    className,
}: {
    label: string;
    value: any;
    className?: string;
}) {
    return (
        <div className={className}>
            <dt className="text-xs uppercase tracking-wide text-muted">{label}</dt>
            <dd className="mt-0.5 text-text">{value || '-'}</dd>
        </div>
    );
}
