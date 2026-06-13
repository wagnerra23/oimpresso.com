import { Check } from 'lucide-react'
import { PIPELINE_STEPS, type OsStage } from '@/Types/os'
import { cn } from '@/Lib/utils'

interface Props {
  currentStage: OsStage
}

export function OsPipeline({ currentStage }: Props) {
  const currentIdx = PIPELINE_STEPS.findIndex((s) => s.id === currentStage)

  return (
    <div className="px-6 py-5 border-b border-border">
      <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-4">
        Progresso do pedido
      </p>

      <div className="flex items-start">
        {PIPELINE_STEPS.map((step, i) => {
          const isDone = i < currentIdx
          const isActive = i === currentIdx

          return (
            <div key={step.id} className="flex flex-col items-center flex-1 relative">
              {i < PIPELINE_STEPS.length - 1 && (
                <div
                  className={cn(
                    'absolute top-[13px] left-1/2 right-[-50%] h-0.5 z-0',
                    isDone
                      ? 'bg-success'
                      : isActive
                      ? 'bg-gradient-to-r from-success to-border'
                      : 'bg-border',
                  )}
                />
              )}

              <div
                className={cn(
                  'relative z-10 flex items-center justify-center w-7 h-7 rounded-full border-2 transition-all',
                  isDone
                    ? 'bg-success border-success'
                    : isActive
                    ? 'bg-primary border-primary ring-4 ring-primary/15'
                    : 'bg-background border-border',
                )}
              >
                {isDone ? (
                  <Check className="w-3 h-3 text-white" strokeWidth={2.5} />
                ) : isActive ? (
                  <span className="w-2.5 h-2.5 rounded-full bg-white" />
                ) : null}
              </div>

              <span
                className={cn(
                  'mt-2 text-[10px] font-medium text-center leading-tight px-0.5',
                  isDone
                    ? 'text-success'
                    : isActive
                    ? 'text-primary font-semibold'
                    : 'text-muted-foreground',
                )}
              >
                {step.label}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
