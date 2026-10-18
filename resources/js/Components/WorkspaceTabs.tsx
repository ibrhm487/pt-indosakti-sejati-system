import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

export type WorkspaceTab = {
    key: string;
    label: string;
    icon?: ReactNode;
};

/**
 * Tab bar for a role workspace. Tabs are driven by the `?tab=` query string
 * so the whole workspace lives on a single route, matching the reference.
 */
export default function WorkspaceTabs({
    routeName,
    tabs,
    active,
}: {
    routeName: string;
    tabs: WorkspaceTab[];
    active: string;
}) {
    return (
        <div className="mb-5 flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            {tabs.map((tab) => {
                const isActive = tab.key === active;
                return (
                    <Link
                        key={tab.key}
                        href={route(routeName, { tab: tab.key })}
                        preserveScroll
                        preserveState
                        className={cn(
                            'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-slate-600 hover:bg-slate-100',
                        )}
                    >
                        {tab.icon}
                        {tab.label}
                    </Link>
                );
            })}
        </div>
    );
}
