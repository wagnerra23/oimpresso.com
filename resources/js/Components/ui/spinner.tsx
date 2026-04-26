import * as React from "react"
import { Loader2 } from "lucide-react"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/Lib/utils"

const spinnerVariants = cva("animate-spin text-muted-foreground", {
  variants: {
    size: {
      xs: "h-3 w-3",
      sm: "h-4 w-4",
      default: "h-5 w-5",
      lg: "h-6 w-6",
      xl: "h-8 w-8",
    },
    tone: {
      default: "text-muted-foreground",
      primary: "text-primary",
      foreground: "text-foreground",
    },
  },
  defaultVariants: { size: "default", tone: "default" },
})

interface SpinnerProps
  extends React.HTMLAttributes<HTMLSpanElement>,
    VariantProps<typeof spinnerVariants> {
  /** Texto pra leitor de tela. Default "Carregando…". */
  label?: string
}

/**
 * Spinner — indicador de carregamento acessível.
 *
 * <Spinner size="sm" tone="primary" />
 */
function Spinner({ className, size, tone, label = "Carregando…", ...props }: SpinnerProps) {
  return (
    <span
      role="status"
      aria-live="polite"
      data-slot="spinner"
      className={cn("inline-flex items-center justify-center", className)}
      {...props}
    >
      <Loader2 className={cn(spinnerVariants({ size, tone }))} aria-hidden="true" />
      <span className="sr-only">{label}</span>
    </span>
  )
}

export { Spinner }
