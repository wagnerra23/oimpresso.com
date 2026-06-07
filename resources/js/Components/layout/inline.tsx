import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Inline — alinha filhos na horizontal com `gap` de token + `wrap` (ADR 0253 · F3 · refino v2).
 *
 * Substitui o `<div className="flex items-center gap-2">` solto. `wrap` liga o
 * flex-wrap (toolbars, chips). Espaço só por token. O refino v2 adiciona
 * `divider` (separador vertical via `divide-x divide-border`) — o "·" entre
 * meta-itens (ex.: "Cliente PJ · Frota · desde mar/2019").
 */
const inlineVariants = cva("flex flex-row", {
  variants: {
    gap: {
      0: "gap-0", 1: "gap-1", 2: "gap-2", 3: "gap-3", 4: "gap-4", 5: "gap-5",
      6: "gap-6", 8: "gap-8", 10: "gap-10", 12: "gap-12",
    },
    align: {
      start: "items-start",
      center: "items-center",
      end: "items-end",
      stretch: "items-stretch",
      baseline: "items-baseline",
    },
    justify: {
      start: "justify-start",
      center: "justify-center",
      end: "justify-end",
      between: "justify-between",
      around: "justify-around",
      evenly: "justify-evenly",
    },
    wrap: {
      true: "flex-wrap",
      false: "flex-nowrap",
    },
    divider: {
      true: "divide-x divide-border",
      false: "",
    },
  },
  defaultVariants: {
    gap: 2,
    align: "center",
  },
})

export type InlineProps = React.ComponentProps<"div"> &
  VariantProps<typeof inlineVariants> & { asChild?: boolean }

export function Inline({ className, gap, align, justify, wrap, divider, asChild = false, ...props }: InlineProps) {
  const Comp = asChild ? Slot.Root : "div"
  return (
    <Comp
      data-slot="inline"
      className={cn(inlineVariants({ gap, align, justify, wrap, divider }), className)}
      {...props}
    />
  )
}
