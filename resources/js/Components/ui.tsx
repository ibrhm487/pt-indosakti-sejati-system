import { cn } from '@/lib/utils';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { X } from 'lucide-react';
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

/* ------------------------------------------------------------------ Button */
const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'bg-slate-900 text-white hover:bg-slate-800',
                primary: 'bg-indigo-600 text-white hover:bg-indigo-500',
                destructive: 'bg-red-600 text-white hover:bg-red-500',
                outline:
                    'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                ghost: 'text-slate-600 hover:bg-slate-100',
                success: 'bg-emerald-600 text-white hover:bg-emerald-500',
                subtle: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
            },
            size: {
                default: 'h-9 px-4 py-2',
                sm: 'h-8 rounded-lg px-3 text-xs',
                lg: 'h-11 rounded-lg px-6',
                icon: 'h-9 w-9',
            },
        },
        defaultVariants: { variant: 'default', size: 'default' },
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
                'rounded-xl border border-slate-200/80 bg-white shadow-sm',
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
            className={cn('border-b border-slate-100 px-5 py-4', className)}
            {...props}
        />
    );
}

export function CardTitle({
    className,
    ...props
}: HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h3
            className={cn(
                'text-sm font-semibold text-slate-800',
                className,
            )}
            {...props}
        />
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
                default: 'bg-slate-100 text-slate-700',
                success: 'bg-emerald-100 text-emerald-700',
                warning: 'bg-amber-100 text-amber-700',
                danger: 'bg-red-100 text-red-700',
                info: 'bg-sky-100 text-sky-700',
                purple: 'bg-violet-100 text-violet-700',
                outline: 'border border-slate-300 text-slate-600',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

export interface BadgeProps
    extends HTMLAttributes<HTMLSpanElement>,
        VariantProps<typeof badgeVariants> {}

export function Badge({ className, variant, ...props }: BadgeProps) {
    return (
        <span className={cn(badgeVariants({ variant }), className)} {...props} />
    );
}

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
    // generic
    draft: 'default',
    pending: 'warning',
    submitted: 'info',
    approved: 'success',
    verified: 'success',
    completed: 'success',
    ready: 'success',
    issued: 'info',
    returned: 'purple',
    revision: 'warning',
    rejected: 'danger',
    cancelled: 'danger',
    invalid: 'danger',
    blocked: 'danger',
    processing: 'info',
    confirmed: 'info',
    preparing: 'warning',
    dispatched: 'purple',
    not_required: 'outline',
    planning: 'info',
    permission: 'info',
    team_assignment: 'info',
    warehouse_preparation: 'info',
    kitchen_preparation: 'warning',
    ongoing: 'purple',
    in_progress: 'info',
    planned: 'default',
    assigned: 'info',
    processed: 'success',
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
            'flex h-9 w-full rounded-md border border-slate-300 bg-white px-3 py-1 text-sm text-slate-800 shadow-sm transition-colors placeholder:text-slate-400 focus-visible:border-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-50',
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
            'flex min-h-[80px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus-visible:border-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200 disabled:opacity-50',
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
            'flex h-9 w-full rounded-md border border-slate-300 bg-white px-3 py-1 text-sm text-slate-800 shadow-sm focus-visible:border-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200 disabled:opacity-50',
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
            className={cn(
                'mb-1 block text-sm font-medium text-slate-700',
                className,
            )}
            {...props}
        />
    );
}

export function Field({
    label,
    error,
    children,
    className,
}: PropsWithChildren<{
    label?: string;
    error?: string;
    className?: string;
}>) {
    return (
        <div className={cn('mb-4', className)}>
            {label && <Label>{label}</Label>}
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
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
            className={cn('border-b border-slate-200 bg-slate-50', className)}
            {...props}
        />
    );
}

export function TBody({
    className,
    ...props
}: HTMLAttributes<HTMLTableSectionElement>) {
    return (
        <tbody
            className={cn('divide-y divide-slate-100', className)}
            {...props}
        />
    );
}

export function TR({
    className,
    ...props
}: HTMLAttributes<HTMLTableRowElement>) {
    return (
        <tr className={cn('hover:bg-slate-50/60', className)} {...props} />
    );
}

export function TH({
    className,
    ...props
}: ThHTMLAttributes<HTMLTableCellElement>) {
    return (
        <th
            className={cn(
                'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500',
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
            className={cn('px-4 py-3 align-middle text-slate-700', className)}
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
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
}) {
    const tones: Record<string, string> = {
        default: 'bg-slate-100 text-slate-600',
        success: 'bg-emerald-100 text-emerald-600',
        warning: 'bg-amber-100 text-amber-600',
        danger: 'bg-red-100 text-red-600',
        info: 'bg-sky-100 text-sky-600',
    };
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                        {label}
                    </p>
                    <p className="mt-2 text-2xl font-semibold text-slate-900">
                        {value}
                    </p>
                    {hint && (
                        <p className="mt-1 text-xs text-slate-400">{hint}</p>
                    )}
                </div>
                {icon && (
                    <span
                        className={cn(
                            'flex h-10 w-10 items-center justify-center rounded-lg',
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
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-slate-950/40 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    className={cn(
                        'fixed left-1/2 top-1/2 z-50 flex max-h-[88vh] w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl shadow-slate-900/10 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-bottom-2 data-[state=open]:slide-in-from-bottom-2',
                        modalSizes[size],
                    )}
                >
                    {(title || description) && (
                        <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                            <div className="min-w-0">
                                {title && (
                                    <DialogPrimitive.Title className="text-base font-semibold tracking-tight text-slate-900">
                                        {title}
                                    </DialogPrimitive.Title>
                                )}
                                {description && (
                                    <DialogPrimitive.Description className="mt-1 text-sm text-slate-500">
                                        {description}
                                    </DialogPrimitive.Description>
                                )}
                            </div>
                            <DialogPrimitive.Close
                                aria-label="Tutup"
                                className="-mr-1.5 -mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                            >
                                <X className="h-4 w-4" />
                            </DialogPrimitive.Close>
                        </div>
                    )}
                    <div className="flex-1 overflow-y-auto px-6 py-5">
                        {children}
                    </div>
                    {footer && (
                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                            {footer}
                        </div>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
