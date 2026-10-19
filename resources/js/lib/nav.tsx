import {
    Activity,
    ArrowDownToLine,
    ArrowUpFromLine,
    BookOpenCheck,
    Boxes,
    CalendarDays,
    ClipboardList,
    FileText,
    LayoutDashboard,
    Package,
    Settings,
    ShieldCheck,
    Target,
    Users,
    UsersRound,
    UtensilsCrossed,
    Warehouse,
} from 'lucide-react';
import { ReactNode } from 'react';

export type NavItem = {
    label: string;
    routeName: string;
    icon: ReactNode;
    permission?: string;
    match?: string;
};

export type NavGroup = { section?: string; items: NavItem[] };

const ic = 'h-4 w-4';

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
): NavGroup[] {
    const can = (permission?: string) =>
        !permission || permissions.includes(permission);

    const filter = (groups: NavGroup[]): NavGroup[] =>
        groups
            .map((group) => ({
                ...group,
                items: group.items.filter((i) => can(i.permission)),
            }))
            .filter((group) => group.items.length > 0);

    const adminGroups: NavGroup[] = [
        {
            items: [
                dashboard,
                {
                    label: 'Event',
                    routeName: 'events.index',
                    icon: <CalendarDays className={ic} />,
                    permission: 'events.view',
                    match: 'events.*',
                },
                {
                    label: 'FJP',
                    routeName: 'fjp.index',
                    icon: <ClipboardList className={ic} />,
                    permission: 'fjp.view',
                    match: 'fjp.*',
                },
                {
                    label: 'Target Quartal',
                    routeName: 'quarterly-targets.index',
                    icon: <Target className={ic} />,
                    permission: 'targets.manage',
                    match: 'quarterly-targets.*',
                },
                {
                    label: 'Perizinan',
                    routeName: 'permits.index',
                    icon: <ShieldCheck className={ic} />,
                    permission: 'permits.view',
                    match: 'permits.*',
                },
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
                {
                    label: 'Tim',
                    routeName: 'team.index',
                    icon: <UsersRound className={ic} />,
                    permission: 'events.view',
                    match: 'team.*',
                },
                {
                    label: 'Laporan',
                    routeName: 'reports.index',
                    icon: <BookOpenCheck className={ic} />,
                    permission: 'reports.view',
                    match: 'reports.*',
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
                    section: 'Administrasi',
                    items: [
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
                    ],
                },
    ];

    switch (role) {
        case 'permitter':
            return filter([
                {
                    items: [dashboard],
                },
                {
                    section: 'Permitter',
                    items: [
                        {
                            label: 'FJP Sekolah',
                            routeName: 'fjp.index',
                            icon: <ClipboardList className={ic} />,
                            permission: 'fjp.view',
                            match: 'fjp.*',
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
                    section: 'Perizinan',
                    items: [
                        {
                            label: 'Perizinan',
                            routeName: 'permits.index',
                            icon: <ShieldCheck className={ic} />,
                            permission: 'permits.view',
                            match: 'permits.*',
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
                            label: 'Jadwal Event',
                            routeName: 'events.index',
                            icon: <CalendarDays className={ic} />,
                            permission: 'events.view',
                            match: 'events.*',
                        },
                        {
                            label: 'Pembagian Tim',
                            routeName: 'team.index',
                            icon: <UsersRound className={ic} />,
                            permission: 'events.view',
                            match: 'team.*',
                        },
                        {
                            label: 'Laporan Harian',
                            routeName: 'reports.index',
                            icon: <BookOpenCheck className={ic} />,
                            permission: 'reports.view',
                            match: 'reports.*',
                        },
                        {
                            label: 'Quick Report',
                            routeName: 'quick-reports.index',
                            icon: <Activity className={ic} />,
                            permission: 'quick_reports.view',
                            match: 'quick-reports.*',
                        },
                        {
                            label: 'Form MRV',
                            routeName: 'warehouse.mrv.index',
                            icon: <Package className={ic} />,
                            permission: 'warehouse.view',
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
                    section: 'Tim Lapangan',
                    items: [
                        {
                            label: 'Jadwal Saya',
                            routeName: 'team.index',
                            icon: <CalendarDays className={ic} />,
                            permission: 'events.view',
                            match: 'team.*',
                        },
                        {
                            label: 'Laporan Foto',
                            routeName: 'reports.index',
                            icon: <BookOpenCheck className={ic} />,
                            permission: 'reports.view',
                            match: 'reports.*',
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
                            match: 'attendance*',
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

        case 'warehouse':
            return filter([
                {
                    items: [dashboard],
                },
                {
                    section: 'Gudang / Warehouse',
                    items: [
                        {
                            label: 'MRV Masuk',
                            routeName: 'warehouse.mrv.index',
                            icon: <Package className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/mrv*',
                        },
                        {
                            label: 'Barang Masuk',
                            routeName: 'warehouse.receipts.index',
                            icon: <ArrowDownToLine className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/receipts*',
                        },
                        {
                            label: 'Barang Keluar',
                            routeName: 'warehouse.inventory.movements',
                            icon: <ArrowUpFromLine className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/inventory/movements*',
                        },
                        {
                            label: 'Rekap Stok',
                            routeName: 'warehouse.inventory.index',
                            icon: <Boxes className={ic} />,
                            permission: 'warehouse.view',
                            match: 'warehouse/inventory',
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
                            label: 'Event',
                            routeName: 'events.index',
                            icon: <CalendarDays className={ic} />,
                            permission: 'events.view',
                            match: 'events.*',
                        },
                        {
                            label: 'Laporan per Event',
                            routeName: 'reports.by-event',
                            icon: <BookOpenCheck className={ic} />,
                            permission: 'reports.view',
                            match: 'reports.*',
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
                    ],
                },
            ]);

        default:
            return filter(adminGroups);
    }
}
