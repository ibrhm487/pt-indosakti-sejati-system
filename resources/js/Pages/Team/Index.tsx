import { EmptyState, PageHeader } from '@/Components/AppBits';
import { EventBrandGroup, EventGroupItem, EventGroupView, EventTypeGroup } from '@/Components/EventGroupView';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, StatCard, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatNumber } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { CalendarCheck, CalendarClock, CircleCheck, ListTodo } from 'lucide-react';

const formatTime = (value: string | null): string | null =>
    value ? value.slice(0, 5) : null;

export default function TeamIndex({
    upcomingGroups,
    pastGroups,
    teamMembers,
    activeRole,
    canQuickReport,
    summary,
}: {
    upcomingGroups: EventBrandGroup[];
    pastGroups: EventBrandGroup[];
    teamMembers: { id: number; name: string; email: string }[];
    activeRole: string;
    canQuickReport: boolean;
    summary: {
        total: number;
        today: number;
        upcoming: number;
        completed: number;
    };
}) {
    const isTeam = activeRole === 'team';
    const title = isTeam ? 'Tugas Tim Hari Ini' : 'Penugasan Tim';
    const subtitle = isTeam
        ? 'Event yang ditugaskan kepada Anda, diurutkan dari yang terdekat'
        : 'Daftar event dan anggota tim yang ditugaskan';
    const renderRows = (rows: EventGroupItem[], type: EventTypeGroup) => (
        <Table>
            <THead>
                <TR>
                    <TH>Event</TH>
                    <TH>{type.requires_school ? 'Sekolah' : 'Venue'}</TH>
                    <TH>Status</TH>
                    <TH>Anggota</TH>
                    <TH className="text-right">Aksi</TH>
                </TR>
            </THead>
            <TBody>
                {rows.map((event) => (
                    <TR key={event.id}>
                        <TD>
                            <p className="font-medium text-text">
                                {event.event_name}
                                {event.is_today && <Badge variant="info" className="ml-2 align-middle">Hari ini</Badge>}
                            </p>
                            {event.team_group_count > 1 && (
                                <p className="mt-1">
                                    <Badge variant="outline">
                                        Satu tim · {event.team_group_count} sekolah · target {formatNumber(event.team_group_sampling_target)} siswa
                                    </Badge>
                                </p>
                            )}
                            <p className="text-xs text-muted">{event.venue_city ?? '-'} · {event.venue ?? 'Lokasi belum diisi'}</p>
                            <p className="text-xs text-muted">
                                {formatTime(event.start_time) ?? '--:--'} - {formatTime(event.end_time) ?? '--:--'} · PIC: {event.pic ?? '-'}
                            </p>
                            {event.notes && <p className="mt-1 text-xs text-muted">Instruksi: {event.notes}</p>}
                            {event.my_schedule && (
                                <div className="mt-2 rounded-lg border border-border bg-page/60 px-3 py-2 text-xs">
                                    <p className="font-medium text-text">Jadwal saya{event.my_schedule.position ? ` · ${event.my_schedule.position}` : ''}</p>
                                    <p className="mt-0.5 text-muted">
                                        {[formatDate(event.my_schedule.schedule_date), formatTime(event.my_schedule.report_time) ? `Kumpul ${formatTime(event.my_schedule.report_time)}` : null, `${formatTime(event.my_schedule.start_time) ?? '--:--'} - ${formatTime(event.my_schedule.end_time) ?? '--:--'}`].filter(Boolean).join(' · ')}
                                    </p>
                                    {event.my_schedule.task && <p className="mt-0.5 text-muted">Tugas: {event.my_schedule.task}</p>}
                                    {event.my_schedule.schedule_notes && <p className="mt-0.5 text-muted">Catatan: {event.my_schedule.schedule_notes}</p>}
                                </div>
                            )}
                        </TD>
                        <TD>{event.venue ?? '-'}</TD>
                        <TD><StatusBadge status={event.status} label={event.status_label} /></TD>
                        <TD>
                            <div className="flex flex-wrap gap-1">
                                {event.members?.map((member: { name: string; position: string | null }, index: number) => (
                                    <Badge key={index} variant="outline">{member.name} · {member.position}</Badge>
                                ))}
                            </div>
                        </TD>
                        <TD className="text-right">
                            <div className="flex items-center justify-end gap-3">
                                {isTeam && canQuickReport && <Link href={route('quick-reports.index')} className="text-sm font-medium text-primary hover:text-primary">Status Laporan</Link>}
                                <Link href={route('events.show', event.id)} className="text-sm font-medium text-muted hover:text-text">Lihat Detail</Link>
                            </div>
                        </TD>
                    </TR>
                ))}
            </TBody>
        </Table>
    );

    return (
        <AppLayout title={title}>
            <Head title={title} />
            <PageHeader title={title} subtitle={subtitle} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    {upcomingGroups.length === 0 && pastGroups.length === 0 ? (
                        <EmptyState title={isTeam ? 'Belum ada tugas' : 'Belum ada penugasan tim'} description={isTeam ? 'Penugasan Anda akan muncul di sini setelah PIC menugaskan tim.' : 'Tugaskan tim pada halaman detail event.'} />
                    ) : (
                        <div className="space-y-6">
                            {upcomingGroups.length > 0 && <section><h2 className="mb-3 text-sm font-semibold text-text">Mendatang</h2><EventGroupView groups={upcomingGroups} renderTable={renderRows} /></section>}
                            {pastGroups.length > 0 && <section><h2 className="mb-3 text-sm font-semibold text-text">Selesai / Lewat</h2><EventGroupView groups={pastGroups} renderTable={renderRows} /></section>}
                        </div>
                    )}
                </div>

                {isTeam ? (
                    <div className="grid content-start gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        <StatCard
                            label="Total Tugas"
                            value={summary.total}
                            icon={<ListTodo className="h-5 w-5" />}
                            tone="info"
                        />
                        <StatCard
                            label="Tugas Hari Ini"
                            value={summary.today}
                            icon={<CalendarCheck className="h-5 w-5" />}
                            tone={summary.today > 0 ? 'warning' : 'default'}
                        />
                        <StatCard
                            label="Akan Datang"
                            value={summary.upcoming}
                            icon={<CalendarClock className="h-5 w-5" />}
                        />
                        <StatCard
                            label="Selesai"
                            value={summary.completed}
                            icon={<CircleCheck className="h-5 w-5" />}
                            tone="success"
                        />
                        <Card className="sm:col-span-2 lg:col-span-1">
                            <CardHeader>
                                <CardTitle>Laporan Foto & Absensi</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p className="text-sm text-muted">
                                    Quick Report menggabungkan laporan foto, video, dan absensi.
                                    Status tugas serta riwayat laporan tersedia dalam satu halaman.
                                </p>
                                <Link href={route('quick-reports.index')}>
                                    <Button variant="primary">Lihat Tugas & Riwayat</Button>
                                </Link>
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Kandidat Tim Lapangan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {teamMembers.length === 0 && (
                                <p className="text-sm text-muted">
                                    Belum ada pengguna dengan role tim.
                                </p>
                            )}
                            {teamMembers.map((member) => (
                                <div
                                    key={member.id}
                                    className="rounded-xl border border-border bg-page/50 px-3.5 py-2.5"
                                >
                                    <p className="text-sm font-medium text-text">
                                        {member.name}
                                    </p>
                                    <p className="text-xs text-muted">
                                        {member.email}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
