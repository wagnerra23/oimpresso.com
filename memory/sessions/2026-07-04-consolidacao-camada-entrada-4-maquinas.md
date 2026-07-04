---
slug: 2026-07-04-consolidacao-camada-entrada-4-maquinas
title: "Consolidação da camada de entrada + 4 máquinas de governança de conhecimento"
type: session
date: "2026-07-04"
tags: [governanca, memoria, anti-drift, memory-health, fact-anchor, estado-da-arte]
pii: false
---

# Sessão 2026-07-04 — Guia do sistema, faxina da camada de entrada e 4 máquinas novas

## Origem
Wagner pediu "um guia ou mapa do sistema" → virou uma sessão de **governança de conhecimento**: criar a porta de entrada, limpar o drift acumulado, e — decisivo — **construir as máquinas** que impedem o drift de voltar (não faxina manual). Fechado com comparação contra o estado-da-arte 2026.

## O que foi entregue (8 PRs)

| PR | Entrega |
|---|---|
| [#3795](https://github.com/wagnerra23/oimpresso.com/pull/3795) | `memory/GUIA-DO-SISTEMA.md` — porta de entrada humana (produto + como operar com Claude Code), link-heavy (delega detalhe, não apodrece) |
| [#3797](https://github.com/wagnerra23/oimpresso.com/pull/3797) | Esquece `memory/memory_backup/` (32 arquivos de backup morto ressuscitado pelo squash #2413) |
| [#3798](https://github.com/wagnerra23/oimpresso.com/pull/3798) | README fresco: React 18→19, MemCofre→SRS, testes local→CT100, "95+ ADRs"→índice gerado; +ponteiro pro guia no INDEX/ARCHITECTURE |
| [#3800](https://github.com/wagnerra23/oimpresso.com/pull/3800) | Esquece `05-preferences` (única contradição ATIVA — "decida não pergunte"); os outros 6 NN ficam banner-quarentenados (red-team rejeitou bulk-delete) |
| [#3799](https://github.com/wagnerra23/oimpresso.com/pull/3799) | **Check S** — sentinela de frescor da camada de entrada (idade) |
| [#3802](https://github.com/wagnerra23/oimpresso.com/pull/3802) | **Check T** (fact-anchor determinístico) + **Check U** (limbo) + fix bug real `Modules/Project`→`ProjectMgmt` |
| [#3803](https://github.com/wagnerra23/oimpresso.com/pull/3803) | **Check V** — links internos quebrados na canon (guard SOTA lychee) |
| [#3804](https://github.com/wagnerra23/oimpresso.com/pull/3804) | Conserta 23 links de ADR quebrados (slug antigo → real, determinístico) |

## As 4 máquinas novas (em `scripts/governance/memory-health.mjs`, advisory)
- **Check S** `entrada-stale` — doc da camada de entrada sem revisão > 6 meses (frescor por idade).
- **Check T** `fato-ancora-drift` — versão (React/Laravel) ou `Modules/<X>` em doc **current-state** que contradiz a fonte-de-verdade (`package.json`/`composer.json`/árvore). Calibrado 18→escopado só a docs current-state (ARCHITECTURE/INDEX têm menção histórica legítima = FP).
- **Check U** `proposta-em-limbo` + `dir-homonimo` — pile de drafts em `proposals/` (contagem, idade mascarada pelo squash #2413) + dirs homônimos (`dominio/`÷`dominios/`).
- **Check V** `link-quebrado` — links internos quebrados na canon front-facing (determinístico, zero-FP).

Todos com teste físico em `tests/memoryHealth.spec.ts` (ADR 0258: check visto falhar+passar antes de valer). Warn-only (ADR 0275/0314 — required = só Tier-0).

## O que as máquinas DESCOBRIRAM (o "já não funciona") na 1ª rodada
- **Check T:** `what-oimpresso` mandava imitar `Modules/Project/` (fantasma; existe `ProjectMgmt`) — **corrigido**.
- **Check V:** 47 links mortos na canon → 23 consertados (#3804), **24 restam** (não-ADR).
- **Check U:** 90 drafts em `proposals/` (só 0204 era sobra promovida); `dominio/`÷`dominios/`.
- Pré-existentes que a máquina já rastreava (surfaçados juntos): 221 scorecards stale (B), 42 sessões sem âncora (K), 16 planos sem `## Status vivo` (J), 12 ADRs revisão-vencida (R), 5 mortas-citadas (O).

## Comparação com estado-da-arte 2026
Doc: [`2026-07-04-arte-governanca-conhecimento-fato-vs-frescor.md`](2026-07-04-arte-governanca-conhecimento-fato-vs-frescor.md). Veredito: oimpresso **à frente** do SOTA público em frescor-por-idade + anti-teatro de gate + symbol-drift; **atrás junto com todo mundo** em fato-errado/contradição (fronteira sem solução barata — Dosu: "detecta drift, não correção"). Fact-anchor resolve o **subconjunto ancorável** sem LLM.

## Backlog exposto (precisa de JULGAMENTO — não bulk)
1. **24 links quebrados** restantes (não-ADR: sessions/arquivos movidos/cross-tree). Subconjunto tratável: **resolução por basename único** (arquivo existe, caminho errado) — QUEUED como próximo passo determinístico (fazer após #3804 mergear, evita conflito em arquivos compartilhados).
2. **90 drafts** em `decisions/proposals/` — triar (promover/arquivar/esquecer, ADR 0316).
3. **`dominio/` ÷ `dominios/`** — rename de desambiguação (alto raio: 425 arquivos em `dominios/wr-comercial`; qual nome ganha = decisão Wagner).
4. **6 NN banner-quarentenados** (01/02/03/04/07/09) — ficam como história honesta; esquecer só se Wagner quiser (custo>ganho, red-team).
5. 221 scorecards / 42 sessões / 16 planos / 12 ADRs / 5 mortas-citadas — julgamento ou CT100 (re-grade).

## Decisões/lições da sessão
- **Esquecimento é decisão consciente e do Wagner** (ADR 0316) — red-team rejeitou bulk-delete dos 7 NN; só a contradição ativa (05) morreu.
- **Fact-anchor de prosa tem limite** — distinguir claim atual de citação histórica não é 100% determinístico; por isso advisory, escopado a docs current-state.
- **Idade por git-date é mascarada pelo squash-restore #2413** — para proposals, o sinal honesto é contagem, não idade.
- **Máquina não conserta, ela torna auditável** — drift silencioso virou drift nomeado e contado; consertar é triagem humana.
