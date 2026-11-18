import {
    Activity,
    ArrowDownToLine,
    BookOpenCheck,
    Boxes,
    CalendarDays,
    ClipboardList,
    FileText,
    KeyRound,
    LayoutDashboard,
    MapPin,
    Package,
    PackageMinus,
    ScrollText,
    ServerCog,
    Settings,
    ShieldCheck,
    Target,
    Users,
    UsersRound,
    UtensilsCrossed,
    Warehouse,
} from 'lucide-react';
import { ReactNode } from 'react';

export type NavChild = {
    label: string;
    routeName: string;
    routeParams?: Record<string, string | number>;
    icon?: ReactNode;
    permission?: string | string[];
    match?: string;
    description?: string;
    children?: NavChild[];
};

export type NavItem = {
    label: string;
    routeName?: string;
    icon: ReactNode;
    permission?: string | string[];
    match?: string;
    description?: string;
    children?: NavChild[];
};

export type NavGroup = { section?: string; items: NavItem[] };

export type EventBrandNav = { id: number; name: string; slug: string };

const ic = 'h-[18px] w-[18px]';

const dashboard: NavItem = {
    label: 'Dashboard',
    routeName: 'dashboard',
    icon: <LayoutDashboard className={ic} />,
};

/**
 * Sidebar navigation grouped per workspace role. Falls back to the full
 * operational menu for administrators / unknown roles.
 */
