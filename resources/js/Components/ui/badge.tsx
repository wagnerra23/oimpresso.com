import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/Lib/utils"

const badgeVariants = cva(
  "inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full border border-transparent px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>svg]:pointer-events-none [&>svg]:size-3",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
        secondary:
          "bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        destructive:
          "bg-destructive text-white focus-visible:ring-destructive/20 dark:bg-destructive/60 dark:focus-visible:ring-destructive/40 [a&]:hover:bg-destructive/90",
        outline:
          "border-border text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
        ghost: "[a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
        link: "text-primary underline-offset-4 [a&]:hover:underline",
        // Status pills (tom soft) — ESTADO, não AÇÃO. Onda M1 (maturidade DS): tokenizado
        // nos pares semânticos `-soft/-fg` (inertia.css). O token já carrega light+dark →
        // sem `dark:` cru. Mata a autocontradição: a camada canônica agora consome o DS
        // que ela mesma exige das Pages (regra ds/no-adhoc-status-text). Migração 1:1 visual.
        success:
          "bg-success-soft text-success-fg border-success/20",
        warning:
          "bg-warning-soft text-warning-fg border-warning/20",
        danger:
          "bg-destructive-soft text-destructive-fg border-destructive/20",
        info:
          "bg-info-soft text-info-fg border-info/20",
        neutral: "bg-muted text-muted-foreground border-border",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

/**
 * `dot` — a 3ª perna do AP7, que faltava.
 *
 * O AP7 do PRE-MERGE-UI (linha 69) é literal: *"Sem `bg-fill` em status badges — usa **dot**
 * + texto colorido (Stripe-style)"*. O #2641 (Onda M1) pagou a 1ª perna (fill → `-soft`) e o
 * #6268 a 2ª (as 9 badges de estado migradas), mas o DOT nunca existiu: medido em 2026-09-01,
 * `dot` tinha ZERO ocorrências neste arquivo e no `StatusBadge`. Quem o achou foi a primeira
 * medição D6 da `Arquivos/Index` (`design-diff --compare --check`), comparando com o
 * `StatusBadge kind="sla"` do espelho DS, que sai com dot — e nenhum gate pega isso, porque
 * AP7 é checklist manual (o único script que o cita, `prototipo-ui/audit/backlog.mjs`, não é
 * invocado por workflow nenhum).
 *
 * `bg-current` de propósito, NÃO token novo: a cor vem do `text-*-fg` que a variante já
 * aplica, então o dot acerta light e dark sozinho e nenhum token entra no DS — criar token é
 * soberania [W] (proibicoes.md §Tier 0). `aria-hidden` porque é redundante com o texto ao lado.
 *
 * Opt-in aqui e ligado por padrão só no `StatusBadge`: neste primitivo `variant` também
 * rotula coisa que não é estado (82 telas o importam contra 14 do StatusBadge), e dot em
 * rótulo seria ruído, não conformidade.
 */
function Badge({
  className,
  variant = "default",
  asChild = false,
  dot = false,
  children,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean; dot?: boolean }) {
  const Comp = asChild ? Slot.Root : "span"

  return (
    <Comp
      data-slot="badge"
      data-variant={variant}
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    >
      {/* `asChild` delega o filho único ao Slot: injetar o dot ali quebraria o contrato do Radix. */}
      {dot && !asChild && (
        <span data-slot="badge-dot" aria-hidden="true" className="size-1.5 shrink-0 rounded-full bg-current" />
      )}
      {children}
    </Comp>
  )
}

export { Badge, badgeVariants }
