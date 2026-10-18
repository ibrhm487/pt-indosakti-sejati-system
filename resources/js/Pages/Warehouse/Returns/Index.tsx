import { CardTable, EmptyState, PageHeader, Pagination } from '@/Components/AppBits';
import { Button, StatusBadge, Table, TBody, TD, TH, THead, TR } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type ReturnRow = {
    id: number;
    return_number: string;
    mrv_number: string | null;
    event: string | null;
    status: string;
    recorded_by: string | null;
};

export default function ReturnsIndex({
    returns,
}: {
    returns: { data: ReturnRow[]; links: any[]; [k: string]: any };
}) {
    const permissions = usePage().props.auth.permissions;
    const canReturn = permissions.includes('warehouse.return');

    return (
        <AppLayout title="Pengembalian">
            <Head title="Pengembalian" />
            <PageHeader title="Pengembalian" subtitle="Barang kembali dari event, baik maupun rusak">
                {canReturn && (
                    <Link href={route('warehouse.returns.create')}>
                        <Button variant="primary">
                            <Plus className="h-4 w-4" /> Pengembalian
                        </Button>
                    </Link>
                )}
            </PageHeader>

            <CardTable>
                {returns.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada pengembalian"
                        description="Catat pengembalian untuk MRV yang sudah dikeluarkan."
                    />
                ) : (
                    <>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>No. Pengembalian</TH>
                                    <TH>No. MRV</TH>
                                    <TH>Event</TH>
                                    <TH>Status</TH>
                                    <TH>Dicatat Oleh</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {returns.data.map((r) => (
                                    <TR key={r.id}>
                                        <TD className="font-medium text-slate-800">{r.return_number}</TD>
                                        <TD>{r.mrv_number ?? '-'}</TD>
                                        <TD>{r.event ?? '-'}</TD>
                                        <TD>
                                            <StatusBadge
                                                status={r.status}
                                                label={r.status === 'processed' ? 'Diproses' : 'Menunggu'}
                                            />
                                        </TD>
                                        <TD>{r.recorded_by ?? '-'}</TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <Pagination paginator={returns} />
                    </>
                )}
            </CardTable>
        </AppLayout>
    );
}