export function navForRole(
    role: string | null | undefined,
    permissions: string[],
    eventBrands: EventBrandNav[] = [],
): NavGroup[] {
    const can = (permission?: string | string[]) =>
        !permission ||
        (Array.isArray(permission)
            ? permission.some((item) => permissions.includes(item))
            : permissions.includes(permission));

    const eventBrandChildren: NavChild[] = eventBrands.map((brand) => ({
        label: brand.name,
        routeName: 'events.index',
        routeParams: { brand_id: brand.id },
    }));

    const filterChild = (child: NavChild): NavChild | null => {
        const children = child.children
            ?.map(filterChild)
            .filter((item): item is NavChild => item !== null);

        const permitted = can(child.permission) || (children?.length ?? 0) > 0;

        if (!permitted) {
            return null;
        }

        return {
            ...child,
            ...(child.children ? { children } : {}),
        };
    };

    const filterItem = (item: NavItem): NavItem | null => {
        if (item.children && item.children.length > 0) {
            const children = item.children
                .map(filterChild)
                .filter((child): child is NavChild => child !== null);

            if (!can(item.permission) && children.length === 0) {
                return null;
            }

            return { ...item, children };
        }

        return can(item.permission) ? item : null;
    };

    const filter = (groups: NavGroup[]): NavGroup[] =>
        groups
            .map((group) => ({
                ...group,
                items: group.items
                    .map(filterItem)
                    .filter((item): item is NavItem => item !== null),
            }))
            .filter((group) => group.items.length > 0);

    const adminGroups: NavGroup[] = [
        {
            items: [dashboard],
        },
        {
            section: 'Operasional',
            items: [
                {
                    label: 'Operasional',
                    icon: <CalendarDays className={ic} />,
                    permission: [
                        'events.view',
                        'fjp.view',
                        'permits.view',
                        'permits.approve',
                        'permits.reject',
                        'venues.approve',
                        'targets.manage',
                    ],
                    description: 'Event, FJP, verifikasi, perizinan, dan target.',
                    children: [
                        {
                            label: 'Event',
                            routeName: 'events.index',
                            icon: <CalendarDays className={ic} />,
                            permission: 'events.view',
                            match: 'events.*',
                            children: eventBrandChildren,
                        },
                        {
                            label: 'FJP',
                            routeName: 'fjp.index',
                            icon: <ClipboardList className={ic} />,
                            permission: 'fjp.view',
                            match: 'fjp.index',
                        },
                        {
                            label: 'Pembagian Tim',
                            routeName: 'fjp.team-assignment',
                            icon: <UsersRound className={ic} />,
                            permission: 'fjp.assign_team',
                            match: 'fjp.team-assignment',
                        },
                        {
                            label: 'Verifikasi',
                            routeName: 'verifications.index',
                            icon: <ShieldCheck className={ic} />,
                            permission: ['permits.approve', 'permits.reject', 'venues.approve'],
                            match: 'verifications.*',
                        },
                        {
                            label: 'Perizinan',
                            routeName: 'permits.index',
                            icon: <FileText className={ic} />,
                            permission: 'permits.view',
                            match: 'permits.*',
                        },
                        {
                            label: 'Target Quartal',
                            routeName: 'quarterly-targets.index',
                            icon: <Target className={ic} />,
                            permission: 'targets.manage',
                            match: 'quarterly-targets.*',
                        },
                    ],
                },
                {
                    label: 'Tim & Lapangan',
                    icon: <UsersRound className={ic} />,
                    permission: ['events.view', 'quick_reports.view', 'attendance.view'],
                    description: 'Jadwal tim, laporan cepat, dan absensi.',
                    children: [
                        {
                            label: 'Tim',
                            routeName: 'team.index',
                            icon: <UsersRound className={ic} />,
                            permission: 'events.view',
                            match: 'team.*',
                        },
                        {
                            label: 'Quick Report',
                            routeName: 'quick-reports.index',
                            icon: <Activity className={ic} />,
                            permission: 'quick_reports.view',
                            match: 'quick-reports.*',
                        },
                        {
                            label: 'Absensi',
                            routeName: 'attendance.index',
                            icon: <Activity className={ic} />,
                            permission: 'attendance.view',
                            match: 'attendance.*',
                        },
                    ],
                },
                {
                    label: 'Laporan',
                    icon: <BookOpenCheck className={ic} />,
                    permission: 'reports.view',
                    description: 'Riwayat laporan dan rekap harian.',
                    children: [
                        {
                            label: 'Laporan',
                            routeName: 'reports.index',
                            icon: <BookOpenCheck className={ic} />,
                            permission: 'reports.view',
                            match: 'reports.index',
                        },
                        {
                            label: 'Rekap Harian',
                            routeName: 'reports.daily',
                            icon: <CalendarDays className={ic} />,
                            permission: 'reports.view',
                            match: 'reports/daily*',
                        },
                    ],
                },
                {
                    label: 'Gudang & Dapur',
                    icon: <Warehouse className={ic} />,
                    permission: ['warehouse.view', 'kitchen.view'],
                    description: 'Stok gudang dan operasional dapur.',
                    children: [
                        {
                            label: 'Gudang',
                            routeName: 'warehouse.products.index',
                            icon: <Package className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse.*',
                        },
                        {
                            label: 'Kitchen',
                            routeName: 'kitchen.index',
                            icon: <UtensilsCrossed className={ic} />,
                            permission: 'kitchen.view',
                            match: 'kitchen.*',
                        },
                    ],
                },
            ],
        },
        {
            section: 'Data & Sistem',
            items: [
                {
                    label: 'Master',
                    icon: <MapPin className={ic} />,
                    permission: ['venues.view', 'admin.settings.manage', 'targets.manage'],
                    description: 'Lokasi, data master, dan target.',
                    children: [
                        {
                            label: 'Lokasi',
                            routeName: 'venues.index',
                            icon: <MapPin className={ic} />,
                            permission: 'venues.view',
                            match: 'venues.*',
                        },
                        {
                            label: 'Data Master',
                            routeName: 'admin.master.index',
                            icon: <FileText className={ic} />,
                            permission: 'admin.settings.manage',
                            match: 'admin/master-data*',
                        },
                        {
                            label: 'Target Quartal',
                            routeName: 'admin.quarterly-targets.index',
                            icon: <Target className={ic} />,
                            permission: 'admin.settings.manage',
                            match: 'admin/quarterly-targets*',
                        },
                    ],
                },
                {
                    label: 'Administrasi',
                    icon: <ServerCog className={ic} />,
                    permission: [
                        'admin.users.manage',
                        'admin.personnel.manage',
                        'admin.roles.manage',
                        'admin.settings.manage',
                        'admin.audit.view',
                        'admin.system.view',
                        'admin.logs.view',
                    ],
                    description: 'Pengguna, role, pengaturan, dan pemantauan sistem.',
                    children: [
                        {
                            label: 'Pengguna',
                            routeName: 'admin.users.index',
                            icon: <Users className={ic} />,
                            permission: 'admin.users.manage',
                            match: 'admin/users*',
                        },
                        {
                            label: 'Personel',
                            routeName: 'admin.personnel.index',
                            icon: <UsersRound className={ic} />,
                            permission: 'admin.personnel.manage',
                            match: 'admin/personnel*',
                        },
                        {
                            label: 'Role & Izin',
                            routeName: 'admin.roles.index',
                            icon: <KeyRound className={ic} />,
                            permission: 'admin.roles.manage',
                            match: 'admin/roles*',
                        },
                        {
                            label: 'Pengaturan',
                            routeName: 'admin.settings.index',
                            icon: <Settings className={ic} />,
                            permission: 'admin.settings.manage',
                            match: 'admin/settings*',
                        },
                        {
                            label: 'Audit Log',
                            routeName: 'admin.audit.index',
                            icon: <ShieldCheck className={ic} />,
                            permission: 'admin.audit.view',
                            match: 'admin/audit-logs*',
                        },
                        {
                            label: 'Kesehatan Sistem',
                            routeName: 'admin.system.index',
                            icon: <ServerCog className={ic} />,
                            permission: 'admin.system.view',
                            match: 'admin/system*',
                        },
                        {
                            label: 'Log Aplikasi',
                            routeName: 'admin.logs.index',
                            icon: <ScrollText className={ic} />,
                            permission: 'admin.logs.view',
                            match: 'admin/logs*',
                        },
                    ],
                },
            ],
        },
    ];

    switch (role) {
        case 'developer':
            return filter(adminGroups);

        case 'permitter':
            return filter([
                {
                    items: [dashboard],
                },
                {
                    section: 'Permitter',
                    items: [
                        {
                            label: 'FJP',
                            routeName: 'fjp.index',
                            icon: <ClipboardList className={ic} />,
                            permission: 'fjp.view',
                            match: 'fjp.index',
                        },
                        {
                            label: 'Lokasi',
                            routeName: 'venues.index',
                            icon: <MapPin className={ic} />,
                            permission: 'venues.view',
                            match: 'venues.*',
                        },
                        {
                            label: 'Target Quartal',
                            routeName: 'quarterly-targets.index',
                            icon: <Target className={ic} />,
                            permission: 'targets.manage',
                            match: 'quarterly-targets.*',
                        },
                        {
                            label: 'Permintaan Sampling',
                            routeName: 'warehouse.mrv.index',
                            icon: <Package className={ic} />,
                            permission: 'warehouse.request',
                            match: 'warehouse/mrv*',
                        },
                    ],
                },
            ]);

        case 'pic':
            return filter([
                {
                    items: [dashboard],
                },
                {
                    section: 'Ruang Kerja PIC',
                    items: [
                        {
                            label: 'FJP',
                            routeName: 'fjp.index',
                            icon: <ClipboardList className={ic} />,
                            permission: 'fjp.view',
                            match: 'fjp.*',
                        },
                        {
                            label: 'Lokasi',
                            routeName: 'venues.index',
                            icon: <MapPin className={ic} />,
                            permission: 'venues.view',
                            match: 'venues.*',
                        },
                        {
                            label: 'Verifikasi',
                            routeName: 'verifications.index',
                            icon: <ShieldCheck className={ic} />,
                            permission: ['permits.approve', 'permits.reject', 'venues.approve'],
                            match: 'verifications.*',
                        },
                        {
                            label: 'Pembagian Tim',
                            routeName: 'fjp.team-assignment',
                            icon: <UsersRound className={ic} />,
                            permission: 'fjp.assign_team_members',
                            match: 'fjp.team-assignment',
                        },
                        {
                            label: 'Form Permintaan',
                            routeName: 'warehouse.mrv.index',
                            icon: <Package className={ic} />,
                            permission: 'warehouse.request',
                            match: 'warehouse/mrv*',
                        },
                        {
                            label: 'Shift & Gaji',
                            routeName: 'attendance.payroll',
                            icon: <Activity className={ic} />,
                            permission: 'attendance.payroll',
                            match: 'attendance*',
                        },
                    ],
                },
                {
                    section: 'Gudang & Dapur',
                    items: [
                        {
                            label: 'Rekap Stok',
                            routeName: 'warehouse.inventory.index',
                            icon: <Warehouse className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/inventory',
                        },
                        {
                            label: 'Kitchen',
                            routeName: 'kitchen.index',
                            icon: <UtensilsCrossed className={ic} />,
                            permission: 'kitchen.view',
                            match: 'kitchen.*',
                        },
                    ],
                },
            ]);

        case 'team':
            return filter([
                {
                    items: [dashboard],
                },
                {
                    section: 'Jadwal Tim',
                    items: [
                        {
                            label: 'Jadwal Saya',
                            routeName: 'team.index',
                            icon: <CalendarDays className={ic} />,
                            permission: 'events.view',
                            match: 'team.*',
                        },
                    ],
                },
                {
                    section: 'Laporan & Absensi',
                    items: [
                        {
                            label: 'Quick Report',
                            routeName: 'quick-reports.index',
                            icon: <Activity className={ic} />,
                            permission: 'quick_reports.view',
                            match: 'quick-reports.index',
                        },
                        {
                            label: 'Riwayat Laporan Saya',
                            routeName: 'quick-reports.history',
                            icon: <BookOpenCheck className={ic} />,
                            permission: 'quick_reports.view',
                            match: 'quick-reports.history*',
                        },
                        {
                            label: 'Absensi Saya',
                            routeName: 'attendance.index',
                            icon: <Activity className={ic} />,
                            permission: 'attendance.view',
                            match: 'attendance.index',
                        },
                    ],
                },
            ]);

        case 'warehouse':
            return filter([
                {
                    items: [dashboard],
                },
                {
                    section: 'Gudang / Warehouse',
                    items: [
                        {
                            label: 'Produk',
                            routeName: 'warehouse.products.index',
                            icon: <Package className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/products*',
                        },
                        {
                            label: 'Permintaan Masuk / MRV',
                            routeName: 'warehouse.mrv.index',
                            icon: <Package className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/mrv*',
                        },
                        {
                            label: 'Barang Masuk / MRS',
                            routeName: 'warehouse.receipts.index',
                            icon: <ArrowDownToLine className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/receipts*',
                        },
                        {
                            label: 'Barang Keluar / OMF',
                            routeName: 'warehouse.omf.index',
                            icon: <PackageMinus className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/omf*',
                        },
                        {
                            label: 'MIOS',
                            routeName: 'warehouse.inventory.mios',
                            icon: <Boxes className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/inventory/mios*',
                        },
                    ],
                },
            ]);

        case 'kitchen':
            return filter([
                {
                    items: [dashboard],
                },
                {
                    section: 'Dapur',
                    items: [
                        {
                            label: 'Dapur & Higienitas',
                            routeName: 'kitchen.index',
                            icon: <UtensilsCrossed className={ic} />,
                            permission: 'kitchen.view',
                            match: 'kitchen.*',
                        },
                    ],
                },
            ]);

        case 'admin_event':
            return filter([
                {
                    items: [dashboard],
                },
                {
                    section: 'Admin Event',
                    items: [
                        {
                            label: 'FJP',
                            routeName: 'fjp.index',
                            icon: <ClipboardList className={ic} />,
                            permission: 'fjp.view',
                            match: 'fjp.*',
                        },
                        {
                            label: 'Event',
                            routeName: 'events.index',
                            icon: <CalendarDays className={ic} />,
                            permission: 'events.view',
                            match: 'events.*',
                            children: eventBrandChildren,
                        },
                        {
                            label: 'Rekap Harian',
                            routeName: 'reports.daily',
                            icon: <CalendarDays className={ic} />,
                            permission: 'reports.view',
                            match: 'reports/daily*',
                        },
                        {
                            label: 'Laporan per Event',
                            routeName: 'reports.by-event',
                            icon: <BookOpenCheck className={ic} />,
                            permission: 'reports.view',
                            match: 'reports.*',
                        },
                        {
                            label: 'Riwayat Laporan',
                            routeName: 'reports.index',
                            icon: <FileText className={ic} />,
                            permission: 'reports.view',
                            match: 'reports.index',
                        },
                        {
                            label: 'Tim',
                            routeName: 'team.index',
                            icon: <UsersRound className={ic} />,
                            permission: 'events.view',
                            match: 'team.*',
                        },
                        {
                            label: 'Absensi',
                            routeName: 'attendance.index',
                            icon: <Activity className={ic} />,
                            permission: 'attendance.view',
                            match: 'attendance*',
                        },
                        {
                            label: 'Shift & Gaji',
                            routeName: 'attendance.payroll',
                            icon: <Activity className={ic} />,
                            permission: 'attendance.payroll',
                            match: 'attendance/payroll*',
                        },
                    ],
                },
            ]);

        default:
            return filter(adminGroups);
    }
}
