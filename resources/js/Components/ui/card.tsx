import * as React from "react"

import { cn } from "@/Lib/utils"

// Anatomia Widget — três slots ADITIVOS (playbook ds-atomos thread 01).
//
// LEI DESTA PASTA: aditivo ou nada. `badge`/`note`/`flush` são opcionais e, quando
// AUSENTES, não emitem nenhum nó nem nenhuma classe extra — o markup fica idêntico ao
// de 733033864088. É o que protege os 139 consumidores medidos em 2026-09-13 (varredura
// contada: `git grep -l "ui/card"` = 139 de 139, Ponto 21 · Financeiro 16 · Essentials 15
// · governance 9 · Whatsapp 17 · Forja 7 · Superadmin 6 · +19 áreas). A guarda física
// dessa promessa é tests/js/card-anatomia.test.tsx, wirada em card-anatomia-gate.yml.
//
// Por que três slots e não um `children` no header: hoje contagem e frase de apoio caem
// dentro do CardTitle e disputam a mesma linha — o título trunca e a contagem some.

function Card({
  className,
  flush,
  ...props
}: React.ComponentProps<"div"> & {
  /** Zera o padding horizontal do CORPO (o conteúdo sangra até a borda — caso da tabela). Header e footer intactos. */
  flush?: boolean
}) {
  return (
    <div
      data-slot="card"
      className={cn(
        "flex flex-col gap-6 rounded-xl border bg-card py-6 text-card-foreground shadow-sm",
        flush && "[&_[data-slot=card-content]]:px-0",
        className
      )}
      {...props}
    />
  )
}

function CardHeader({
  className,
  note,
  children,
  ...props
}: React.ComponentProps<"div"> & {
  /** Linha de apoio ABAIXO do título, dentro do header. Quebra em 2 linhas — nunca trunca. */
  note?: React.ReactNode
}) {
  return (
    <div
      data-slot="card-header"
      className={cn(
        "@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6",
        className
      )}
      {...props}
    >
      {children}
      {note != null && (
        <div
          data-slot="card-note"
          className="col-start-1 text-xs break-words text-muted-foreground"
        >
          {note}
        </div>
      )}
    </div>
  )
}

function CardTitle({
  className,
  badge,
  children,
  ...props
}: React.ComponentProps<"div"> & {
  /** Nó à DIREITA do título, na mesma linha de base (contagem, período). Encolhe por último. */
  badge?: React.ReactNode
}) {
  return (
    <div
      data-slot="card-title"
      className={cn(
        "leading-none font-semibold",
        badge != null && "flex items-baseline justify-between gap-2",
        className
      )}
      {...props}
    >
      {badge == null ? (
        children
      ) : (
        <>
          <span className="min-w-0">{children}</span>
          <span
            data-slot="card-title-badge"
            className="shrink-0 font-mono text-[11.5px] font-normal whitespace-nowrap text-muted-foreground"
          >
            {badge}
          </span>
        </>
      )}
    </div>
  )
}

function CardDescription({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-description"
      className={cn("text-sm text-muted-foreground", className)}
      {...props}
    />
  )
}

function CardAction({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-action"
      className={cn(
        "col-start-2 row-span-2 row-start-1 self-start justify-self-end",
        className
      )}
      {...props}
    />
  )
}

function CardContent({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-content"
      className={cn("px-6", className)}
      {...props}
    />
  )
}

function CardFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-footer"
      className={cn("flex items-center px-6 [.border-t]:pt-6", className)}
      {...props}
    />
  )
}

export {
  Card,
  CardHeader,
  CardFooter,
  CardTitle,
  CardAction,
  CardDescription,
  CardContent,
}
