import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Info, Search, XCircle } from 'lucide-react';
import {
    FormEventHandler,
    HTMLAttributes,
    InputHTMLAttributes,
    PropsWithChildren,
    ReactNode,
    useState,
} from 'react';
import { Button, Card, Input, Modal, Breadcrumb, type BreadcrumbItem } from './ui';

export function PageHeader({
    title,
    subtitle,
    breadcrumbs,
    children,
}: PropsWithChildren<{
    title: string;
    subtitle?: string;
    breadcrumbs?: BreadcrumbItem[];
}>) {
    return (
        <div className="mb-6">
            {breadcrumbs && breadcrumbs.length > 0 && (
                <Breadcrumb items={breadcrumbs} />
            )}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-3">
                    <span className="mt-1.5 h-9 w-1.5 shrink-0 rounded-full bg-primary" />
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text">
                            {title}
                        </h1>
                        {subtitle && (
                            <p className="mt-1 text-sm text-muted">{subtitle}</p>
                        )}
                    </div>
                </div>
                {children && (
                    <div className="flex flex-wrap items-center gap-2">
                        {children}
                    </div>
                )}
            </div>
        </div>
    );
}

type Paginator = {
    data?: unknown[];
    links?: { url: string | null; label: string; active: boolean }[];
    current_page?: number;
    last_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
};

export function Pagination({ paginator }: { paginator: Paginator }) {
    const links = paginator?.links ?? [];
    if (links.length <= 3) return null;

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-border px-4 py-3 sm:flex-row">
            <p className="text-xs text-muted">
                {paginator.total
                    ? `Menampilkan ${paginator.from ?? 0}–${paginator.to ?? 0} dari ${paginator.total}`
                    : ''}
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {links.map((link, index) => (
                    <Link
                        key={index}
                        href={link.url ?? '#'}
                        preserveScroll
                        className={cn(
                            'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs',
                            link.active
                                ? 'border-primary bg-primary text-white'
                                : 'border-border bg-surface text-muted hover:bg-page hover:text-text',
                            !link.url && 'pointer-events-none opacity-40',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}

export function FlashMessages() {
    const { flash } = usePage().props;
    const [dismissed, setDismissed] = useState<string[]>([]);

    const messages: ReactNode[] = [];

    if (flash?.success && !dismissed.includes('success')) {
        messages.push(
            <Alert
                key="success"
                tone="success"
                onClose={() => setDismissed((d) => [...d, 'success'])}
            >
                {flash.success}
            </Alert>,
        );
    }

    if (flash?.error && !dismissed.includes('error')) {
        messages.push(
            <Alert
                key="error"
                tone="error"
                onClose={() => setDismissed((d) => [...d, 'error'])}
            >
                {flash.error}
            </Alert>,
        );
    }

    if (messages.length === 0) return null;

    return <div className="mb-4 space-y-2">{messages}</div>;
}

export function Alert({
    tone = 'info',
    children,
    onClose,
}: PropsWithChildren<{ tone?: 'info' | 'success' | 'error'; onClose?: () => void }>) {
    const tones = {
        info: 'border-info/20 bg-info/10 text-info',
        success: 'border-success/20 bg-success/10 text-success',
        error: 'border-danger/20 bg-danger/10 text-danger',
    };
    const icons = {
        info: <Info className="h-4 w-4" />,
        success: <CheckCircle2 className="h-4 w-4" />,
        error: <XCircle className="h-4 w-4" />,
    };
    return (
        <div
            className={cn(
                'flex items-start gap-2 rounded-md border px-4 py-3 text-sm',
                tones[tone],
            )}
        >
            <span className="mt-0.5">{icons[tone]}</span>
            <span className="flex-1">{children}</span>
            {onClose && (
                <button
                    type="button"
                    onClick={onClose}
                    className="text-xs font-medium uppercase opacity-60 hover:opacity-100"
                >
                    tutup
                </button>
            )}
        </div>
    );
}

export function EmptyState({
    title = 'Belum ada data',
    description,
    children,
}: PropsWithChildren<{ title?: string; description?: string }>) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <span className="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-page text-muted/60">
                <Search className="h-6 w-6" />
            </span>
            <p className="text-sm font-medium text-text">{title}</p>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-muted">
                    {description}
                </p>
            )}
            {children && <div className="mt-4">{children}</div>}
        </div>
    );
}

export function CardTable({ children }: PropsWithChildren) {
    return <Card className="overflow-hidden">{children}</Card>;
}

export function ConfirmButton({
    onConfirm,
    children,
    confirmLabel = 'Ya, lanjutkan',
    title = 'Konfirmasi',
    description,
    variant = 'destructive',
    size = 'sm',
    className,
}: {
    onConfirm: () => void;
    children: ReactNode;
    confirmLabel?: string;
    title?: string;
    description?: string;
    variant?: 'destructive' | 'default' | 'primary' | 'success' | 'ghost' | 'outline';
    size?: 'sm' | 'default' | 'icon';
    className?: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant={variant}
                size={size}
                className={className}
                onClick={() => setOpen(true)}
            >
                {children}
            </Button>
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={title}
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => {
                                setOpen(false);
                                onConfirm();
                            }}
                        >
                            {confirmLabel}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-muted">
                    {description ?? 'Tindakan ini tidak dapat dibatalkan.'}
                </p>
            </Modal>
        </>
    );
}

export function SearchInput({
    className,
    containerClassName,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & {
    containerClassName?: string;
}) {
    return (
        <div className={cn('relative flex-1', containerClassName)}>
            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
            <Input
                className={cn('h-11 rounded-xl pl-10', className)}
                {...props}
            />
        </div>
    );
}

export function FilterCard({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <Card
            className={cn('mb-4 border-border p-4 shadow-sm', className)}
            {...props}
        />
    );
}

export type TabItem = {
    key: string;
    label: string;
    icon?: ReactNode;
    count?: number;
};

export function Tabs({
    items,
    active,
    onChange,
}: {
    items: TabItem[];
    active: string;
    onChange: (key: string) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1 rounded-xl border border-border bg-page p-1">
            {items.map((item) => {
                const isActive = item.key === active;
                return (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => onChange(item.key)}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-surface text-primary shadow-sm ring-1 ring-border'
                                : 'text-muted hover:text-text',
                        )}
                    >
                        {item.icon}
                        {item.label}
                        {typeof item.count === 'number' && (
                            <span
                                className={cn(
                                    'ml-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-medium',
                                    isActive
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-border/60 text-muted',
                                )}
                            >
                                {item.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

export function ModalForm({
    open,
    onClose,
    title,
    description,
    children,
    onSubmit,
    submitLabel = 'Simpan',
    cancelLabel = 'Batal',
    submitDisabled,
    submitVariant = 'primary',
    size = 'md',
}: PropsWithChildren<{
    open: boolean;
    onClose: () => void;
    title: string;
    description?: ReactNode;
    onSubmit: FormEventHandler<HTMLFormElement>;
    submitLabel?: string;
    cancelLabel?: string;
    submitDisabled?: boolean;
    submitVariant?: 'primary' | 'default' | 'destructive' | 'outline' | 'ghost' | 'success';
    size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
}>) {
    const id = 'modal-form';
    return (
        <Modal
            open={open}
            onClose={onClose}
            size={size}
            title={title}
            description={description}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onClose}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="submit"
                        form={id}
                        variant={submitVariant}
                        size="sm"
                        disabled={submitDisabled}
                    >
                        {submitLabel}
                    </Button>
                </>
            }
        >
            <form id={id} onSubmit={onSubmit}>
                {children}
            </form>
        </Modal>
    );
}
