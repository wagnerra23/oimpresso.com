import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Grid — grade de N colunas-token com `gap` de token (ADR 0253 · F3 · refino v2).
 *
 * Dois modos, escolha um:
 *  • `cols` (1–6,12) → grade FIXA de cadastro/formulário.
 *  • `min` (sm/md/lg) → grade RESPONSIVA auto-fit: as colunas se reflowam pela
 *    largura disponível (`repeat(auto-fill, minmax(<token>, 1fr))`). É o que
 *    cura a quebra entre 1280 (Larissa) e 1440 (Wagner) sem media-query na tela,
 *    e expressa o `repeat(auto-fill,minmax(…))` que a grade de cards do DS usa.
 *
 * Largura mínima vem de token (enum), não de px solto no call-site.
 */
const gridVariants = cva("grid", {
  variants: {
    cols: {
      1: "grid-cols-1", 2: "grid-cols-2", 3: "grid-cols-3", 4: "grid-cols-4",
      5: "grid-cols-5", 6: "grid-cols-6", 12: "grid-cols-12",
    },
    /* auto-fit responsivo — largura mínima da coluna por token de escala */
    min: {
      sm: "grid-cols-[repeat(auto-fill,minmax(14rem,1fr))]",
      md: "grid-cols-[repeat(auto-fill,minmax(18rem,1fr))]",
      lg: "grid-cols-[repeat(auto-fill,minmax(22rem,1fr))]",
    },
    gap: {
      0: "gap-0", 1: "gap-1", 2: "gap-2", 3: "gap-3", 4: "gap-4", 5: "gap-5",
      6: "gap-6", 8: "gap-8", 10: "gap-10", 12: "gap-12",
    },
  },
  defaultVariants: {
    gap: 4,
  },
})

export type GridProps = React.ComponentProps<"div"> &
  VariantProps<typeof gridVariants> & { asChild?: boolean }

export function Grid({ className, cols, min, gap, asChild = false, ...props }: GridProps) {
  const Comp = asChild ? Slot.Root : "div"
  // `min` (auto-fit) vence `cols` se ambos vierem; default = 1 coluna quando nenhum.
  const resolvedCols = min ? undefined : (cols ?? 1)
  return (
    <Comp
      data-slot="grid"
      className={cn(gridVariants({ cols: resolvedCols, min, gap }), className)}
      {...props}
    />
  )
}
