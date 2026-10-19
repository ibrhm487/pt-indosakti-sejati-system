import { cn } from '@/lib/utils';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { ChevronRight, X } from 'lucide-react';
import {
    ButtonHTMLAttributes,
    HTMLAttributes,
    InputHTMLAttributes,
    LabelHTMLAttributes,
    PropsWithChildren,
    ReactNode,
    SelectHTMLAttributes,
    TdHTMLAttributes,
    TextareaHTMLAttributes,
    ThHTMLAttributes,
    forwardRef,
} from 'react';
import { Link } from '@inertiajs/react';

/* ------------------------------------------------------------------ Button */
const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'bg-primary text-white hover:bg-primary-hover',
                primary: 'bg-primary text-white hover:bg-primary-hover',
                destructive: 'bg-danger text-white hover:bg-danger/90',
                outline:
                    'border border-border bg-surface text-text hover:bg-page',
                ghost: 'text-muted hover:bg-page hover:text-text',
                success: 'bg-success text-white hover:bg-success/90',
                accent: 'bg-accent text-primary hover:bg-accent-hover',
                subtle: 'bg-page text-text hover:bg-border/50',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-8 rounded-lg px-3 text-xs',
                lg: 'h-11 rounded-lg px-6',
                icon: 'h-10 w-10',
            },
        },
        defaultVariants: { variant: 'primary', size: 'default' },
    },
);

export interface ButtonProps
    extends ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, asChild = false, ...props }, ref) => {
        const Comp = asChild ? Slot : 'button';
        return (
            <Comp
                ref={ref}
                className={cn(buttonVariants({ variant, size }), className)}
                {...props}
            />
        );
    },
);
Button.displayName = 'Button';

/* -------------------------------------------------------------------- Card */
export function Card({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-surface shadow-sm',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('border-b border-border px-5 py-4', className)}
            {...props}
        />
    );
}

export function CardTitle({
    className,
    ...props
}: HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h3 className={cn('text-sm font-semibold text-text', className)} {...props} />
    );
}

export function CardContent({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('p-5', className)} {...props} />;
}

