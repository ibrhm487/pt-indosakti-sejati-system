import { cn } from '@/lib/utils';

const STEPS = ['Permitter', 'PIC', 'Tim & Gudang', 'Kitchen'];

/**
 * The operational workflow legend shown in the sidebar, mirroring the
 * reference design: Permitter → PIC → Tim & Gudang → Kitchen → Admin.
 */
export default function WorkflowLegend({ className }: { className?: string }) {
    return (
        <div className={cn('rounded-lg border border-slate-200 bg-slate-50 p-3', className)}>
            <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                Alur Kerja
            </p>
            <div className="flex flex-wrap items-center gap-y-1">
                {STEPS.map((step, index) => (
                    <span key={step} className="flex items-center">
                        <span className="rounded bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-600 ring-1 ring-slate-200">
                            {step}
                        </span>
                        {index < STEPS.length - 1 && (
                            <span className="px-1 text-[10px] text-slate-300">→</span>
                        )}
                    </span>
                ))}
            </div>
        </div>
    );
}
