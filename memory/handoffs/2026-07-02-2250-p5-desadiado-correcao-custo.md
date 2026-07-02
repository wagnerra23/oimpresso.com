---
date: "2026-07-02"
time: "22:50 BRT"
slug: p5-desadiado-correcao-custo
tldr: "Continuação da Onda 0 Estoque: Wagner desadiou → P5 Purchase→Compras merge concluído (#3684, docs-only, merge sem perda dos dois lados; Tabela B P5/P6/P7 ✅) + CORREÇÃO do Wagner: custo NÃO é greenfield ('já tem coisa pronta, estais desatualizado') — a afirmação 'custo médio ponderado é gap' está STALE. Não repetir; reverificar mecanismo de custo com Wagner ANTES de desenhar Onda 1. #3678 (drifts) e #3684 (P5) com auto-merge armado."
prs: [3684, 3678]
related_adrs:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
next_steps:
  - "Antes de Onda 1: perguntar ao Wagner / achar no código o mecanismo de custo que ele considera pronto (NÃO assumir greenfield)"
  - "Corrigir a afirmação stale de custo no plano/mapa (branch #3672, não mergeado)"
  - "Confirmar #3678 e #3684 mergearam (auto-merge armado)"
---

# Handoff — P5 desadiado + correção de custo (continuação Onda 0)

> Sequência do handoff [2245 onda0-consolidacao-estoque](2026-07-02-2245-onda0-consolidacao-estoque.md).

## Estado MCP no momento do fechamento

- **`cycles-active` (COPI):** nenhum cycle ATIVO.
- **`my-work`:** 30 tasks (8 REVIEW / 8 BLOCKED / 14 TODO) — nenhuma de Estoque/Inventory no WIP.
- **`decisions-search "custo médio / average cost":`** sem ADR de valoração de estoque — o método de custo NÃO está cravado em ADR (mais um motivo pra confirmar com Wagner antes de codar).

## O que aconteceu (2 coisas)

### 1. P5 desadiado — merge Purchase→Compras concluído ([#3684](https://github.com/wagnerra23/oimpresso.com/pull/3684))
Wagner: *"quero fazer já agora, tire o status de adiada"*. Fiz o **merge** (não sobrescrita cega — premissa invertida: Purchase era o novo, Compras/_telas o velho-mas-complementar): `Compras/_telas/` vira casa única (create fundido novo+antigo, index novo, edit compliant, visual-comparison grade incorporada); `Purchase/` vira lápide "P5 CONCLUÍDA"; `_TRIAGEM-IDENTIDADE` Tabela B **P5/P6/P7 marcadas ✅** e "ADIADO cluster Estoque" **desadiado**. Detalhe: [session log p5](2026-07-02-p5-purchase-compras-merge.md). Docs-only; RUNBOOK/Schema gates verdes; auto-merge armado.

### 2. Correção do Wagner sobre custo (importante — pega antes da Onda 1)
Wagner: *"o custo já tem coisa pronta, estais desatualizado"*. Minha afirmação recorrente de que **custo médio ponderado móvel é gap** está **STALE**. Custo NÃO é greenfield — já existe: ComVis `cv_substratos.preco_custo_m2`, Compras `purchase_price`, Manufacturing `averageProductionCost`, OficinaAuto item cost, UltimatePOS `default_purchase_price`/`last_purchased_price`. Detalhe + o que reverificar: [session log correção custo](2026-07-02-correcao-custo-estoque-nao-greenfield.md).

## Implicação pra retomar (CRÍTICO)

**NÃO desenhar a Onda 1 (`stock_movements` + custo médio) assumindo greenfield de custo.** Primeiro pinar com Wagner/código o mecanismo de custo que ele considera pronto — senão eu reconstruo algo existente (viola "comparar e não duplicar"). A afirmação stale vive no plano/mapa do branch [#3672](https://github.com/wagnerra23/oimpresso.com/pull/3672) (não mergeado) — corrigir lá antes de mergear o plano.

## Lições

- **Domínio: veredito do Wagner vence inferência de estudo/agente** (precedente ADR 0265 "locação é alucinação"). Estudo externo + mapa afirmaram "custo médio é gap"; o dono diz que não. Material inferido vira stale-a-corrigir, não fonte.
- **Não repetir claim de gap sem reverificar contra o código atual** — especialmente claims herdados de estudo externo que envelhecem quando o produto evolui.

## Persistência
- git: `claude/estoque-p5-purchase-compras` (#3684) + este handoff/logs em `claude/estoque-correcao-custo-handoff`.
- MCP: propaga ~2min após merge.

## Pointers
- P5: [session log](2026-07-02-p5-purchase-compras-merge.md) · Onda 0: [handoff 2245](2026-07-02-2245-onda0-consolidacao-estoque.md)
- Correção custo: [session log](2026-07-02-correcao-custo-estoque-nao-greenfield.md)