/* ------------------------------------------------------------------- Badge */
const badgeVariants = cva(
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
    {
        variants: {
            variant: {
                default: 'bg-page text-muted',
                success: 'bg-success/10 text-success',
                warning: 'bg-warning/10 text-warning',
                danger: 'bg-danger/10 text-danger',
                info: 'bg-info/10 text-info',
                accent: 'bg-accent/15 text-primary',
                outline: 'border border-border text-muted',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

export interface BadgeProps
    extends HTMLAttributes<HTMLSpanElement>,
        VariantProps<typeof badgeVariants> {}

export function Badge({ className, variant, ...props }: BadgeProps) {
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
    // generic
    draft: 'default',
    pending: 'warning',
    submitted: 'info',
    approved: 'success',
    verified: 'success',
    completed: 'success',
    complete: 'success',
    ready: 'success',
    issued: 'info',
    returned: 'accent',
    revision: 'warning',
    rejected: 'danger',
    cancelled: 'danger',
    invalid: 'danger',
    blocked: 'danger',
    processing: 'info',
    confirmed: 'info',
    preparing: 'warning',
    dispatched: 'accent',
    not_required: 'outline',
    planning: 'info',
    permission: 'info',
    team_assignment: 'info',
    warehouse_preparation: 'info',
    kitchen_preparation: 'warning',
    ongoing: 'info',
    in_progress: 'info',
    planned: 'default',
    assigned: 'info',
    processed: 'success',
    // FJP legacy status vocabulary (fjp.status)
    plan: 'info',
    confirm: 'warning',
    cancel: 'danger',
    reschedule: 'warning',
    done: 'success',
};

export function StatusBadge({
    status,
    label,
}: {
    status: string;
    label?: string;
}) {
    return (
        <Badge variant={STATUS_VARIANT[status] ?? 'default'}>
            {label ?? status}
        </Badge>
    );
}

/* ------------------------------------------------------------------ Inputs */
export const Input = forwardRef<
    HTMLInputElement,
    InputHTMLAttributes<HTMLInputElement>
>(({ className, type = 'text', ...props }, ref) => (
    <input
        ref={ref}
        type={type}
        className={cn(
            'flex h-10 w-full rounded-lg border border-border bg-surface px-3 py-1 text-sm text-text shadow-sm transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50',
            className,
        )}
        {...props}
    />
));
Input.displayName = 'Input';

export const Textarea = forwardRef<
    HTMLTextAreaElement,
    TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className, ...props }, ref) => (
    <textarea
        ref={ref}
        className={cn(
            'flex min-h-[80px] w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text shadow-sm placeholder:text-muted focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50',
            className,
        )}
        {...props}
    />
));
Textarea.displayName = 'Textarea';

export const Select = forwardRef<
    HTMLSelectElement,
    SelectHTMLAttributes<HTMLSelectElement>
>(({ className, children, ...props }, ref) => (
    <select
        ref={ref}
        className={cn(
            'flex h-10 w-full rounded-lg border border-border bg-surface px-3 py-1 text-sm text-text shadow-sm focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50',
            className,
        )}
        {...props}
    >
        {children}
    </select>
));
Select.displayName = 'Select';

export function Label({
    className,
    ...props
}: LabelHTMLAttributes<HTMLLabelElement>) {
    return (
        <label
            className={cn('mb-1 block text-sm font-medium text-text', className)}
            {...props}
        />
    );
}

export function Field({
    label,
    error,
    required,
    children,
    className,
}: PropsWithChildren<{
    label?: string;
    error?: string;
    required?: boolean;
    className?: string;
}>) {
    return (
        <div className={cn('mb-4', className)}>
            {label && (
                <Label>
                    {label}
                    {required && (
                        <span className="ml-0.5 text-danger" aria-hidden>
                            *
                        </span>
                    )}
                </Label>
            )}
            {children}
            {error && (
                <p role="alert" className="mt-1 text-xs text-danger">
                    {error}
                </p>
            )}
        </div>
    );
}

/* ------------------------------------------------------------------- Table */
export function Table({
    className,
    ...props
}: HTMLAttributes<HTMLTableElement>) {
    return (
        <div className="w-full overflow-x-auto">
            <table
                className={cn('w-full caption-bottom text-sm', className)}
                {...props}
            />
        </div>
    );
}

export function THead({
    className,
    ...props
}: HTMLAttributes<HTMLTableSectionElement>) {
    return (
        <thead
            className={cn('border-b border-border bg-page', className)}
            {...props}
        />
    );
}

export function TBody({
    className,
    ...props
}: HTMLAttributes<HTMLTableSectionElement>) {
    return <tbody className={cn('divide-y divide-border/70', className)} {...props} />;
}

export function TR({
    className,
    ...props
}: HTMLAttributes<HTMLTableRowElement>) {
    return <tr className={cn('hover:bg-page/60', className)} {...props} />;
}

export function TH({
    className,
    ...props
}: ThHTMLAttributes<HTMLTableCellElement>) {
    return (
        <th
            className={cn(
                'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted',
                className,
            )}
            {...props}
        />
    );
}

export function TD({
    className,
    ...props
}: TdHTMLAttributes<HTMLTableCellElement>) {
    return (
        <td
            className={cn('px-4 py-3 align-middle text-text', className)}
            {...props}
        />
    );
}

/* ---------------------------------------------------------------- StatCard */
export function StatCard({
    label,
    value,
    icon,
    hint,
    tone = 'default',
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
    hint?: ReactNode;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info' | 'accent';
}) {
    const tones: Record<string, string> = {
        default: 'bg-page text-muted',
        success: 'bg-success/10 text-success',
        warning: 'bg-warning/10 text-warning',
        danger: 'bg-danger/10 text-danger',
        info: 'bg-info/10 text-info',
        accent: 'bg-accent/15 text-primary',
    };
    return (
        <Card className="p-5 transition-shadow hover:shadow-md">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted">
                        {label}
                    </p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-text">
                        {value}
                    </p>
                    {hint && <p className="mt-1 text-xs text-muted">{hint}</p>}
                </div>
                {icon && (
                    <span
                        className={cn(
                            'flex h-11 w-11 items-center justify-center rounded-xl',
                            tones[tone],
                        )}
                    >
                        {icon}
                    </span>
                )}
            </div>
        </Card>
    );
}

/* ------------------------------------------------------------------ Skeleton */
export function Skeleton({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            aria-hidden
            className={cn('animate-pulse rounded-lg bg-border/60', className)}
            {...props}
        />
    );
}

export function TableSkeleton({ rows = 5, cols = 5 }: { rows?: number; cols?: number }) {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="flex gap-3">
                    {Array.from({ length: cols }).map((__, j) => (
                        <Skeleton
                            key={j}
                            className={cn('h-4', j === 0 ? 'w-1/4' : 'w-1/6')}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
}

/* ------------------------------------------------------------------- Chart */
export function ChartCard({
    title,
    subtitle,
    children,
    className,
}: PropsWithChildren<{
    title: string;
    subtitle?: string;
    className?: string;
}>) {
    return (
        <Card className={cn('flex flex-col', className)}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {subtitle && <p className="mt-0.5 text-xs text-muted">{subtitle}</p>}
            </CardHeader>
            <CardContent className="flex-1">{children}</CardContent>
        </Card>
    );
}

/* ------------------------------------------------------------ Form section */
export function FormSection({
    title,
    description,
    children,
    className,
}: PropsWithChildren<{
    title?: string;
    description?: string;
    className?: string;
}>) {
    return (
        <section className={cn('border-b border-border px-5 py-5 last:border-b-0', className)}>
            {title && <h3 className="text-sm font-semibold text-text">{title}</h3>}
            {description && <p className="mt-0.5 text-xs text-muted">{description}</p>}
            <div className="mt-4">{children}</div>
        </section>
    );
}

/* ------------------------------------------------------------------- Modal */
const modalSizes = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
    '2xl': 'sm:max-w-6xl',
} as const;

export function Modal({
    open,
    onClose,
    title,
    description,
    children,
    footer,
    size = 'md',
}: PropsWithChildren<{
    open: boolean;
    onClose: () => void;
    title?: string;
    description?: ReactNode;
    footer?: ReactNode;
    size?: keyof typeof modalSizes;
}>) {
    return (
        <DialogPrimitive.Root open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-text/40 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    className={cn(
                        'fixed left-1/2 top-1/2 z-50 flex max-h-[88vh] w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-xl border border-border bg-surface shadow-2xl shadow-slate-900/10 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-bottom-2 data-[state=open]:slide-in-from-bottom-2',
                        modalSizes[size],
                    )}
                >
                    {(title || description) && (
                        <div className="flex items-start justify-between gap-4 border-b border-border px-6 py-5">
                            <div className="min-w-0">
                                {title && (
                                    <DialogPrimitive.Title className="text-base font-semibold tracking-tight text-text">
                                        {title}
                                    </DialogPrimitive.Title>
                                )}
                                {description && (
                                    <DialogPrimitive.Description className="mt-1 text-sm text-muted">
                                        {description}
                                    </DialogPrimitive.Description>
                                )}
                            </div>
                            <DialogPrimitive.Close
                                aria-label="Tutup"
                                className="-mr-1.5 -mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-page hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                            >
                                <X className="h-4 w-4" />
                            </DialogPrimitive.Close>
                        </div>
                    )}
                    <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>
                    {footer && (
                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border bg-page/70 px-6 py-4">
                            {footer}
                        </div>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

/* -------------------------------------------------------------- Breadcrumb */
export type BreadcrumbItem = { label: string; href?: string };

export function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
    if (items.length === 0) return null;
    return (
        <nav aria-label="Breadcrumb" className="mb-2">
            <ol className="flex flex-wrap items-center gap-1 text-xs text-muted">
                {items.map((item, i) => (
                    <li key={i} className="flex items-center gap-1">
                        {i > 0 && <ChevronRight className="h-3 w-3" aria-hidden />}
                        {item.href ? (
                            <Link
                                href={item.href}
                                className="transition-colors hover:text-primary"
                            >
                                {item.label}
                            </Link>
                        ) : (
                            <span aria-current="page" className="font-medium text-text">
                                {item.label}
                            </span>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
