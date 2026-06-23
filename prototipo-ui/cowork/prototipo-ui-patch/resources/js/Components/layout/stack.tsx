import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Stack — empilha filhos na vertical com `gap` de token (ADR 0253 · F3 · refino v2).
 *
 * Substitui o `<div className="flex flex-col gap-4">` solto repetido pelas telas.
 * `gap`/`align`/`justify` enumerados → espaço nunca vem de px literal. O refino v2
 * adiciona `divider` (hairline entre itens via `divide-y divide-border`) — o
 * separador onipresente no DS, antes feito à mão.
 */
const stackVariants = cva("flex flex-col", {
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
    divider: {
      true: "divide-y divide-border",
      false: "",
    },
  },
  defaultVariants: {
    gap: 4,
  },
})

export type StackProps = React.ComponentProps<"div"> &
  VariantProps<typeof stackVariants> & { asChild?: boolean }

export function Stack({ className, gap, align, justify, divider, asChild = false, ...props }: StackProps) {
  const Comp = asChild ? Slot.Root : "div"
  return (
    <Comp
      data-slot="stack"
      className={cn(stackVariants({ gap, align, justify, divider }), className)}
      {...props}
    />
  )
}
