---
date: "2026-06-30"
time: "17:20 BRT"
slug: import-bundle-comvis-deteccao-charter-mis-anchor
tldr: "Import bundle Cowork ComVis -> maquina de deteccao charter-first (bundle_source/visual_source, #3431/#3432) + reconciliacao OS OficinaAuto; mis-anchor de C pego antes de quebrar Repair (#3433 merged + #3446 correcao)."
decided_by: CC
cycle: CYCLE-08
prs: [3431, 3432, 3433, 3446]
---

## TL;DR
[W] "importa esse zip" virou conserto da máquina de detecção (charter-first via `bundle_source`/`visual_source`, #3431/#3432 merged) + reconciliação OS OficinaAuto (#3433 merged). Ao executar a migração "deprecar C" (PR #3446), o handler real do Sells mostrou que `Repair/ProducaoOficina` (C) é Repair/JobSheet, **não** duplicata — deprecar quebraria o Repair. Corrigido o mis-anchor sem deprecação. Lição-mãe: ler o handler real antes de editar pegou um erro de inventário antes de quebrar prod.

## Estado MCP no momento do fechamento
- **Cycle:** CYCLE-08 "Receita — Onda A" · 2d restantes · off-cycle (esta sessão é housekeeping de detecção/governança, 0% commits no cycle — esperado).
- **HITL pending [W]:** 2 (FIN-004 cobrança ROTA LIVRE · runbook on-prem pós-Gold).
- **Handoff irmão imediatamente anterior:** [2026-06-30 16:03 anti-bifurcação/armar-gates](2026-06-30-1603-anti-bifurcacao-armar-gates.md) — mesma sessão-dia, tema governança.
- **ADRs aceitas no intervalo:** 0312 (decisions-search FULLTEXT) — não tocada aqui.

## O que aconteceu
Wagner mandou **"importa esse zip do protótipo"** (bundle Cowork ComVis, handoff). O fluxo `aplicar-prototipo` rodou import (1220/1220 íntegros) + detecção. Daí a sessão virou **conserto da máquina de detecção** + uma **reconciliação de telas OficinaAuto** que terminou com um **erro meu pego antes de causar dano**.

1. **Máquina charter-first** (PRs **#3431 + #3432**, merged): `financeiro-page.jsx` caía em `A_CRIAR` (válvula de escape do gate) e silenciava o ledger vivo Unificado. Raiz: o link mockup↔tela mora no CHARTER, mas `detectar-telas`/`ancora.mjs` liam só `component:` (o .tsx) ou heurística `startsWith(dir)` — ambas falham quando o bundle nomeia pela RAIZ do módulo (`financeiro-page`) e a tela vive em sub-pasta (`Unificado`). Fix: campo estruturado **`bundle_source:`** + ler **`visual_source:`** (convenção já existente em 8 charters) nas DUAS máquinas; "alvo que existe ganha"; ALIAS 7→2; advisory A-CRIAR×dir-vivo-homônimo. 6 mockups passaram a resolver via charter.

2. **Reconciliação OS OficinaAuto** (PR **#3433** merged + **#3446** OPEN): mapear `oficina-page` (kanban) + `oficina-os-page` (drawer) revelou que o drawer rico **já está vivo** (`ServiceOrderRichSheet`, 11/13 partes). Deprecei o ghost `OficinaAuto/Os/` (#3433). **MAS** decidi (errado) que `Repair/ProducaoOficina` (C) duplicava `ServiceOrders/Board` (A) e ia deprecar.

3. **O erro pego (#3446):** ao COMEÇAR a migração, o handler real `Sells/Index.tsx:1571` mostrou que C serve **`Modules\Repair\Entities\JobSheet`** (vertical Repair, `OS-{id}`), distinto da OficinaAuto de veículo (`SO-{id}`). O Sells **já roteia certo por prefixo**. **C NÃO é duplicata — deprecar quebraria o Repair.** O bug real era um **mis-anchor**: o charter de C tinha `related_prototype: oficina-page.jsx` (mockup de veículo da OficinaAuto). Corrigido sem deprecação.

## Artefatos gerados
- **PRs:** #3431, #3432, #3433 (merged) · #3446 (OPEN — correção do mis-anchor).
- **Máquinas:** `prototipo-ui/detectar-telas.mjs` + `ancora.mjs` leem `bundle_source`+`visual_source` (+ refactor `_lib-charter.mjs` veio de outra sessão no main).
- **Charters:** `Os/Create` deprecated · `ServiceOrders/Show`+`Board` ganharam `visual_source`+`related_us` · `Repair/ProducaoOficina` mis-anchor removido + `related_us` real.
- **prod-flags.json:** `ServiceOrders/Show`+`Board` live biz=164 (Martinho).
- **Inventário:** [`RECONCILIACAO-os-inventario.md`](../requisitos/OficinaAuto/RECONCILIACAO-os-inventario.md) (com ERRATA no topo) + `kanban-producao-gap.md` + `os-drawer-build-map.md`.

## Persistência
- **git:** 4 PRs (3 merged + #3446 open). Webhook→MCP propaga em ~2min.
- **Staging do bundle:** `C:\Users\wagne\Downloads\_cowork-handoff-staging` (fixo, fora do repo).

## Próximos passos pra retomar
- **Mergear #3446** (correção do mis-anchor — gates verdes).
- **NÃO deprecar `Repair/ProducaoOficina`** (é Repair/JobSheet, não duplicata). Decisão "deprecar C" está VOID.
- Ganhos visuais do `oficina-page` a colher NO Board (A): busca multi-campo, 6 KPIs clicáveis, view Lista, placa Mercosul (`kanban-producao-gap.md`, ADOTAR-PARCIAL) — quando [W] abrir o foco, worktree fresco do `origin/main`.
- Débito separado (não desta sessão): 23 `locada`/`cacamba` no código vivo → `RUNBOOK-erradicacao-locacao.md`.

## Lições catalogadas
- 🛑 **Inventário com conclusão errada vira decisão errada.** Concluí "C duplica A" sem conferir o que C **serve**. O que salvou: **ler o handler real (`Sells/Index.tsx`) antes de editar** — pegou antes de quebrar o Repair em produção. Mesma família do "leia o log, não a conclusion" do handoff 16:03.
- **A verdade do link mora no CHARTER, mas a máquina tem que ler o campo certo** — `bundle_source`/`visual_source`/`related_prototype`, não só `component`. Bug financeiro = ler campo errado; mis-anchor de C = charter com campo CERTO mas VALOR errado.
- **`related_us` o lint só lê inline `[A,B]`**, não lista YAML bloco (gastei 1 iteração).
- **prod-flags = evidência, não palavra** — biz=164 grounded (BRIEFING + ADR 0171), não chute. `related_us` OficinaAuto sourced do SPEC; Sells/Produto/kb ficam SDD P10 (sem SPEC claro da tela).

## Pointers detalhados (on-demand)
- Inventário + plano (com errata): `memory/requisitos/OficinaAuto/RECONCILIACAO-os-inventario.md`
- Mapas Fase 1: `kanban-producao-gap.md` · `os-drawer-build-map.md` (mesmo dir)
- Charter do Board (reforça que C é board distinto): `resources/js/Pages/OficinaAuto/ServiceOrders/Board.charter.md` §"Contexto de domínio"
