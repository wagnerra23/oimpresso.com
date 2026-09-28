import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/Lib/utils"

const alertVariants = cva(
  "relative grid w-full grid-cols-[0_1fr] items-start gap-y-0.5 rounded-lg border px-4 py-3 text-sm has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current",
  {
    variants: {
      variant: {
        default: "bg-card text-card-foreground",
        destructive:
          "bg-card text-destructive *:data-[slot=alert-description]:text-destructive/90 [&>svg]:text-current",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

/**
 * O ÍCONE DO ALERT É DECORATIVO — ele repete o que o título/descrição já dizem.
 *
 * "O ícone" aqui é exatamente o que o layout acima já trata como ícone: o `svg` FILHO DIRETO
 * (`has-[>svg]`, `[&>svg]:size-4`). Usar o mesmo critério, e não o nome do componente, cobre
 * `lucide-react`, o wrapper `@/Components/Icon` e `svg` cru — nenhum dos três marca
 * `aria-hidden` sozinho (medido 2026-09-28: `lucide-react` 0.460 não põe o atributo, e o
 * `Icon` só repassa props). Sem isto o leitor de tela anuncia uma figura sem nome antes do
 * aviso: eram ≥16 dos 17 alerts com ícone do repo.
 *
 * Quem der NOME ao ícone continua sendo respeitado: `aria-label`, `aria-labelledby`,
 * `role="img"`, um `<title>` dentro, ou um `aria-hidden` explícito (inclusive `false`) deixam
 * o `svg` intocado. Marca-se no DOM, depois do render, porque só ali se sabe o que a criança
 * de fato renderizou — e `useEffect`, não `useLayoutEffect`, porque há SSR.
 *
 * Playbook `ds-atomos`, thread 06 (achado 2) · decisão [W] 2026-09-28.
 */
function marcarIconesDecorativos(raiz: HTMLElement | null) {
  if (!raiz) return
  for (const svg of Array.from(raiz.children)) {
    if (svg.tagName.toLowerCase() !== "svg") continue
    const temNome =
      svg.hasAttribute("aria-label") ||
      svg.hasAttribute("aria-labelledby") ||
      svg.getAttribute("role") === "img" ||
      svg.hasAttribute("aria-hidden") ||
      Array.from(svg.children).some((c) => c.localName === "title")
    if (temNome) continue
    svg.setAttribute("aria-hidden", "true")
    svg.setAttribute("focusable", "false")
  }
}

function Alert({
  className,
  variant,
  ref,
  ...props
}: React.ComponentProps<"div"> & VariantProps<typeof alertVariants>) {
  const local = React.useRef<HTMLDivElement | null>(null)
  const juntarRefs = React.useCallback(
    (el: HTMLDivElement | null) => {
      local.current = el
      if (typeof ref === "function") ref(el)
      else if (ref) (ref as React.RefObject<HTMLDivElement | null>).current = el
    },
    [ref]
  )
  // Sem deps de propósito: o ícone pode entrar ou trocar a cada render (alerta condicional).
  // É uma varredura dos filhos diretos — custo desprezível.
  React.useEffect(() => marcarIconesDecorativos(local.current))

  return (
    <div
      ref={juntarRefs}
      data-slot="alert"
      role="alert"
      className={cn(alertVariants({ variant }), className)}
      {...props}
    />
  )
}

function AlertTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-title"
      className={cn(
        "col-start-2 line-clamp-1 min-h-4 font-medium tracking-tight",
        className
      )}
      {...props}
    />
  )
}

function AlertDescription({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-description"
      className={cn(
        "col-start-2 grid justify-items-start gap-1 text-sm text-muted-foreground [&_p]:leading-relaxed",
        className
      )}
      {...props}
    />
  )
}

export { Alert, AlertTitle, AlertDescription }
