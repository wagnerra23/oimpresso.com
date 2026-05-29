import * as React from "react"
import { Check, Loader2 } from "lucide-react"

import { cn } from "@/Lib/utils"

/**
 * InputGroup — input + addon/botão coeso (Onda F `.cw-input-group`).
 * Substitui o `<div className="flex"> input + button` do "Buscar CNPJ"/ViaCEP
 * e o prefixo `R$`/`%`. Use com `<Input variant="cowork">` dentro.
 */
function InputGroup({ className, ...props }: React.ComponentProps<"div">) {
  return <div data-slot="input-group" className={cn("cw-input-group", className)} {...props} />
}

/**
 * Botão acoplado à direita do input (ex.: "Buscar"). `loading` mostra spinner,
 * `done` pinta de verde com check — feedback do lookup.
 */
function InputGroupButton({
  loading = false,
  done = false,
  className,
  children,
  disabled,
  ...props
}: {
  loading?: boolean
  done?: boolean
} & React.ComponentProps<"button">) {
  return (
    <button
      type="button"
      data-slot="input-group-button"
      className={cn("cw-ig-btn", loading && "cw-loading", done && "cw-done", className)}
      disabled={loading || disabled}
      {...props}
    >
      {loading ? (
        <Loader2 className="cw-spin" aria-hidden />
      ) : done ? (
        <Check aria-hidden />
      ) : null}
      {children}
    </button>
  )
}

/** Prefixo/sufixo não-interativo (ex.: `R$`, `%`). */
function InputGroupAddon({ className, ...props }: React.ComponentProps<"span">) {
  return <span data-slot="input-group-addon" className={cn("cw-ig-addon", className)} {...props} />
}

export { InputGroup, InputGroupButton, InputGroupAddon }
