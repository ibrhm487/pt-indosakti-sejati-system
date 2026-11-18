import { EmptyState, PageHeader } from '@/Components/AppBits';
import { QuickReportEventOption } from '@/Components/QuickReportForm';
import { Badge, Button, Card } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type QuickReportTask = QuickReportEventOption & {
    status: 'pending' | 'submitted';
    status_label: string;
    report_id: number | null;
    submitted_at: string | null;
};

export default function QuickReportsIndex({
    canCreate,
    events,
    tasks,
}: {
    canCreate: boolean;
    events: QuickReportEventOption[];
    tasks: QuickReportTask[];
}) {
    return (
        <AppLayout title="Quick Report">
            <Head title="Quick Report" />
            <PageHeader
                title="Quick Report"
                subtitle="Pilih tugas event, lengkapi form, lalu kirim laporan foto/video sekaligus absensi."
            >
                {canCreate && events.length > 0 && (
                    <Link href={route('quick-reports.create')}>
                        <Button variant="primary">
                            <Plus className="h-4 w-4" /> Buat Quick Report
                        </Button>
                    </Link>
                )}
            </PageHeader>

            {tasks.length === 0 ? (
                <Card>
                    <EmptyState
                        title="Belum ada tugas laporan"
                        description="Tugas muncul setelah PIC menugaskan Anda pada event yang siap dilaporkan."
                    />
                </Card>
            ) : (
                <>
                    <div className="grid gap-3 md:grid-cols-2">
                        {tasks.map((task) => (
                            <Card
                                key={task.id}
                                className="flex items-center justify-between gap-4 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium text-text">
                                        {task.event_name}
                                    </p>
                                    <p className="text-xs text-muted">
                                        {formatDate(task.activity_date ?? task.event_date)}
                                        {task.submitted_at
                                            ? ` · Dikirim ${formatDateTime(task.submitted_at)}`
                                            : ''}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <Badge
                                        variant={
                                            task.status === 'submitted' ? 'success' : 'warning'
                                        }
                                    >
                                        {task.status_label}
                                    </Badge>
                                    {task.status === 'pending' && canCreate && (
                                        <Link
                                            href={route('quick-reports.create', {
                                                event: task.id,
                                            })}
                                        >
                                            <Button size="sm">Isi</Button>
                                        </Link>
                                    )}
                                    {task.status === 'submitted' && task.report_id && (
                                        <Link
                                            href={route('quick-reports.show', task.report_id)}
                                        >
                                            <Button size="sm" variant="outline">
                                                Lihat detail
                                            </Button>
                                        </Link>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                </>
            )}
        </AppLayout>
    );
}
