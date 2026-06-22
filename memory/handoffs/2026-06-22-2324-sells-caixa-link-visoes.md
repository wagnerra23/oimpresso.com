---
date: 2026-06-22
time: "2324 BRT"
slug: "sells-caixa-link-visoes"
tldr: "1a fatia da tela de Vendas pelo fluxo aplicar-prototipo: link 'Caixa do dia' -> /vendas/caixa adicionado ao dropdown Visoes de Sells/Index (tela viva mas orfa de navegacao, ADR 0192 Onda 6). Refaz o PR #3231 (fechado por base stale + casos stale) corretamente: branch fresh off origin/main + casos-gate honesto (last_run->06-22, UC-S10 OK->testing). PR #3234 MERGED (c200fb0184), deployado, verificado ao vivo na prod (dropdown + rota). Footgun do casos-guard (glifo em prosa flipava testing->green) ja consertado em main por #3241."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3234]
---

# Handoff — Sells: link "Caixa do dia" no dropdown Visoes

## Estado MCP no momento
- Cycle **CYCLE-08** "Receita — Onda A" · ~5d restantes · 82% decorrido. Esta fatia e UI (off-goal direto do cycle, mas destrava tela orfa).
- my-work @wagner: 30 ativas — nenhuma desta sessao (trabalho veio de prompt/handoff decodificado, sem task MCP criada).

## O que aconteceu
- **Objetivo**: aplicar a 1a fatia da tela de Vendas (fluxo `aplicar-prototipo` Fase 4). A tela `Sells/Caixa/Index` (`/vendas/caixa`, `SellController@inertiaCaixa`, ADR 0192 Onda 6) ja existia viva e testada (`SellsCaixaPageTest`) mas estava **orfa de navegacao**.
- **Mudanca** (1 linha): `{ id: 'caixa', label: 'Caixa do dia', href: '/vendas/caixa' }` como 1o item do array do dropdown "Visoes" em `Sells/Index.tsx` (~linha 1255, antes de Orcamentos).
- **Refez o PR #3231** (fechado por 2 gates corretos), agora pelo caminho certo.

## Os 2 gates do #3231, fechados honesto
- **Preflight**: worktree isolada **fresh off `origin/main`** (nunca commitei na worktree frosty-greider, suja de outras sessoes).
- **Casos-coverage (ADR 0264)**: `Sells/Index.casos.md` -> `last_run` 06-11->06-22 (G-6 frescor) + `UC-S10` verde->testing honesto (G-7 status derivado). Sem prova fingida, sem `casos:baseline:write`.
- **Footgun pego em CI**: `declaredStatus()` do guard casava o glifo verde em QUALQUER lugar da linha `Status:` e o checava primeiro -> um glifo verde em prosa flipou meu testing pra green e falhou o gate. Contornei deixando a linha `Status:` so com o glifo testing. **Ja consertado em main por #3241** ("G-7 le so o primeiro glyph do Status") — o chip de follow-up que abri ficou redundante.

## Artefatos
- PR **#3234** MERGED squash `c200fb0184` -> main (deployado Hostinger, HEAD deployado `2e4552a789`).
- 2 arquivos: `resources/js/Pages/Sells/Index.tsx` (+1 link) · `resources/js/Pages/Sells/Index.casos.md` (last_run + UC-S10).
- CI: 20/20 required verdes (incl. `visual-regression`, `E2E`, `Casos-coverage`) + Preflight advisory verde.

## Persistencia
- git: este handoff (PR docs proprio) · MCP: webhook GitHub->MCP ~2min pos-merge · BRIEFING: nao tocado (link de nav, baixo valor).

## Verificacao ao vivo (portao visual Fase 5)
- Wagner logado (WR2 Sistemas) -> `/sells` -> dropdown Visoes mostra "Caixa do dia" em 2o (apos "Lista de vendas · AQUI") -> clique roteia pra `/vendas/caixa` e renderiza a tela real (Caixa aberto #2, KPIs, conferencia). **Aprovado ao vivo** via browser MCP.

## Proximos passos pra retomar
- Proxima fatia da tela de Vendas: **split fiscal no drawer (UC-V05)** — cards NF-e/NFS-e, nucleo do charter. Depois ranking de vendedores, pendentes-por-vendedor, card IA.
- GAP-SPEC completo: `memory/requisitos/Sells/vendas-gap.md`.

## Licoes
- `casos:check` local roda **shallow** -> G-6/G-7 dormem e dao verde enganoso; CI roda `fetch-depth:0` onde mordem (foi o que matou o #3231). Raciocinar o caso full-history ANTES de commitar.
- Doc-only Fase 5 (SYNC_LOG/charter/brief-update) pulado conscientemente pra link de 1 linha — confirmado por Wagner.

## Pointers
- [vendas-gap.md](../requisitos/Sells/vendas-gap.md) · ADR 0264 (casos/trio) · ADR 0192 (Caixa Onda 6) · skill `aplicar-prototipo` + `cowork-prototype-replication`.
