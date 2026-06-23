import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Text — tipografia 100% via type-scale token (ADR 0253 · F3 · refino v2 2026-06-07).
 *
 * Mata o `className="text-[22px]"` / cor crua. `as` escolhe a tag semântica
 * (h1–h6/p/span/label) — estilo e semântica desacoplados.
 *
 * Refino v2 — fecha os gaps que travavam telas de NÚMERO (vendas/financeiro/OS/NF-e):
 *  • `family="mono"` + `numeric="tabular"` → money, placa, km, NF-e alinham.
 *    (NB: o utility `font-mono` cai no mono do sistema até o token `--font-mono`
 *     ("IBM Plex Mono") existir no @theme — decisão Tier 0 de [W], flag no handoff.)
 *  • `tone` ganha `success`/`warning`/`destructive` (KPI +/−) — tokens REAIS do
 *    @theme (inertia.css), não os nomes da régua CSS (`--pos/--neg`).
 *  • `size` sobe até `5xl` — o KPI canônico é `4xl/36px` (DESIGN.md §16.3),
 *    que a v1 (teto `3xl`) não expressava.
 */
const textVariants = cva("", {
  variants: {
    size: {
      xs: "text-xs",
      sm: "text-sm",
      base: "text-base",
      lg: "text-lg",
      xl: "text-xl",
      "2xl": "text-2xl",
      "3xl": "text-3xl",
      "4xl": "text-4xl",
      "5xl": "text-5xl",
    },
    weight: {
      normal: "font-normal",
      medium: "font-medium",
      semibold: "font-semibold",
      bold: "font-bold",
    },
    /* tons = tokens semânticos do @theme (geram utilities Tailwind v4) */
    tone: {
      default: "text-foreground",
      muted: "text-muted-foreground",
      primary: "text-primary",
      success: "text-success",
      warning: "text-warning",
      destructive: "text-destructive",
    },
    family: {
      sans: "font-sans",
      mono: "font-mono",
    },
    numeric: {
      tabular: "tabular-nums",
      normal: "",
    },
    leading: {
      none: "leading-none",
      tight: "leading-tight",
      snug: "leading-snug",
      normal: "leading-normal",
      relaxed: "leading-relaxed",
    },
    align: {
      left: "text-left",
      center: "text-center",
      right: "text-right",
    },
    truncate: {
      true: "truncate",
    },
  },
  defaultVariants: {
    size: "base",
    weight: "normal",
    tone: "default",
  },
})

type TextElement =
  | "p" | "span" | "div" | "label" | "strong" | "em"
  | "h1" | "h2" | "h3" | "h4" | "h5" | "h6"

export type TextProps = React.HTMLAttributes<HTMLElement> &
  VariantProps<typeof textVariants> & {
    as?: TextElement
    asChild?: boolean
  }

export function Text({
  className, size, weight, tone, family, numeric, leading, align, truncate,
  as = "p", asChild = false, ...props
}: TextProps) {
  const Comp = asChild ? Slot.Root : as
  return (
    <Comp
      data-slot="text"
      className={cn(
        textVariants({ size, weight, tone, family, numeric, leading, align, truncate }),
        className,
      )}
      {...props}
    />
  )
}
