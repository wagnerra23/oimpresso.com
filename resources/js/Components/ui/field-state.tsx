import * as React from "react"
import { AlertCircle, Check, Loader2 } from "lucide-react"

import { cn } from "@/Lib/utils"

/**
 * Estados de campo canon Onda F — completam o trio da A2 + obrigatório.
 * Substituem os `<p>` de erro/sucesso soltos com cor crua por tokens semânticos
 * (text-destructive / text-success), anunciados por screen reader.
 */

/** Erro de validação. role=alert (anunciado por screen reader). Não renderiza vazio. */
function FieldError({ className, children, ...props }: React.ComponentProps<"p">) {
  if (!children) return null
  return (
    <p role="alert" data-slot="field-error" className={cn("cw-field-error", className)} {...props}>
      <AlertCircle className="cw-field-ico" aria-hidden />
      {children}
    </p>
  )
}

/** Sucesso (ex.: "Dados preenchidos pela BrasilAPI"). role=status. */
function FieldSuccess({ className, children, ...props }: React.ComponentProps<"span">) {
  if (!children) return null
  return (
    <span role="status" data-slot="field-success" className={cn("cw-field-success", className)} {...props}>
      <Check className="cw-field-ico" aria-hidden />
      {children}
    </span>
  )
}

/** Validação em andamento (spinner). role=status. */
function FieldValidating({ className, children, ...props }: React.ComponentProps<"span">) {
  return (
    <span role="status" data-slot="field-validating" className={cn("cw-field-validating", className)} {...props}>
      <Loader2 className="cw-field-ico cw-spin" aria-hidden />
      {children}
    </span>
  )
}

/** Marca de campo obrigatório (`*`). Decorativo — aria-hidden. */
function RequiredMark({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <span aria-hidden data-slot="required-mark" className={cn("cw-req", className)} {...props}>
      *
    </span>
  )
}

export { FieldError, FieldSuccess, FieldValidating, RequiredMark }
