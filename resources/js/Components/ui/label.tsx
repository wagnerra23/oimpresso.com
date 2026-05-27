"use client"

import * as React from "react"
import { Label as LabelPrimitive } from "radix-ui"

import { cn } from "@/Lib/utils"

type LabelVariant = "cowork" | "shadcn"

interface LabelProps extends React.ComponentProps<typeof LabelPrimitive.Root> {
  /**
   * Visual variant.
   *
   * **Default desde 2026-05-27 (ADR UI-0015):** `cowork` aplica classe `.cw-label`
   * (font 11.5px, weight 500, color `--cw-text-dim` — hierarquia visual clara
   * label vs valor).
   *
   * **Opt-in legacy:** `shadcn` — text-sm font-medium text-foreground (preto
   * principal).
   */
  variant?: LabelVariant
}

function Label({ className, variant = "cowork", ...props }: LabelProps) {
  if (variant === "shadcn") {
    return (
      <LabelPrimitive.Root
        data-slot="label"
        className={cn(
          "flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
          className
        )}
        {...props}
      />
    )
  }

  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn("cw-label", className)}
      {...props}
    />
  )
}

export { Label }
export type { LabelVariant, LabelProps }
