import * as React from "react"
import { ToggleGroup as ToggleGroupPrimitive } from "radix-ui"

import { cn } from "@/Lib/utils"

export interface SegmentedOption {
  value: string
  label: React.ReactNode
  disabled?: boolean
}

/**
 * Segmented — controle de seleção única (toggle 2–3 opções) sobre Radix
 * ToggleGroup (type single). Visual canon Onda F (`.cw-segmented`).
 * Substitui `<input type="radio">` de PF/PJ, Cliente/Fornecedor, vistas.
 *
 * `accent` pinta o estado ativo em roxo (em vez de neutro) — usar quando a
 * escolha é "de marca" (ex.: Jurídica destacada).
 */
function Segmented({
  value,
  onValueChange,
  options,
  accent = false,
  className,
  ...props
}: {
  value: string
  onValueChange: (value: string) => void
  options: SegmentedOption[]
  accent?: boolean
} & Omit<
  React.ComponentProps<typeof ToggleGroupPrimitive.Root>,
  "type" | "value" | "onValueChange"
>) {
  return (
    <ToggleGroupPrimitive.Root
      type="single"
      data-slot="segmented"
      value={value}
      // type=single permite desmarcar (value -> ""); como é seleção obrigatória,
      // ignoramos o evento vazio pra manter sempre uma opção ativa.
      onValueChange={(v) => {
        if (v) onValueChange(v)
      }}
      className={cn("cw-segmented", accent && "cw-accent", className)}
      {...props}
    >
      {options.map((opt) => (
        <ToggleGroupPrimitive.Item
          key={opt.value}
          value={opt.value}
          disabled={opt.disabled}
          data-slot="segmented-item"
        >
          {opt.label}
        </ToggleGroupPrimitive.Item>
      ))}
    </ToggleGroupPrimitive.Root>
  )
}

export { Segmented }
