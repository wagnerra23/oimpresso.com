import * as React from "react"

import { SelectItem } from "@/Components/ui/select"

/**
 * SafeSelectItem — borda que neutraliza o crash de tela-branca do Radix Select.
 *
 * ## Por que existe (Tier 0 — classe de render)
 * O Radix `<Select.Item>` LANÇA e derruba o render INTEIRO da árvore React
 * (tela branca em produção — não degradação parcial) se `value` for string
 * vazia: `A <Select.Item /> must have a value prop that is not an empty string`.
 *
 * Já aconteceu: opções data-driven (distinct do banco) com um slug/endpoint
 * NULO/vazio passaram os 20 checks de CI VERDES — só o smoke real (browser MCP,
 * console EXCEPTION) pegou. O hotfix #3411 corrigiu caso a caso com
 * `.filter(Boolean)`. Ref: memory/proibicoes.md §5 (2026-06-29) + PR #3405/#3411.
 *
 * ## O que resolve
 * A garantia mora na BORDA (este componente), NÃO numa análise de fluxo do
 * ESLint. Um lint sintático só enxerga o `.map` literal — `map`/`reduce`/helper/
 * `?? ''` furam. Aqui, seja qual for a forma de gerar o value, se ele chegar
 * null/undefined/'' (ou só espaços) a opção some em vez de crashar a tela.
 *
 * ## O que NÃO resolve (honestidade — R1 continua obrigatório)
 * "CI verde" continua NÃO provando render. Este componente evita o crash da
 * classe empty-value; ele NÃO substitui o smoke real pós-deploy (browser MCP)
 * exigido pela R1. Uma opção que some silenciosamente também é sinal de bug de
 * dado a montante (distinct trazendo NULL) — investigue a origem, não só abafe.
 *
 * ## Quando usar
 * Sempre que o `value` de um item vier de DADO (map/reduce sobre lista do banco,
 * prop, distinct, `Object.keys`) e um membro vazio for plausível. Para o item
 * fixo "Todos"/"Nenhum" use um SENTINELA não-vazio (`__all__`), NUNCA value="".
 */
type SafeSelectItemProps = Omit<
  React.ComponentProps<typeof SelectItem>,
  "value"
> & {
  /** Aceita nulo/indefinido de propósito: value inválido → item não renderiza. */
  value: string | null | undefined
}

function SafeSelectItem({ value, ...props }: SafeSelectItemProps) {
  // Espelha a semântica do `.filter(Boolean)` do hotfix #3411 + trata whitespace
  // e tipo não-string (JS runtime): value inválido => a opção some, sem derrubar
  // o render inteiro. É por construção — nenhuma forma de gerar o value escapa.
  if (value == null || typeof value !== "string" || value.trim() === "") {
    return null
  }

  return <SelectItem value={value} {...props} />
}

export { SafeSelectItem }
