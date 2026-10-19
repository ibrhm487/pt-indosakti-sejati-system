import { CardTable, EmptyState, PageHeader } from '@/Components/AppBits';
import { Badge, Card, CardContent, CardHeader, CardTitle, StatCard, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { CalendarCheck, CalendarClock, CircleCheck, ListTodo } from 'lucide-react';

type TeamEventRow = {
    id: number;
    event_name: string;
    event_date: string | null;
    brand: string | null;
    pic: string | null;
    status: string;
    status_label: string;
    is_today: boolean;
    members: { name: string; position: string | null }[];
};

export default function TeamIndex({
    events,
    teamMembers,
    activeRole,
    canCreateReport,
    canQuickReport,
    summary,
}: {
    events: TeamEventRow[];
    teamMembers: { id: number; name: string; email: string }[];
    activeRole: string;
    canCreateReport: boolean;
    canQuickReport: boolean;
    summary: { total: number; today: number; upcoming: number; completed: number };
}) {
    const isTeam = activeRole === 'team';
    const title = isTeam ? 'Tugas Tim Hari Ini' : 'Penugasan Tim';
    const subtitle = isTeam
        ? 'Event yang ditugaskan kepada Anda, diurutkan dari yang terdekat'
        : 'Daftar event dan anggota tim yang ditugaskan';

    return (
        <AppLayout title={title}>
            <Head title={title} />
            <PageHeader title={title} subtitle={subtitle} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <CardTable>
                        {events.length === 0 ? (
                            <EmptyState
                                title={isTeam ? 'Belum ada tugas' : 'Belum ada penugasan tim'}
                                description={
                                    isTeam
                                        ? 'Penugasan Anda akan muncul di sini setelah PIC menugaskan tim.'
                                        : 'Tugaskan tim pada halaman detail event.'
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <THead>
                                        <TR>
                                            <TH>Event</TH>
                                            <TH>Tanggal</TH>
                                            <TH>Status</TH>
                                            <TH>Anggota</TH>
                                            <TH className="text-right">Aksi</TH>
                                        </TR>
                                    </THead>
                                    <TBody>
                                        {events.map((event) => (
                                            <TR key={event.id}>
                                                <TD>
                                                    <p className="font-medium text-text">
                                                        {event.event_name}
                                                        {event.is_today && (
                                                            <Badge variant="info" className="ml-2 align-middle">
                                                                Hari ini
                                                            </Badge>
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted">
                                                        {event.brand} · PIC: {event.pic ?? '-'}
                                                    </p>
                                                </TD>
                                                <TD>{formatDate(event.event_date)}</TD>
                                                <TD>
                                                    <StatusBadge
                                                        status={event.status}
                                                        label={event.status_label}
                                                    />
                                                </TD>
                                                <TD>
                                                    <div className="flex flex-wrap gap-1">
                                                        {event.members?.map((m, i) => (
                                                            <Badge key={i} variant="outline">
                                                                {m.name} · {m.position}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </TD>
                                                <TD className="text-right">
                                                    <div className="flex items-center justify-end gap-3">
                                                        {canQuickReport && (
                                                            <Link
                                                                href={route('quick-reports.create') + `?event=${event.id}`}
                                                                className="text-sm font-medium text-primary hover:text-primary"
                                                            >
                                                                Buat Laporan
                                                            </Link>
                                                        )}
                                                        {canCreateReport && !canQuickReport && (
                                                            <Link
                                                                href={route('reports.create')}
                                                                className="text-sm font-medium text-primary hover:text-primary"
                                                            >
                                                                Buat Laporan
                                                            </Link>
                                                        )}
                                                        <Link
                                                            href={route('events.show', event.id)}
                                                            className="text-sm font-medium text-muted hover:text-text"
                                                        >
                                                            Lihat Detail
                                                        </Link>
                                                    </div>
                                                </TD>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            </div>
                        )}
                    </CardTable>
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
