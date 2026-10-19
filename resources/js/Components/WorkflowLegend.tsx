import { cn } from '@/lib/utils';

const STEPS = ['Permitter', 'PIC', 'Tim & Gudang', 'Kitchen'];

/**
 * The operational workflow legend shown in the sidebar, mirroring the
 * reference design: Permitter → PIC → Tim & Gudang → Kitchen → Admin.
 */
export default function WorkflowLegend({ className }: { className?: string }) {
    return (
        <div className={cn('rounded-lg border border-border bg-page p-3', className)}>
            <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-muted">
                Alur Kerja
            </p>
            <div className="flex flex-wrap items-center gap-y-1">
                {STEPS.map((step, index) => (
                    <span key={step} className="flex items-center">
                        <span className="rounded bg-surface px-1.5 py-0.5 text-[10px] font-medium text-text ring-1 ring-border">
                            {step}
                        </span>
                        {index < STEPS.length - 1 && (
                            <span className="px-1 text-[10px] text-muted/60">→</span>
                        )}
                    </span>
                ))}
            </div>
        </div>
    );
}
