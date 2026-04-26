import * as React from "react"
import { cn } from "@/Lib/utils"

interface EmptyProps extends Omit<React.HTMLAttributes<HTMLDivElement>, "title"> {
  icon?: React.ReactNode
  title?: React.ReactNode
  description?: React.ReactNode
  action?: React.ReactNode
}

/**
 * Empty — estado vazio enterprise (sem dados, sem resultados).
 *
 * <Empty
 *   icon={<Inbox className="size-8" />}
 *   title="Sem mensagens"
 *   description="Quando alguém te enviar algo, aparece aqui."
 *   action={<Button>Nova conversa</Button>}
 * />
 */
function Empty({
  icon,
  title,
  description,
  action,
  className,
  children,
  ...props
}: EmptyProps) {
  return (
    <div
      data-slot="empty"
      className={cn(
        "flex min-h-[280px] flex-col items-center justify-center px-6 py-12 text-center",
        className
      )}
      {...props}
    >
      {icon && (
        <div className="mb-4 flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
          {icon}
        </div>
      )}
      {title && (
        <h3 className="mb-1.5 text-h4 text-foreground" style={{ fontWeight: 600 }}>
          {title}
        </h3>
      )}
      {description && (
        <p className="max-w-sm text-small text-muted-foreground">
          {description}
        </p>
      )}
      {action && <div className="mt-6">{action}</div>}
      {children}
    </div>
  )
}

export { Empty }
