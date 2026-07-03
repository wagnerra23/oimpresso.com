---
date: '2026-07-03'
slug: onda11-capterra-sells-backlog
topic: "Handoff Onda 1.1 Sells — CAPTERRA de capacidade (nota 60) materializada em backlog MCP + US-SELL-040 repriorizada P0/desgated"
tldr: "Onda 1.1 Sells - ficha de capacidade (nota 60/100, #3699 merged); 4 gaps novos viraram US-SELL-054..057 (#3702 merged) + US-SELL-040 subiu pra P0 sem gate de refactor (#3704, aguarda merge). A nota 90 e design, cega a calculo/fiscal/offline."
authors: [C]
prs: [3699, 3702, 3704]
---

# Handoff — Onda 1.1 (CAPTERRA de capacidade Sells) → backlog materializado

> **Sessão:** 2026-07-02 → 2026-07-03 (continuação pós-compact) · worktree `vigorous-cannon-62050e`
> **Escopo:** Onda 1.1 do programa-ondas (adversário concorrente Sells) + materialização dos gaps em backlog MCP.
> **Regra-âncora:** read-only research → docs-only. Zero código de produto.

## TL;DR

3 PRs. **#3699 (MERGED)** gerou a `CAPTERRA-FICHA.md` de **capacidade** do Sells (nota **60/100**) — a nota 90 do screen-grade é de **design**, cega a cálculo/fiscal/offline/pagamento. **#3702 (MERGED)** materializou os 4 gaps novos em US (054-057, `parent_plan: programa-ondas-onda-1-sells`), sem duplicar os 2 que já existiam (comentário DB-only em US-SELL-040 e 041). **#3704 (verde, aguarda merge Wagner)** subiu US-SELL-040 pra **P0 e removeu o gate de refactor** — o teste E2E de cálculo estava travado atrás de "só quando refatorar store()", justo o gate que deixou o incidente R$×100k (2026-06-05) passar.

## O que foi feito (3 PRs)

1. **[#3699](https://github.com/wagnerra23/oimpresso.com/pull/3699) MERGED** — `CAPTERRA-FICHA.md` de **capacidade** do Sells (nota **60/100**) + session log `2026-07-02-capterra-sells.md`. Benchmark vs 13 concorrentes de PDV (Bling/Tiny/Omie/Conta Azul/Shopify POS/Square/Stripe Terminal/Lightspeed/Nex/Hiper/Linx/Clover/Toast), 19 capacidades P0-P3, cálculo ponderado (P0=4/P1=2/P2=1/P3=0.5). **Achado central (o que a nota 90 esconde):** a 88-90 é screen-grade de **design**, cega a cálculo/fiscal/offline/pagamento; capacidade real é 60.

2. **[#3702](https://github.com/wagnerra23/oimpresso.com/pull/3702) MERGED** — materializa no `Sells/SPEC.md` os **4 gaps NOVOS** (via `tasks-create` MCP, IDs 054-057 na sequência após 053, sem colisão):
   - **US-SELL-054** (p1, 40h) Offline-first IndexedDB — fecha C05=2
   - **US-SELL-055** (p1, 20h) Pix QR + webhook auto-reconcile — fecha C04=5
   - **US-SELL-056** (p2, 8h) Keyboard-first coeso — fecha C07=5
   - **US-SELL-057** (p2, 3h) Skeleton Create + INP<200ms — fecha C13=5
   - Todas com `parent_plan: programa-ondas-onda-1-sells`.
   - **Não duplicou** (regra Tier 0): os outros 2 gaps já tinham task → só comentário DB-only:
     - **G-01** → **US-SELL-040** (comentado: adversário argumenta P0, não P2)
     - **G-03** → **US-SELL-041** (comentado: falta contingência `tpEmis=9`)

3. **[#3704](https://github.com/wagnerra23/oimpresso.com/pull/3704) VERDE, aguardando merge Wagner** — US-SELL-040 → **P0 + remove o gate de refactor**. Ao abrir a task descobri que o teste E2E de cálculo estava (a) p2 e (b) travado atrás de *"só fazer quando refatorar `SellPosController@store`"*. Esse gate é exatamente o que deixou o incidente **R$×100k (2026-06-05, biz=4)** passar: invariante estrutural é tautológica (strpos no source, proibicoes §5) e o canary humano biz=1 não pega inflação. Reescrita: fazer **já** contra o `store()` legado como está; a rede de segurança de valor precisa existir **independente** do refactor.

## Achado adversarial que sobrevive (perene)

O `90 Leader` diz "linda e usável"; a capacidade (60) diz "ainda sem prova de que a conta fecha, sem rede fiscal/offline". **Gap #1 (G-01, teste E2E de cálculo)** é a rede de segurança mais barata contra um 2º incidente de valor — e estava mal-priorizada + gated. Corrigido em #3704 (aguarda merge).

## Pendências pro próximo

1. **Wagner mergear #3704** — pra US-SELL-040 cair como P0/desgated no DB (webhook).
2. **Onda 1.2 / 1.3** do programa-ondas Sells (se existirem no plano) — não tocadas nesta sessão.
3. Os 4 US novos (054-057) sincronizam pro DB no próximo webhook pós-merge de #3702 (já mergeado).

## Estado MCP no momento do fechamento

- **cycles-active (COPI):** *Nenhum cycle ATIVO.*
- **my-work @wagner:** 30 tasks ativas (8 review, 8 blocked, 14 todo). P0 em aberto no Sells: US-SELL-036 (FSM rollout), US-SELL-009 (cutover Blade), FORJA-142 (Create piloto). US-SELL-040 vira o 4º P0 do Sells após merge de #3704.
- **sessions-recent:** último handoff = `2026-07-02-2330-balde-d-sdd-alavancas-roadmap.md` (BALDE D anchors + SDD composto 79).

## Refs

- `memory/requisitos/Sells/CAPTERRA-FICHA.md` (§8 leitura adversarial · §6 gaps G-01..G-06)
- `memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.1-adversario-capterra.md`
- [ADR 0089](../decisions/0089-capterra-driven-module-evolution.md) Capterra-driven · [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) tests biz=1 · [ADR 0284](../decisions/0284-pipeline-incidente-graduado-confianca.md) incidente graduado · [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) cliente como sinal
