---
date: "2026-08-13"
time: "1815 BRT"
slug: "catalogo-v2-porte-completo-trava-visual"
tldr: "Porte da v2 do Catálogo (/products/unificado) COMPLETO no PR #5756 — 10 commits, 114 checks verdes. Trava só na aprovação visual do [W]: diff 2,2260% > τ_alto 2%, e acima de τ_alto o label não basta (precisa Run workflow na branch + aprovação F1.5). O vermelho `crons de governança vivos?` é ambiente e NÃO é required."
decided_by: [M]
cycle: null
prs: [5756, 5753, 5745, 5597]
us:  ["US-PROD-023"]
next_steps:
  - "[W]: Actions → visual-regression → Run workflow na branch feat/catalogo-v2-pt01 (regenera baselines) → mergear o PR de baselines → mergear o #5756"
  - "Consertar o cron mv-metabolismo.yml, que quebrou 10:36 e derruba `crons de governança vivos?` em TODO PR do repo"
  - "Autor do #5597: os UC-PUNI-02b e 03 passam VAZIOS (o teste não semeia venda nem SellingPriceGroup) — conserto muda o que o teste afirma, é chamada dele"
related_adrs: ["0130-handoff-append-only-mcp-first", "0185-drawer-760-canon-entidades-cadastrais", "0253-primitivos-layout"]
---

# Handoff 2026-08-13 18:15 BRT — Catálogo v2: porte completo, travado na foto

## TL;DR

