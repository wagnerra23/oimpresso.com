import * as React from "react"

import { Label } from "@/Components/ui/label"
import { FieldError, RequiredMark } from "@/Components/ui/field-state"
import { cn } from "@/Lib/utils"

/**
 * Field — wrapper canon de campo do cadastro Cliente (label + controle + erro).
 * Consolida o `<Field>` que estava duplicado em Create/Edit/DadosFiscais.
 *
 * a11y (ajuste Claude Design): quando há erro, injeta no controle filho
 * `aria-invalid` + `aria-describedby` apontando pro id do <FieldError> (que é
 * role=alert). Funciona direto pro <Input> (props passam pro <input>).
 */
export function Field({
  label,
  error,
  required = false,
  fullRow = false,
  children,
}: {
  label: React.ReactNode
  error?: string
  required?: boolean
  fullRow?: boolean
  children: React.ReactNode
}) {
  const errorId = React.useId()

  const child =
    error && React.isValidElement(children)
      ? React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
          "aria-invalid": true,
          "aria-describedby": [
            (children as React.ReactElement<Record<string, unknown>>).props["aria-describedby"],
            errorId,
          ]
            .filter(Boolean)
            .join(" "),
        })
      : children

  return (
    <div className={cn("cw-field", fullRow && "full-row", error && "has-error")}>
      <Label className="cw-label">
        {label}
        {required && <RequiredMark />}
      </Label>
      {child}
      <FieldError id={errorId}>{error}</FieldError>
    </div>
  )
}
