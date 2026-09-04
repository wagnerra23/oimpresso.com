import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Grid — grade de N colunas-token com `gap` de token (ADR 0253 · F3 · refino v2).
 *
 * Três modos, escolha um:
 *  • `cols` (1–6,12) → grade FIXA de cadastro/formulário.
 *  • `min` (sm/md/lg) → grade RESPONSIVA **auto-FILL**: as colunas se reflowam pela
 *    largura disponível (`repeat(auto-fill, minmax(<token>, 1fr))`). É o que
 *    cura a quebra entre 1280 (Larissa) e 1440 (Wagner) sem media-query na tela.
 *  • `fit` (xs/sm/md/lg) → idem, mas **auto-FIT**.
 *
 * ⚠️ `auto-fill` × `auto-fit` NÃO são sinônimos, e a escolha muda o render:
 * `auto-fill` MANTÉM as faixas vazias que couberem; `auto-fit` as COLAPSA e deixa
 * os itens existentes esticarem. Com 4 cards num container de ~1232px e mínimo de
 * 224px, o `auto-fill` abre uma 5ª faixa VAZIA (cards de 231px + um buraco), e o
 * `auto-fit` dá 4 cards de ~296px. Use `fit` quando o número de itens é fixo e
 * pequeno (KPIs); `min` quando a lista é aberta e alinhar a "trilhos" é desejável.
 *
 * (Até 2026-09-03 este docblock dizia "auto-fit" enquanto o código fazia
 * `auto-fill` — a prosa afirmava o que a implementação não fazia. Corrigido aqui,
 * sem tocar no comportamento de `min`, que 42 telas já consomem.)
 *
 * Largura mínima vem de token (enum), não de px solto no call-site.
 */
const gridVariants = cva("grid", {
  variants: {
    cols: {
      1: "grid-cols-1", 2: "grid-cols-2", 3: "grid-cols-3", 4: "grid-cols-4",
      5: "grid-cols-5", 6: "grid-cols-6", 12: "grid-cols-12",
    },
    /* auto-FILL responsivo — largura mínima da coluna por token de escala */
    min: {
      sm: "grid-cols-[repeat(auto-fill,minmax(14rem,1fr))]",
      md: "grid-cols-[repeat(auto-fill,minmax(18rem,1fr))]",
      lg: "grid-cols-[repeat(auto-fill,minmax(22rem,1fr))]",
    },
    /* auto-FIT — colapsa faixa vazia; para conjunto pequeno e fixo (ver docblock) */
    fit: {
      xs: "grid-cols-[repeat(auto-fit,minmax(7.5rem,1fr))]",
      sm: "grid-cols-[repeat(auto-fit,minmax(14rem,1fr))]",
      md: "grid-cols-[repeat(auto-fit,minmax(18rem,1fr))]",
      lg: "grid-cols-[repeat(auto-fit,minmax(22rem,1fr))]",
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

export function Grid({ className, cols, min, fit, gap, asChild = false, ...props }: GridProps) {
  const Comp = asChild ? Slot.Root : "div"
  // `min`/`fit` (responsivos) vencem `cols`; default = 1 coluna quando nenhum dos tres.
  // ⚠️ `fit` PRECISA estar na destruturacao E no cva. Entre 2026-09-03 e 09-04 ele
  // estava so no cva: caia no `...props`, virava atributo DOM (`<div fit="sm">`), e
  // `resolvedCols` ia pro default 1 => `grid-cols-1`. A Home empilhou os 4 KPIs em
  // producao. Prop declarada que o componente descarta e inerte em silencio: o
  // typecheck aceita, o CI fica verde, e so o DOM denuncia.
  const resolvedCols = (min || fit) ? undefined : (cols ?? 1)
  return (
    <Comp
      data-slot="grid"
      className={cn(gridVariants({ cols: resolvedCols, min, fit, gap }), className)}
      {...props}
    />
  )
}
