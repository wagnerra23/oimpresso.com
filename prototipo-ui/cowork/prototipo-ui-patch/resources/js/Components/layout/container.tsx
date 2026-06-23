import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Container — largura-máxima de página + padding horizontal de token (ADR 0253 · F3 · refino v2).
 *
 * O wrapper de página: centraliza e limita a medida de leitura. `px` é o respiro
 * lateral (token).
 *
 * ⚠️ Correção do refino v2: a v1 usava `max-w-screen-*`, utilities REMOVIDAS no
 * Tailwind v4 — o limite de largura virava silenciosamente nulo. Agora mapeia
 * pra escala `max-w-*` que existe de fato no v4 (valores controlados no enum,
 * nunca px literal no call-site).
 */
const containerVariants = cva("mx-auto w-full", {
  variants: {
    size: {
      sm: "max-w-3xl",      /* ~768px  */
      md: "max-w-5xl",      /* ~1024px */
      lg: "max-w-6xl",      /* ~1152px */
      xl: "max-w-7xl",      /* ~1280px — alvo Larissa/Wagner */
      "2xl": "max-w-[96rem]", /* ~1536px */
      full: "max-w-full",
    },
    px: {
      0: "px-0", 2: "px-2", 4: "px-4", 6: "px-6", 8: "px-8",
    },
  },
  defaultVariants: {
    size: "xl",
    px: 4,
  },
})

export type ContainerProps = React.ComponentProps<"div"> &
  VariantProps<typeof containerVariants> & { asChild?: boolean }

export function Container({ className, size, px, asChild = false, ...props }: ContainerProps) {
  const Comp = asChild ? Slot.Root : "div"
  return (
    <Comp
      data-slot="container"
      className={cn(containerVariants({ size, px }), className)}
      {...props}
    />
  )
}
