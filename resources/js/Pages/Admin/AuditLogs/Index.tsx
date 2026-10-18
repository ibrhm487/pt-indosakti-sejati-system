import { CardTable, EmptyState, FilterCard, PageHeader, Pagination, SearchInput } from '@/Components/AppBits';
import { Badge, Button, Modal, Select, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function AdminAuditLogsIndex({
    logs,
    filters,
    modules,
}: {
    logs: any;
    filters: { module?: string; action?: string; user_id?: string | number };
    modules: string[];
}) {
    const [module, setModule] = useState(filters.module ?? '');
    const [action, setAction] = useState(filters.action ?? '');
    const [detail, setDetail] = useState<any | null>(null);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.audit.index'), { module, action }, { preserveState: true });
    };

    return (
        <AppLayout title="Audit Log">
            <Head title="Audit Log" />
            <PageHeader title="Audit Log" subtitle="Riwayat aktivitas sistem" />

            <FilterCard>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <Select className="sm:w-64" value={module} onChange={(e) => setModule(e.target.value)}>
                        <option value="">Semua modul</option>
                        {modules.map((m) => (
                            <option key={m} value={m}>{m}</option>
                        ))}
                    </Select>
                    <SearchInput
                        placeholder="Cari aksi…"
                        value={action}
                        onChange={(e) => setAction(e.target.value)}
                    />
                    <Button type="submit" variant="default" className="h-11 rounded-xl px-5">Terapkan</Button>
                </form>
            </FilterCard>

            <CardTable>
                {logs.data.length === 0 ? (
                    <EmptyState title="Belum ada log" />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Waktu</TH>
                                    <TH>Pengguna</TH>
                                    <TH>Aksi</TH>
                                    <TH>Modul</TH>
                                    <TH>Rekaman</TH>
                                    <TH>IP</TH>
                                    <TH className="text-right">Detail</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {logs.data.map((l: any) => (
                                    <TR key={l.id}>
                                        <TD className="whitespace-nowrap">{formatDateTime(l.at)}</TD>
                                        <TD>
                                            <p className="font-medium text-slate-800">{l.user ?? 'Sistem'}</p>
                                            {l.role && <p className="text-xs text-slate-400">{l.role}</p>}
                                        </TD>
                                        <TD><Badge variant="outline">{l.action}</Badge></TD>
                                        <TD>{l.module ?? '-'}</TD>
                                        <TD>
                                            {l.record_type
                                                ? `${l.record_type} #${l.record_id ?? '-'}`
                                                : '-'}
                                        </TD>
                                        <TD>{l.ip_address ?? '-'}</TD>
                                        <TD className="text-right">
                                            <Button size="sm" variant="outline" onClick={() => setDetail(l)}>
                                                <Eye className="h-3.5 w-3.5" /> Lihat
                                            </Button>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={logs} />
                    </>
                )}
            </CardTable>

            <Modal
                open={!!detail}
                onClose={() => setDetail(null)}
                title="Detail Audit Log"
                footer={
                    <Button variant="outline" size="sm" onClick={() => setDetail(null)}>Tutup</Button>
                }
            >
                {detail && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">Aksi</p>
                                <p className="text-slate-800">{detail.action}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">Modul</p>
                                <p className="text-slate-800">{detail.module ?? '-'}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">Rekaman</p>
                                <p className="text-slate-800">
                                    {detail.record_type ? `${detail.record_type} #${detail.record_id ?? '-'}` : '-'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">Waktu</p>
                                <p className="text-slate-800">{formatDateTime(detail.at)}</p>
                            </div>
                        </div>
                        <div>
                            <p className="mb-1 text-xs uppercase tracking-wide text-slate-400">Nilai Lama</p>
                            <pre className="max-h-48 overflow-auto rounded-md bg-slate-50 p-3 text-xs text-slate-700">
                                {detail.old_values ? JSON.stringify(detail.old_values, null, 2) : '-'}
                            </pre>
                        </div>
                        <div>
                            <p className="mb-1 text-xs uppercase tracking-wide text-slate-400">Nilai Baru</p>
                            <pre className="max-h-48 overflow-auto rounded-md bg-slate-50 p-3 text-xs text-slate-700">
                                {detail.new_values ? JSON.stringify(detail.new_values, null, 2) : '-'}
                            </pre>
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
