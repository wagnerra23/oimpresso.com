import * as React from "react"

import { cn } from "@/Lib/utils"

/**
 * FormSection — bloco de formulário canon Onda F (`.cw-form-section`).
 * Substitui o `<section className="rounded-lg border p-4|p-5">` hand-rolled
 * (Create p-4 vs DadosFiscaisBR p-5 — agora um só).
 *
 * `count` mostra um contador opcional à direita do título (ex.: "3 de 4 ✓").
 */
function FormSection({
  title,
  icon,
  count,
  className,
  children,
  ...props
}: {
  title: React.ReactNode
  icon?: React.ReactNode
  count?: React.ReactNode
} & React.ComponentProps<"section">) {
  return (
    <section data-slot="form-section" className={cn("cw-form-section", className)} {...props}>
      <h3 className="cw-form-section-h">
        {icon}
        <span>{title}</span>
        {count != null && <span className="cw-count">{count}</span>}
      </h3>
      {children}
    </section>
  )
}

/**
 * FormGrid — grid 2-col (→1-col em ≤640px) canon Onda F (`.cw-form-grid`).
 * Filhos full-width: adicionar a classe `full-row` no wrapper (`.cw-field`).
 */
function FormGrid({ className, ...props }: React.ComponentProps<"div">) {
  return <div data-slot="form-grid" className={cn("cw-form-grid", className)} {...props} />
}

export { FormSection, FormGrid }
