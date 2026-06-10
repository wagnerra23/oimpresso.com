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
 *
 * Type RAMP (F2 Financeiro · [W] "vai" 2026-06-10): `size` consome a âncora
 * única `--fs-1..9` de `resources/css/foundations.css` — UMA escala, não duas.
 * Mapeamento 1:1 xs→fs-1 … 5xl→fs-9. Cada degrau carrega o leading default das
 * regras de acabamento do ramp (1.45 corpo · 1.2 títulos · 1 números); a
 * variant `leading` explícita sobrescreve (tailwind-merge resolve o conflito).
 */
const textVariants = cva("", {
  variants: {
    size: {
      xs: "text-[length:var(--fs-1)] leading-[1.45]",
      sm: "text-[length:var(--fs-2)] leading-[1.45]",
      base: "text-[length:var(--fs-3)] leading-[1.45]",
      lg: "text-[length:var(--fs-4)] leading-[1.45]",
      xl: "text-[length:var(--fs-5)] leading-[1.45]",
      "2xl": "text-[length:var(--fs-6)] leading-[1.2]",
      "3xl": "text-[length:var(--fs-7)] leading-[1.2]",
      "4xl": "text-[length:var(--fs-8)] leading-none",
      "5xl": "text-[length:var(--fs-9)] leading-none",
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
