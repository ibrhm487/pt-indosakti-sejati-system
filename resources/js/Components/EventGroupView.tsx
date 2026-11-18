import { Badge, Button } from '@/Components/ui';
import { formatDate } from '@/lib/utils';
import { ReactNode, useState } from 'react';

export type EventGroupItem = Record<string, any>;

export type EventTypeGroup = {
    key: string;
    event_type_id: number | null;
    event_type_name: string;
    requires_school: boolean;
    count: number;
    dates: {
        key: string;
        date: string | null;
        count: number;
        events: EventGroupItem[];
    }[];
};

export type EventBrandGroup = {
    key: string;
    brand_id: number | null;
    brand_name: string;
    count: number;
    event_types: EventTypeGroup[];
};

export function EventGroupView({
    groups,
    renderTable,
    empty,
    compact = false,
}: {
    groups: EventBrandGroup[];
    renderTable: (rows: EventGroupItem[], type: EventTypeGroup) => ReactNode;
    empty?: ReactNode;
    compact?: boolean;
}) {
    const [openAll, setOpenAll] = useState(true);

    if (groups.length === 0) {
        return <>{empty}</>;
    }

    return (
        <div className="space-y-4">
            <div className="flex justify-end gap-2">
                <Button type="button" size="sm" variant="outline" onClick={() => setOpenAll(true)}>
                    Buka semua
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={() => setOpenAll(false)}>
                    Tutup semua
                </Button>
            </div>
            {groups.map((brand) => (
                <details
                    key={brand.key}
                    open={openAll}
                    className={compact ? 'border-b border-border last:border-b-0' : 'overflow-hidden rounded-xl border border-border bg-surface shadow-sm'}
                >
                    <summary className={`flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-3 ${compact ? '' : 'border-b border-border bg-page/60 px-4'}`}>
                        <span className="flex min-w-0 items-center gap-2">
                            <span className="text-xs font-semibold uppercase tracking-wide text-muted">
                                Brand
                            </span>
                            <span className="truncate text-sm font-semibold text-text">
                                {brand.brand_name}
                            </span>
                        </span>
                        <Badge variant="outline">{brand.count} event</Badge>
                    </summary>

                    <div className={compact ? 'pb-3 ps-2' : 'space-y-4 p-3 sm:p-4'}>
                        {brand.event_types.map((type) => (
                            <details
                                key={type.key}
                                open={openAll}
                                className={compact ? 'border-s-2 border-border/70' : 'overflow-hidden rounded-lg border border-border/70'}
                            >
                                <summary className={`flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2.5 ${compact ? '' : 'bg-page/40'}`}>
                                    <span className="flex min-w-0 items-center gap-2">
                                        <span className="truncate text-sm font-medium text-text">
                                            {type.event_type_name}
                                        </span>
                                        <Badge variant={type.requires_school ? 'info' : 'warning'}>
                                            {type.requires_school ? 'Sekolah' : 'Venue'}
                                        </Badge>
                                    </span>
                                    <Badge variant="outline">{type.count} event</Badge>
                                </summary>

                                <div className={compact ? 'divide-y divide-border/70' : 'divide-y divide-border'}>
                                    {type.dates.map((date) => (
                                        <div key={date.key}>
                                            <div className="flex items-center justify-between gap-2 bg-page/20 px-3 py-2 text-xs font-medium text-muted">
                                                <span>
                                                    {date.date
                                                        ? formatDate(date.date)
                                                        : 'Tanpa tanggal'}
                                                </span>
                                                <span>{date.count} event</span>
                                            </div>
                                            <div className="overflow-x-auto">
                                                {renderTable(date.events, type)}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </details>
                        ))}
                    </div>
                </details>
            ))}
        </div>
    );
}
