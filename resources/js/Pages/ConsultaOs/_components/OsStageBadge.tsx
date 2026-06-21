import { Badge } from '@/Components/ui/badge'
import { STAGE_META, type OsStage } from '@/Types/os'
import { cn } from '@/Lib/utils'

interface Props {
  stage: OsStage
  className?: string
}

const variantClasses: Record<string, string> = {
  secondary:   'bg-muted text-muted-foreground border-border',
  warning:     'bg-amber-50 text-amber-700 border-amber-200',
  info:        'bg-violet-50 text-violet-700 border-violet-200',
  cyan:        'bg-cyan-50 text-cyan-700 border-cyan-200',
  success:     'bg-success-soft text-success-fg border-success/20',
  destructive: 'bg-destructive-soft text-destructive-fg border-destructive/20',
}

export function OsStageBadge({ stage, className }: Props) {
  const meta = STAGE_META[stage] ?? { label: stage, variant: 'secondary' }
  const classes = variantClasses[meta.variant] ?? variantClasses.secondary

  return (
    <Badge
      variant="outline"
      className={cn('font-semibold text-xs tracking-wide', classes, className)}
    >
      <span className="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-current" />
      {meta.label}
    </Badge>
  )
}
