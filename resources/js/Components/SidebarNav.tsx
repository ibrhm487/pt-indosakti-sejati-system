import type { NavChild, NavGroup, NavItem } from '@/lib/nav';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    MouseEvent as ReactMouseEvent,
    useEffect,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';

type Flyout = {
    key: string;
    label: string;
    description?: string;
    children: NavChild[];
    top: number;
    left: number;
};

type Props = {
    groups: NavGroup[];
    collapsed: boolean;
    mobile?: boolean;
    currentUrl: string;
    onNavigate?: () => void;
};

function routeMatches(routeName?: string, match?: string): boolean {
    const pattern = match ?? routeName;

    if (!pattern) {
        return false;
    }

    return route().current(pattern);
}

function childIsActive(child: NavChild, currentUrl: string): boolean {
    if (child.routeParams?.brand_id) {
        return (
            currentUrl.split('?')[0].endsWith('/events') &&
            currentUrl.includes(`brand_id=${child.routeParams.brand_id}`)
        );
    }

    return routeMatches(child.routeName, child.match);
}

function itemIsActive(item: NavItem, currentUrl: string): boolean {
    return (
        routeMatches(item.routeName, item.match) ||
        (item.children?.some((child) => childIsActive(child, currentUrl)) ??
            false)
    );
}

