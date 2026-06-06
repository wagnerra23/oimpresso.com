import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Text — tipografia 100% via type-scale token (ADR 0253 · F3).
 *
 * Mata o `className="text-[22px]"` / cor crua: `size`/`weight`/`tone` são
 * enumerados e mapeiam pros tokens do DS v6 (`text-foreground`,
 * `text-muted-foreground`, `text-primary`, `text-destructive`). `as` escolhe a
 * tag semântica (h1–h6/p/span/label) — estilo e semântica desacoplados.
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
    },
    weight: {
      normal: "font-normal",
      medium: "font-medium",
      semibold: "font-semibold",
      bold: "font-bold",
    },
    tone: {
      default: "text-foreground",
      muted: "text-muted-foreground",
      primary: "text-primary",
      destructive: "text-destructive",
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
  className, size, weight, tone, align, truncate, as = "p", asChild = false, ...props
}: TextProps) {
  const Comp = asChild ? Slot.Root : as
  return (
    <Comp
      data-slot="text"
      className={cn(textVariants({ size, weight, tone, align, truncate }), className)}
      {...props}
    />
  )
}
