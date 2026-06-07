import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Box — primitivo de layout neutro (ADR 0253 · F3 · refino v2 2026-06-07).
 *
 * Container sem opinião visual além de ESPAÇO e SUPERFÍCIE, e tudo só vem de
 * token. `p`/`px`/`py` são enumerados via CVA → o TS recusa px/hex literal em
 * tempo de compilação. O refino v2 entrega a parte de "cor" que a ADR 0253
 * nomeou e a v1 não tinha: `bg`/`rounded`/`border` mapeiam pros tokens do
 * `@theme` (inertia.css) — fecha o caso "card" sem nascer um `.css` bespoke.
 *
 * `asChild` (Radix Slot) projeta as props no filho sem inflar a árvore DOM.
 */
const boxVariants = cva("", {
  variants: {
    p: {
      0: "p-0", 1: "p-1", 2: "p-2", 3: "p-3", 4: "p-4", 5: "p-5",
      6: "p-6", 8: "p-8", 10: "p-10", 12: "p-12",
    },
    px: {
      0: "px-0", 1: "px-1", 2: "px-2", 3: "px-3", 4: "px-4", 5: "px-5",
      6: "px-6", 8: "px-8", 10: "px-10", 12: "px-12",
    },
    py: {
      0: "py-0", 1: "py-1", 2: "py-2", 3: "py-3", 4: "py-4", 5: "py-5",
      6: "py-6", 8: "py-8", 10: "py-10", 12: "py-12",
    },
    /* superfície — só tokens semânticos do @theme (geram utilities Tailwind v4) */
    bg: {
      none: "",
      card: "bg-card text-card-foreground",
      muted: "bg-muted",
      background: "bg-background",
      secondary: "bg-secondary text-secondary-foreground",
      accent: "bg-accent text-accent-foreground",
      primary: "bg-primary text-primary-foreground",
    },
    rounded: {
      none: "rounded-none", sm: "rounded-sm", md: "rounded-md", lg: "rounded-lg",
    },
    border: {
      true: "border border-border",
      false: "",
    },
  },
})

export type BoxProps = React.ComponentProps<"div"> &
  VariantProps<typeof boxVariants> & { asChild?: boolean }

export function Box({ className, p, px, py, bg, rounded, border, asChild = false, ...props }: BoxProps) {
  const Comp = asChild ? Slot.Root : "div"
  return (
    <Comp
      data-slot="box"
      className={cn(boxVariants({ p, px, py, bg, rounded, border }), className)}
      {...props}
    />
  )
}
