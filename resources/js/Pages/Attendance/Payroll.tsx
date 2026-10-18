import { CardTable, FilterCard, PageHeader } from '@/Components/AppBits';
import { Badge, Field, Select, StatCard, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatCurrency, formatDateTime } from '@/lib/utils';
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
    rows: any[];
    summary: { rate: number; eligible_shifts: number; total: number; incomplete: number };
    filters: { event_id?: number | null };
}) {
    const changeEvent = (e: FormEvent<HTMLSelectElement>) => {
        const event_id = e.currentTarget.value;
        router.get(route('attendance.payroll'), { event_id: event_id || undefined }, { preserveState: true });
    };

    return (
        <AppLayout title="Payroll Absensi">
            <Head title="Payroll Absensi" />
            <PageHeader title="Payroll Absensi" subtitle={event ? event.event_name : 'Semua Event'} />

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
                <StatCard label="Belum Lengkap" value={summary.incomplete} tone={summary.incomplete > 0 ? 'warning' : 'default'} />
            </div>

            <CardTable>
                {rows.length === 0 ? (
                    <p className="p-6 text-sm text-slate-400">Belum ada data absensi.</p>
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>User</TH>
                                <TH>Check In</TH>
                                <TH>Check Out</TH>
                                <TH>Status</TH>
                                <TH>Layak Bayar</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {rows.map((r: any) => (
                                <TR key={r.id}>
                                    <TD>{r.user}</TD>
                                    <TD>{formatDateTime(r.check_in_at)}</TD>
                                    <TD>{formatDateTime(r.check_out_at)}</TD>
                                    <TD>{r.status}</TD>
                                    <TD>
                                        {r.qualifies ? (
                                            <Badge variant="success">Ya</Badge>
                                        ) : (
                                            <Badge variant="danger">Tidak</Badge>
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
