import { Tabs } from '@/Components/AppBits';
import { router, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowDownToLine,
    ArrowUpFromLine,
    Boxes,
    CalendarDays,
    ClipboardList,
    ImageUp,
    Package,
    ShieldCheck,
    Target,
    UsersRound,
    UtensilsCrossed,
} from 'lucide-react';
import { ReactNode } from 'react';

type WorkspaceTab = {
    key: string;
    label: string;
    icon: ReactNode;
    href: string;
    active: boolean;
};

type WorkspaceDef = {
    title: string;
    subtitle?: string;
    tabs: WorkspaceTab[];
};

const ic = 'h-4 w-4';

function parseTab(url: string): string {
    const query = url.includes('?') ? url.split('?')[1] : '';
    return new URLSearchParams(query).get('tab') ?? '';
}

/**
 * Role workspace definition. Tabs mirror the reference app; active state is
 * derived from the current route (or `?tab=` where a single page hosts
 * multiple sub-views, e.g. Kitchen).
 */
function workspaceForRole(
    role: string | null | undefined,
    url: string,
): WorkspaceDef | null {
    const at = (pattern: string) => route().current(pattern);
    const tab = parseTab(url);

    switch (role) {
        case 'permitter':
            return {
                title: 'Ruang Kerja Permitter',
                subtitle: 'Kelola FJP sekolah & surat perizinan',
                tabs: [
                    {
                        key: 'fjp',
                        label: 'FJP Sekolah',
                        icon: <ClipboardList className={ic} />,
                        href: route('fjp.index'),
                        active: at('fjp.index') || at('fjp.show'),
                    },
                    {
                        key: 'target',
                        label: 'Target Quartal',
                        icon: <Target className={ic} />,
                        href: route('fjp.quarterly-targets.index'),
                        active: at('fjp.quarterly-targets*'),
                    },
                    {
                        key: 'izin',
                        label: 'Perizinan',
                        icon: <ShieldCheck className={ic} />,
                        href: route('permits.index'),
                        active: at('permits.*'),
                    },
                ],
            };

        case 'team':
            return {
                title: 'Tim Lapangan',
                subtitle: 'Lihat jadwal dari PIC, kirim foto aktivitas, dan laporan kilat',
                tabs: [
                    {
                        key: 'jadwal',
                        label: 'Jadwal Saya',
                        icon: <CalendarDays className={ic} />,
                        href: route('team.index'),
                        active: at('team.*'),
                    },
                    {
                        key: 'foto',
                        label: 'Laporan Foto',
                        icon: <ImageUp className={ic} />,
                        href: route('reports.index'),
                        active: at('reports.index') || at('reports.show'),
                    },
                    {
                        key: 'quick',
                        label: 'Quick Report',
                        icon: <Activity className={ic} />,
                        href: route('quick-reports.index'),
                        active: at('quick-reports.*'),
                    },
                ],
            };

        case 'pic':
            return {
                title: 'Ruang Kerja PIC',
                subtitle: 'Kelola jadwal, tim, kebutuhan operasional, dan laporan event',
                tabs: [
                    {
                        key: 'event',
                        label: 'Jadwal Event',
                        icon: <CalendarDays className={ic} />,
                        href: route('events.index'),
                        active: at('events.*'),
                    },
                    {
                        key: 'tim',
                        label: 'Pembagian Tim',
                        icon: <UsersRound className={ic} />,
                        href: route('team.index'),
                        active: at('team.*'),
                    },
                    {
                        key: 'mrv',
                        label: 'Form MRV',
                        icon: <Package className={ic} />,
                        href: route('warehouse.mrv.index'),
                        active: at('warehouse.mrv*'),
                    },
                    {
                        key: 'laporan',
                        label: 'Laporan Harian',
                        icon: <ClipboardList className={ic} />,
                        href: route('reports.index'),
                        active: at('reports.*'),
                    },
                    {
                        key: 'quick',
                        label: 'Quick Report',
                        icon: <Activity className={ic} />,
                        href: route('quick-reports.index'),
                        active: at('quick-reports.*'),
                    },
                    {
                        key: 'shift',
                        label: 'Shift & Gaji',
                        icon: <Activity className={ic} />,
                        href: route('attendance.payroll'),
                        active: at('attendance*'),
                    },
                ],
            };

        case 'warehouse':
            return {
                title: 'Gudang / Warehouse',
                subtitle:
                    'Terima permintaan barang dari PIC, catat barang masuk/keluar, dan pantau stok',
                tabs: [
                    {
                        key: 'mrv',
                        label: 'MRV Masuk',
                        icon: <Package className={ic} />,
                        href: route('warehouse.mrv.index'),
                        active: at('warehouse.mrv*'),
                    },
                    {
                        key: 'masuk',
                        label: 'Barang Masuk',
                        icon: <ArrowDownToLine className={ic} />,
                        href: route('warehouse.receipts.index'),
                        active: at('warehouse.receipts*'),
                    },
                    {
                        key: 'keluar',
                        label: 'Barang Keluar',
                        icon: <ArrowUpFromLine className={ic} />,
                        href: route('warehouse.inventory.movements'),
                        active: at('warehouse.inventory.movements'),
                    },
                    {
                        key: 'stok',
                        label: 'Rekap Stok',
                        icon: <Boxes className={ic} />,
                        href: route('warehouse.inventory.index'),
                        active: at('warehouse.inventory'),
                    },
                ],
            };

        case 'kitchen':
            return {
                title: 'Kitchen',
                subtitle:
                    'Jadwal produksi dari PIC, bukti foto masak, dan foto cuci jug',
                tabs: [
                    {
                        key: 'jadwal',
                        label: 'Jadwal Kitchen',
                        icon: <UtensilsCrossed className={ic} />,
                        href: route('kitchen.index'),
                        active: at('kitchen.index') && tab === '',
                    },
                    {
                        key: 'masak',
                        label: 'Foto Masak',
                        icon: <ImageUp className={ic} />,
                        href: route('kitchen.index', { tab: 'masak' }),
                        active: at('kitchen.index') && tab === 'masak',
                    },
                    {
                        key: 'jug',
                        label: 'Foto Cuci Jug',
                        icon: <ImageUp className={ic} />,
                        href: route('kitchen.index', { tab: 'jug' }),
                        active: at('kitchen.index') && tab === 'jug',
                    },
                ],
            };

        case 'admin_event':
            return {
                title: 'Admin Event',
                subtitle: 'Laporan per event & verifikasi',
                tabs: [
                    {
                        key: 'event',
                        label: 'Event',
                        icon: <CalendarDays className={ic} />,
                        href: route('events.index'),
                        active: at('events.*'),
                    },
                    {
                        key: 'laporan',
                        label: 'Laporan per Event',
                        icon: <ClipboardList className={ic} />,
                        href: route('reports.by-event'),
                        active: at('reports.*'),
                    },
                    {
                        key: 'quick',
                        label: 'Quick Report',
                        icon: <Activity className={ic} />,
                        href: route('quick-reports.index'),
                        active: at('quick-reports.*'),
                    },
                    {
                        key: 'tim',
                        label: 'Tim',
                        icon: <UsersRound className={ic} />,
                        href: route('team.index'),
                        active: at('team.*'),
                    },
                    {
                        key: 'absensi',
                        label: 'Absensi',
                        icon: <Activity className={ic} />,
                        href: route('attendance.index'),
                        active: at('attendance*'),
                    },
                ],
            };

        default:
            return null;
    }
}

export default function WorkspaceBar() {
    const page = usePage() as any;
    const role = page.props?.auth?.user?.active_role?.slug ?? null;
    const workspace = workspaceForRole(role, (page.url as string) ?? '');

    if (!workspace) return null;

    return (
        <div className="mb-5 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
            <div className="mb-3">
                <h1 className="text-base font-semibold text-slate-900">
                    {workspace.title}
                </h1>
                {workspace.subtitle && (
                    <p className="mt-0.5 text-sm text-slate-500">
                        {workspace.subtitle}
                    </p>
                )}
            </div>
            <Tabs
                items={workspace.tabs.map((t) => ({
                    key: t.key,
                    label: t.label,
                    icon: t.icon,
                }))}
                active={workspace.tabs.find((t) => t.active)?.key ?? ''}
                onChange={(key) => {
                    const tab = workspace.tabs.find((t) => t.key === key);
                    if (tab) {
                        router.get(
                            tab.href,
                            {},
                            { preserveState: true, preserveScroll: true },
                        );
                    }
                }}
            />
        </div>
    );
}