O porte da v2 do Catálogo está **completo e verde** no [#5756](https://github.com/wagnerra23/oimpresso.com/pull/5756). O que falta não é técnico: o `visual-regression` acusou **2,2260% > τ_alto 2%**, e acima de τ_alto a etiqueta sozinha não resolve — precisa regenerar a baseline **+ aprovação [W]** (F1.5). Não fiz nenhum dos dois: regenerar baseline no próprio PR é aprovar o próprio desenho.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 11:00 | Briefing pro Claude Design a partir da tela de referência `/contacts` |
| 12:30 | #5709 (rota morta nos charters do Produto) — mergeado por [W] |
| 13:39 | #5597 auditado: **3 defeitos reais** achados e corrigidos |
| 15:00 | Medição dos 7 UC-PUNI rodando a lane por `workflow_dispatch` |
| 17:48 | #5745 (PT-01) e #5753 (âncora) — mergeados por [W] |
| 18:15 | #5756 fechado: 10 commits, porte completo |

## Estado atual dos artefatos

### Entregue nesta sessão

| PR | O quê | Estado |
|---|---|---|
| [#5709](https://github.com/wagnerra23/oimpresso.com/pull/5709) | 14 ocorrências de `/produto/unificado` — rota que não existe | MERGED |
| [#5745](https://github.com/wagnerra23/oimpresso.com/pull/5745) | PT-01 mandava importar `ModuleTopNav`, que **nunca existiu** | MERGED |
| [#5753](https://github.com/wagnerra23/oimpresso.com/pull/5753) | âncora do charter apontava pro protótipo **descartado** | MERGED |
| [#5756](https://github.com/wagnerra23/oimpresso.com/pull/5756) | porte da v2 — 10 commits | **OPEN, travado** |

### O que o #5756 entrega

Busca + filtro de categoria + chips + `/` · **paginação real** (o `limit(500)` cortava em silêncio) · 5 KPIs clicáveis que **calculam de verdade** (`populares` e `sem_giro` eram `0` chumbado desde o scaffold) · ordenação SKU/Produto · **drawer 760** (ADR 0185) · `Inertia::defer` + skeleton (era **zero** no controller) · escada tipográfica `10·11·12·13·14·16·18·22` (havia **43% dos elementos em meio-pixel**).

Garantia que sustenta os KPIs: `aplicarRecorte()` é **um** método usado pelo `count()` do card **e** pelo `where` da lista — se fossem queries separadas, o card diria "12" e a lista mostraria 9.

## Três desvios do protótipo, todos medidos

1. **Sem `min-width: 1014px`** — medido na tela real: o shell ocupa **260px fixos**, então em 1280 sobram 1020 − 48 de respiro = **972**. A tabela entraria em rolagem horizontal. A `/contacts` não tem min-width: comprime.
2. **Sem seções BOM/Histórico no drawer** — `bomCount` é `0` literal e não há histórico por produto. Seção vazia afirmaria *"não tem composição"*, ≠ *"ainda não sabemos"*.
3. **Sem seta** nas colunas que o servidor não ordena.

## Achados de fundo (fora do escopo, registrados)

- **A rampa `--fs-*` do DS v6 é órfã.** Ela existe em `resources/css/` (`10.5 / 11.5 / 12.5 / 13.5 / 15 / 18 / 22 / 28 / 38`, DTCG + Style Dictionary, "do not invent values"), mas **0 telas React consomem `var(--fs-*)`** e **199** usam valor arbitrário — inclusive a referência `Cliente/Index.tsx`. Consequência real: um handoff de design levou o agente a **redefinir os tokens dentro da página** pra casar com a referência.
- **Os UC-PUNI-02b e 03 do #5597 passam VAZIOS.** O teste não semeia venda nem `SellingPriceGroup`, então a lista chega vazia com gate ou sem gate. Os dois estavam marcados como *"vermelho esperado"* e vieram verdes — a divergência entre previsão e medição é o alarme. Diagnóstico no PR.
- **`PT-01` citava a ADR 0040 como respaldo** da regra "sub-tabs no header". Essa ADR existe mas é sobre **policy de publicação**; o documento citado nunca existiu. Corrigido no #5745 sem inventar substituto.

## Estado MCP no momento do fechamento

`cycles-active` **timeout** (MCP não respondeu — registrado como falha de consulta, não como ausência de cycle). Levantado por fora:

- **Handoffs irmãos do dia**: `2026-08-13-1330-jana-dark-e-a-ancora-que-mentia`, `2026-08-13-1520-ancora-jana-consertada-p2-revertido`, `2026-08-13-2056-espelho-cowork-medir-vs-consertar`
- **PRs meus hoje**: #5756 OPEN · #5753, #5745, #5709, #5597 MERGED
- **`whats-active`**: respondeu **CEGA** — `fresh=0 · stale=0 · dead=95`, o pipeline de ingest sem heartbeat. Não tratei "nenhuma sessão" como escopo livre; medi por fora (varredura de `git status` em todas as worktrees: **zero** trabalho não commitado na tela).

## Pro próximo

1. **O merge depende de 2 atos do [W]**, nesta ordem: `Actions → visual-regression → Run workflow` na branch `feat/catalogo-v2-pt01` (regenera baselines no runner canônico e abre PR), mergear esse PR, e então mergear o #5756.
2. **Ignorar o `crons de governança vivos?`** — o cron `mv-metabolismo.yml` quebrou às 10:36 e derruba esse check em **todo PR do repo**. Não está entre os 45 required de `governance/required-checks-baseline.json`; [W] mergeou #5745 e #5753 com ele vermelho.
3. **⚠️ Medição velha:** a corrida dos UC-PUNI que mostrou vazamento de custo/preço rodou **antes** do #5733 (mergeado 15:04 UTC), que fechou o gate. Não citar aquele resultado como estado atual.
4. Página de aprovação montada pro [W] (antes/depois + mapa de diff + os 4 passos): https://claude.ai/code/artifact/a32f2e89-167e-4910-957c-c5b50d223e84

## Erros meus nesta sessão

- **Empurrei com o gate vermelho.** Rodei `node gate.mjs | tail -2 && git commit` — num pipeline o `$?` é do `tail`, que sai 0. É a lápide §5 2026-08-13 de `proibicoes.md`, que eu tinha lido no mesmo dia. Corrigido no commit seguinte.
- **Chamei de "conflito" o que era risco não medido**, e perguntei ao [M] algo que eu podia medir sozinho (as worktrees estão todas no disco).
- **Duplicei parte do #5597** por não varrer os PRs abertos antes de abrir o #5709. Declarado no PR; merge resolveu limpo (mudança idêntica).
- **Instruí o Claude Design a "sumir com o meio-pixel"** sem saber que 10,5/11,5/12,5 são a rampa oficial do DS. Ele cumpriu **redefinindo os tokens dentro da página** — pior que o problema. Corrigido, e virou o achado da rampa órfã.