export default function SidebarNav({
    groups,
    collapsed,
    mobile = false,
    currentUrl,
    onNavigate,
}: Props) {
    const [flyout, setFlyout] = useState<Flyout | null>(null);
    const [mobileOpen, setMobileOpen] = useState<string | null>(null);
    const closeTimer = useRef<number | null>(null);

    const cancelClose = () => {
        if (closeTimer.current !== null) {
            window.clearTimeout(closeTimer.current);
            closeTimer.current = null;
        }
    };

    const scheduleClose = () => {
        cancelClose();
        closeTimer.current = window.setTimeout(() => setFlyout(null), 160);
    };

    useEffect(() => () => cancelClose(), []);

    useEffect(() => {
        if (!flyout) {
            return;
        }

        const close = () => setFlyout(null);
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            }
        };

        window.addEventListener('scroll', close, true);
        window.addEventListener('resize', close);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('scroll', close, true);
            window.removeEventListener('resize', close);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [flyout]);

    const openAt = (element: HTMLElement, key: string, item: NavItem) => {
        if (!item.children?.length) {
            return;
        }

        cancelClose();

        const rect = element.getBoundingClientRect();
        const estimatedHeight = Math.min(item.children.length * 54 + 84, 420);
        const top = Math.max(
            12,
            Math.min(rect.top, window.innerHeight - estimatedHeight - 12),
        );

        setFlyout({
            key,
            label: item.label,
            description: item.description,
            children: item.children,
            top,
            left: rect.right + 10,
        });
    };

    const openOnHover = (
        event: ReactMouseEvent<HTMLElement>,
        key: string,
        item: NavItem,
    ) => {
        if (mobile) {
            return;
        }

        openAt(event.currentTarget, key, item);
    };

    const handleNavigate = () => {
        setFlyout(null);
        setMobileOpen(null);
        onNavigate?.();
    };

    const renderChild = (child: NavChild, key: string): React.ReactNode => {
        const active = childIsActive(child, currentUrl);

        return (
            <div key={key}>
                <Link
                    href={route(child.routeName, child.routeParams)}
                    onClick={handleNavigate}
                    aria-current={active ? 'page' : undefined}
                    className={cn(
                        'flex items-start gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30',
                        active
                            ? 'bg-brand-soft text-primary'
                            : 'text-muted hover:bg-page hover:text-text',
                    )}
                >
                    {child.icon && (
                        <span className="mt-0.5 shrink-0">{child.icon}</span>
                    )}
                    <span className="min-w-0">
                        <span className="block truncate font-medium">
                            {child.label}
                        </span>
                        {child.description && (
                            <span className="block text-xs text-muted">
                                {child.description}
                            </span>
                        )}
                    </span>
                </Link>

                {child.children?.length ? (
                    <div className="ms-4 mt-0.5 space-y-0.5 border-s border-border ps-2">
                        {child.children.map((nested, index) =>
                            renderChild(
                                nested,
                                `${key}-${nested.label}-${index}`,
                            ),
                        )}
                    </div>
                ) : null}
            </div>
        );
    };

    return (
        <>
            <div className={cn(collapsed ? 'space-y-3' : 'space-y-5')}>
                {groups.map((group, groupIndex) => (
                    <div key={group.section ?? `group-${groupIndex}`}>
                        {group.section && !collapsed && (
                            <p className="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">
                                {group.section}
                            </p>
                        )}
                        {collapsed && groupIndex > 0 && (
                            <div className="mx-auto mb-3 h-px w-8 bg-border" />
                        )}
                        <div className="space-y-1">
                            {group.items.map((item, itemIndex) => {
                                const key = `${groupIndex}-${itemIndex}-${item.label}`;
                                const active = itemIsActive(item, currentUrl);
                                const hasChildren =
                                    (item.children?.length ?? 0) > 0;
                                const expanded =
                                    (!mobile && flyout?.key === key) ||
                                    mobileOpen === key;

                                const itemClass = cn(
                                    'group relative flex items-center rounded-xl text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30',
                                    collapsed
                                        ? 'h-11 w-full justify-center px-0'
                                        : 'gap-3 px-3 py-2.5',
                                    active
                                        ? 'bg-brand-soft text-primary'
                                        : 'text-muted hover:bg-page hover:text-text',
                                );

                                const content = (
                                    <>
                                        {active && !collapsed && (
                                            <span className="absolute inset-y-2 left-0 w-1 rounded-full bg-brand" />
                                        )}
                                        <span
                                            className={cn(
                                                'flex shrink-0 items-center justify-center transition-colors',
                                                active
                                                    ? 'text-primary'
                                                    : 'text-muted group-hover:text-text',
                                            )}
                                        >
                                            {item.icon}
                                        </span>
                                        {!collapsed && (
                                            <>
                                                <span className="truncate">
                                                    {item.label}
                                                </span>
                                                {hasChildren && (
                                                    <ChevronRight
                                                        className={cn(
                                                            'ms-auto h-3.5 w-3.5 shrink-0 transition-transform',
                                                            expanded &&
                                                                'rotate-90',
                                                        )}
                                                    />
                                                )}
                                            </>
                                        )}
                                    </>
                                );

                                return (
                                    <div key={key}>
                                        {hasChildren && !item.routeName ? (
                                            <button
                                                type="button"
                                                onClick={(event) => {
                                                    if (mobile) {
                                                        setMobileOpen(
                                                            expanded ? null : key,
                                                        );

                                                        return;
                                                    }

                                                    openAt(
                                                        event.currentTarget,
                                                        key,
                                                        item,
                                                    );
                                                }}
                                                onMouseEnter={(event) =>
                                                    openOnHover(event, key, item)
                                                }
                                                onMouseLeave={scheduleClose}
                                                onFocus={(event) => {
                                                    if (!mobile) {
                                                        openAt(
                                                            event.currentTarget,
                                                            key,
                                                            item,
                                                        );
                                                    }
                                                }}
                                                onBlur={scheduleClose}
                                                aria-haspopup="true"
                                                aria-expanded={expanded}
                                                title={
                                                    collapsed
                                                        ? item.label
                                                        : undefined
                                                }
                                                className={itemClass}
                                            >
                                                {content}
                                            </button>
                                        ) : (
                                            <Link
                                                href={route(
                                                    item.routeName ??
                                                        'dashboard',
                                                )}
                                                onClick={handleNavigate}
                                                onMouseEnter={(event) =>
                                                    openOnHover(event, key, item)
                                                }
                                                onMouseLeave={scheduleClose}
                                                onFocus={(event) => {
                                                    if (!mobile) {
                                                        openAt(
                                                            event.currentTarget,
                                                            key,
                                                            item,
                                                        );
                                                    }
                                                }}
                                                onBlur={scheduleClose}
                                                aria-current={
                                                    active ? 'page' : undefined
                                                }
                                                title={
                                                    collapsed
                                                        ? item.label
                                                        : undefined
                                                }
                                                className={itemClass}
                                            >
                                                {content}
                                            </Link>
                                        )}

                                        {mobile &&
                                            hasChildren &&
                                            mobileOpen === key && (
                                                <div className="mt-1 space-y-0.5 border-s border-border ps-4">
                                                    {item.children?.map(
                                                        (child, index) =>
                                                            renderChild(
                                                                child,
                                                                `${key}-${child.label}-${index}`,
                                                            ),
                                                    )}
                                                </div>
                                            )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>

            {!mobile &&
                flyout &&
                typeof document !== 'undefined' &&
                createPortal(
                    <div
                        style={{
                            position: 'fixed',
                            top: flyout.top,
                            left: flyout.left,
                            zIndex: 60,
                        }}
                        onMouseEnter={cancelClose}
                        onMouseLeave={scheduleClose}
                        className="w-72 overflow-hidden rounded-xl border border-border bg-surface shadow-xl shadow-slate-900/10"
                    >
                        <div className="border-b border-border bg-page/60 px-4 py-3">
                            <p className="text-sm font-semibold text-text">
                                {flyout.label}
                            </p>
                            {flyout.description && (
                                <p className="mt-0.5 text-xs text-muted">
                                    {flyout.description}
                                </p>
                            )}
                        </div>
                        <div className="max-h-[70vh] space-y-0.5 overflow-y-auto p-2">
                            {flyout.children.map((child, index) =>
                                renderChild(
                                    child,
                                    `${flyout.key}-${child.label}-${index}`,
                                ),
                            )}
                        </div>
                    </div>,
                    document.body,
                )}
        </>
    );
}
