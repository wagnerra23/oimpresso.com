import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Container — largura-máxima de página + padding horizontal de token (ADR 0253 · F3).
 *
 * O wrapper de página: centraliza e limita a medida de leitura. `size` mapeia
 * pros breakpoints-token do Tailwind; `px` é o respiro lateral (token).
 */
const containerVariants = cva("mx-auto w-full", {
  variants: {
    size: {
      sm: "max-w-screen-sm",
      md: "max-w-screen-md",
      lg: "max-w-screen-lg",
      xl: "max-w-screen-xl",
      "2xl": "max-w-screen-2xl",
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
