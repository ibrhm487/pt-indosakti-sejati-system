import { CardTable, FilterCard, PageHeader } from '@/Components/AppBits';
import { Badge, Field, Select, StatCard, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function AttendancePayroll({
    event,
    events,
    rows,
    summary,
    filters,
}: {
    event: { id: number; event_name: string } | null;
    events: any[];
    rows: {
        id: number;
        user_id: number | string;
        mrv_number: string;
        user: string;
        event: string;
        event_date: string | null;
        issued_at: string | null;
        status: string;
        qualifies: boolean;
    }[];
    summary: {
        rate: number;
        eligible_shifts: number;
        total: number;
        incomplete: number;
        by_user: { user_id: number | string; user: string; eligible_shifts: number; total: number }[];
        by_event: { event_id: number | string; event: string; event_date: string | null; shifts: number; total: number }[];
    };
    filters: { event_id?: number | null };
}) {
    const changeEvent = (e: FormEvent<HTMLSelectElement>) => {
        const event_id = e.currentTarget.value;
        router.get(route('attendance.payroll'), { event_id: event_id || undefined }, { preserveState: true });
    };

    return (
        <AppLayout title="Payroll Shift Tim">
            <Head title="Payroll Shift Tim" />
            <PageHeader
                title="Payroll Shift Tim"
                subtitle={event ? event.event_name : 'Shift dihitung setelah MRV diterbitkan gudang'}
            />

            <FilterCard>
                <Field label="Filter Event" className="sm:w-56">
                    <Select value={filters.event_id ?? ''} onChange={changeEvent}>
                        <option value="">Semua Event</option>
                        {events.map((e: any) => (
                            <option key={e.id} value={e.id}>
                                {e.event_name}
                            </option>
                        ))}
                    </Select>
                </Field>
            </FilterCard>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Rate Per Shift" value={formatCurrency(summary.rate)} />
                <StatCard label="Shift Layak Bayar" value={summary.eligible_shifts} />
                <StatCard label="Total Payroll" value={formatCurrency(summary.total)} />
                <StatCard label="Absensi Belum Lengkap" value={summary.incomplete} tone={summary.incomplete > 0 ? 'warning' : 'default'} />
            </div>

            {summary.by_event.length > 0 && (
                <div className="mb-6">
                    <CardTable>
                        <p className="px-4 pt-4 text-sm font-semibold text-text">Rekap Shift Per Event</p>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Event</TH>
                                    <TH>Tanggal</TH>
                                    <TH className="text-right">Shift</TH>
                                    <TH className="text-right">Total Fee</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {summary.by_event.map((e) => (
                                    <TR key={e.event_id}>
                                        <TD>{e.event}</TD>
                                        <TD>{formatDate(e.event_date)}</TD>
                                        <TD className="text-right">{e.shifts}</TD>
                                        <TD className="text-right">{formatCurrency(e.total)}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </CardTable>
                </div>
            )}

            {summary.by_user.length > 0 && (
                <div className="mb-6">
                    <CardTable>
                        <p className="px-4 pt-4 text-sm font-semibold text-text">Rekap Per Orang</p>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Anggota Tim</TH>
                                    <TH className="text-right">Shift Layak</TH>
                                    <TH className="text-right">Total</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {summary.by_user.map((u) => (
                                    <TR key={u.user_id}>
                                        <TD>{u.user}</TD>
                                        <TD className="text-right">{u.eligible_shifts}</TD>
                                        <TD className="text-right">{formatCurrency(u.total)}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </CardTable>
                </div>
            )}

            <CardTable>
                {rows.length === 0 ? (
                    <p className="p-6 text-sm text-muted">
                        Belum ada MRV yang sudah diterbitkan untuk anggota tim pada filter ini.
                    </p>
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Nama</TH>
                                <TH>MRV</TH>
                                <TH>Event</TH>
                                <TH>Tanggal</TH>
                                <TH>Stok Diterbitkan</TH>
                                <TH>Status</TH>
                                <TH>Shift Payroll</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {rows.map((r) => (
                                <TR key={`${r.id}-${r.user_id}`}>
                                    <TD>{r.user}</TD>
                                    <TD>{r.mrv_number}</TD>
                                    <TD>{r.event ?? '-'}</TD>
                                    <TD>{formatDate(r.event_date)}</TD>
                                    <TD>{formatDateTime(r.issued_at)}</TD>
                                    <TD>{r.status}</TD>
                                    <TD>
                                        {r.qualifies ? (
                                            <Badge variant="success">Dihitung</Badge>
                                        ) : (
                                            <Badge variant="warning">Duplikat hari ini</Badge>
                                        )}
                                    </TD>
                                </TR>
                            ))}
                        </TBody>
                    </Table>
                )}
            </CardTable>
        </AppLayout>
    );
}
