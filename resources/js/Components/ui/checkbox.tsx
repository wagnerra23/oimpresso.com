import * as React from "react"
import { Checkbox as CheckboxPrimitive } from "radix-ui"

import { cn } from "@/Lib/utils"

/**
 * Caixinha do DS — `prototipo-ui/design-system/components/Checkbox/Checkbox.jsx`, a mesma em todas as
 * telas (filtros, seleção de linha da `shared/DataTable`, formulários).
 *
 * Os números vêm de lá: 16×16, canto `--radius-sm`, borda de 1,5px; desmarcada, borda `--border` sobre
 * `--surface`; marcada, fundo e borda `--accent` com o ✓ de 11px (traço 3,5) na cor `--accent-fg`;
 * transição de .15s; desabilitada com opacidade .55.
 *
 * Até 2026-11-12 era a caixinha do shadcn: borda de 1px na cor de destaque (roxa) até desmarcada,
 * sombra e o ✓ do lucide. Medido no navegador, a borda era a única diferença de forma.
 *
 * Os tokens do DS (`--border`, `--surface`, `--accent`, `--accent-fg`) só existem dentro do `.cockpit`.
 * Fora dele (tela pública, portal montado no `<body>`) cada um cai no token do tema do app.
 *
 * O ✓ usa `--accent-fg`, não o branco literal do `Checkbox.jsx`: no escuro o destaque clareia e o
 * token troca para tinta escura (`tokens/semantic.tokens.json`); no claro ele é o próprio branco.
 *
 * Borda da desmarcada no ESCURO: `--text-mute`, não `--border`. Decisão [F] 2026-11-25: manter o
 * `Checkbox` do DS (a caixa nativa do navegador continua fora, decisão [M] 2026-10-06) e deixar a borda
 * visível. Contraste calculado dos tokens do tema escuro (`_generated-cockpit-dark.css`), WCAG 1.4.11
 * pede 3:1 no contorno de controle: `--border` dá 1,16:1 sobre `--surface` (a caixinha sumia na
 * tabela), `--text-mute` dá 4,55:1. Só a desmarcada muda: marcada e parcial seguem `--accent`. No CLARO
 * a borda segue `--border` (1,35:1 sobre o branco, também abaixo de 3:1), levado ao [W] junto com o
 * pedido de levar a mesma troca ao `Checkbox.jsx` do DS, que ainda usa `--border` nos dois temas.
 *
 * Estado "parcial" (`checked="indeterminate"`): tracinho no lugar do ✓. É o "marcar todas" da
 * `shared/DataTable` quando só parte da página está marcada. O `Checkbox` do DS ainda não tem esse
 * estado (registrado no Cowork em 2026-10-06); aqui ele usa o mesmo preenchimento do marcado.
 */

function Checkbox({
  className,
  ...props
}: React.ComponentProps<typeof CheckboxPrimitive.Root>) {
  const parcial = props.checked === "indeterminate"
  return (
    <CheckboxPrimitive.Root
      data-slot="checkbox"
      className={cn(
        "peer grid h-4 w-4 shrink-0 place-items-center rounded-sm border-[1.5px] border-[var(--border,var(--color-border))] bg-[var(--surface,var(--color-background))] outline-none transition-[background-color,border-color] duration-150 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-[.55] dark:data-[state=unchecked]:border-[var(--text-mute,var(--color-muted-foreground))] data-[state=checked]:border-[var(--accent,var(--color-primary))] data-[state=checked]:bg-[var(--accent,var(--color-primary))] data-[state=indeterminate]:border-[var(--accent,var(--color-primary))] data-[state=indeterminate]:bg-[var(--accent,var(--color-primary))]",
        className
      )}
      {...props}
    >
      <CheckboxPrimitive.Indicator
        data-slot="checkbox-indicator"
        className="grid place-items-center text-[var(--accent-fg,var(--color-primary-foreground))]"
      >
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          <path d={parcial ? "M6 12h12" : "M5 12l5 5 9-9"} />
        </svg>
      </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
  )
}

export { Checkbox }
