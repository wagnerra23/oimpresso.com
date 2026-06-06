import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Grid — grade de N colunas-token com `gap` de token (ADR 0253 · F3).
 *
 * Substitui `<div className="grid grid-cols-3 gap-4">` solto. `cols` enumerado
 * (1–6, 12) cobre os arranjos reais de cadastro/dashboard; responsividade fina
 * fica por composição (`className` extra) até surgir demanda pra prop dedicada.
 */
const gridVariants = cva("grid", {
  variants: {
    cols: {
      1: "grid-cols-1", 2: "grid-cols-2", 3: "grid-cols-3", 4: "grid-cols-4",
      5: "grid-cols-5", 6: "grid-cols-6", 12: "grid-cols-12",
    },
    gap: {
      0: "gap-0", 1: "gap-1", 2: "gap-2", 3: "gap-3", 4: "gap-4", 5: "gap-5",
      6: "gap-6", 8: "gap-8", 10: "gap-10", 12: "gap-12",
    },
  },
  defaultVariants: {
    cols: 1,
    gap: 4,
  },
})

export type GridProps = React.ComponentProps<"div"> &
  VariantProps<typeof gridVariants> & { asChild?: boolean }

export function Grid({ className, cols, gap, asChild = false, ...props }: GridProps) {
  const Comp = asChild ? Slot.Root : "div"
  return (
    <Comp
      data-slot="grid"
      className={cn(gridVariants({ cols, gap }), className)}
      {...props}
    />
  )
}
