import { CardTable, EmptyState, PageHeader } from '@/Components/AppBits';
import { Badge, Card, CardContent, CardHeader, CardTitle, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';

export default function TeamIndex({
    events,
    teamMembers,
}: {
    events: any[];
    teamMembers: { id: number; name: string; email: string }[];
}) {
    return (
        <AppLayout title="Tim">
            <Head title="Tim" />
            <PageHeader
                title="Penugasan Tim"
                subtitle="Daftar event dan anggota tim yang ditugaskan"
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <CardTable>
                        {events.length === 0 ? (
                            <EmptyState
                                title="Belum ada penugasan tim"
                                description="Tugaskan tim pada halaman detail event."
                            />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Event</TH>
                                        <TH>Tanggal</TH>
                                        <TH>Anggota</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {events.map((event) => (
                                        <TR key={event.id}>
                                            <TD>
                                                <Link
                                                    href={route('events.show', event.id)}
                                                    className="font-medium text-slate-800 hover:text-indigo-600"
                                                >
                                                    {event.event_name}
                                                </Link>
                                                <p className="text-xs text-slate-400">
                                                    {event.brand} · PIC: {event.pic ?? '-'}
                                                </p>
                                            </TD>
                                            <TD>{formatDate(event.event_date)}</TD>
                                            <TD>
                                                <div className="flex flex-wrap gap-1">
                                                    {event.members?.map((m: any, i: number) => (
                                                        <Badge key={i} variant="outline">
                                                            {m.name} · {m.position}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </CardTable>
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Kandidat Tim Lapangan</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {teamMembers.length === 0 && (
                            <p className="text-sm text-slate-400">
                                Belum ada pengguna dengan role tim.
                            </p>
                        )}
                        {teamMembers.map((member) => (
                            <div
                                key={member.id}
                                className="rounded-xl border border-slate-200/80 bg-slate-50/50 px-3.5 py-2.5"
                            >
                                <p className="text-sm font-medium text-slate-700">
                                    {member.name}
                                </p>
                                <p className="text-xs text-slate-400">
                                    {member.email}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
