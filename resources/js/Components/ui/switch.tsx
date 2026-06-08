import * as React from "react"
import { Switch as SwitchPrimitive } from "radix-ui"

import { cn } from "@/Lib/utils"

type SwitchVariant = "shadcn" | "cowork"

function Switch({
  className,
  size = "default",
  variant = "shadcn",
  ...props
}: React.ComponentProps<typeof SwitchPrimitive.Root> & {
  size?: "sm" | "default"
  /**
   * Visual variant.
   *
   * **Default `shadcn`** (mantido pra não quebrar telas legacy).
   *
   * **Opt-in `cowork` (ADR UI-0015)**: aplica `.cw-switch` / `.cw-switch-thumb`
   * — track 28×16, thumb 12px, accent quando on. Visual fiel ao protótipo Cowork.
   */
  variant?: SwitchVariant
}) {
  if (variant === "cowork") {
    return (
      <SwitchPrimitive.Root
        data-slot="switch"
        className={cn("cw-switch", className)}
        {...props}
      >
        <SwitchPrimitive.Thumb data-slot="switch-thumb" className="cw-switch-thumb" />
      </SwitchPrimitive.Root>
    )
  }

  return (
    <SwitchPrimitive.Root
      data-slot="switch"
      data-size={size}
      className={cn(
        "peer group/switch inline-flex shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-[1.15rem] data-[size=default]:w-8 data-[size=sm]:h-3.5 data-[size=sm]:w-6 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input dark:data-[state=unchecked]:bg-input/80",
        className
      )}
      {...props}
    >
      <SwitchPrimitive.Thumb
        data-slot="switch-thumb"
        className={cn(
          "pointer-events-none block rounded-full bg-background ring-0 transition-transform group-data-[size=default]/switch:size-4 group-data-[size=sm]/switch:size-3 data-[state=checked]:translate-x-[calc(100%-2px)] data-[state=unchecked]:translate-x-0 dark:data-[state=checked]:bg-primary-foreground dark:data-[state=unchecked]:bg-foreground"
        )}
      />
    </SwitchPrimitive.Root>
  )
}

export { Switch }
export type { SwitchVariant }
