# Handoff 2026-06-01 — Jana Pro paywall (F3) + 3 champion-makers IA

> **Autor:** [CL] Claude Code (Opus 4.8) · **Aprovador:** [W] Wagner
> **Sessão:** design-handoff Cowork "Jana Pro - Paywall CC" → implementação + champion-makers IA

## O que entrou em prod (`main`)

| PR | Tema | Estado |
|---|---|---|
| [#2069](https://github.com/wagnerra23/oimpresso.com/pull/2069) | **Jana Pro paywall** `/ia/pro` (F3 do design Cowork, gate PASS 90) + 2 entry-points (Dashboard + ghost sub-nav) | ✅ merged |
| [#2070](https://github.com/wagnerra23/oimpresso.com/pull/2070) | **TAREFA 3** — `jana:health-check` check 10 `memoria_recall_backend` (alarme antes da degradação silenciosa do Meilisearch) + Pest | ✅ merged |
| [#2071](https://github.com/wagnerra23/oimpresso.com/pull/2071) | **TAREFA 2** — +15 casos golden anti-alucinação derivados de erros REAIS (`feedback-*.md`), gold-set 100→115 | ✅ merged |

## Jana Pro paywall (origem da sessão)

Design Cowork `Jana Pro - Paywall CC.html` (champion-maker nº1, gate F1.5 **PASS 90**) traduzido pra Inertia/React. **Nota de governança:** o [W] tinha segurado o F3 ("ainda não quero") no chat de design; **liberou explicitamente nesta sessão** antes da entrega. Billing real (Asaas) continua sendo Sprint JANA-B (ADR 0140) — a CTA é mock client-side fiel ao protótipo.

- `resources/js/Pages/Jana/Pro.tsx` (modo FOCO, tokens canon, `text-success`) + `Pro.charter.md`
- `Modules/Jana/Http/Controllers/ProController.php` + rota `jana.pro.index`
- Entry-points: botão na Dashboard Jana + ghost `Jana Pro` no DataController (sub-nav hub IA)

## Champion-makers IA (handoff "Avaliação de Estrutura de IA")

Protocolo: PASSO 0 re-ancorar em `origin/main` fresco · não recriar · aditivo autônomo (CI verde → merge) · **Tier 0 espera [W]**.

- **TAREFA 1 (Purge LGPD + OTel)** — ✅ aditivo **já estava pronto** no `main` (`RetentionPurgeCommand` + `RetentionPurgeService` + Pest). PASSO 0 pegou; não recriei.
- **TAREFA 3 (Resiliência Meilisearch)** — ✅ #2070. `McpMemoriaDriver` já degradava gracioso; faltava o alarme no health-check (era queda silenciosa). Bônus: corrigi brittleness do smoke test (cravava 8, virou subset robusto).
- **TAREFA 2 (Golden RAGAS)** — ✅ #2071. [W] pediu pra varrer a `memory/` por erros reais → 15 dos 51 `feedback-*.md` viraram casos anti-alucinação (`must_not_contain` = o erro, `must_contain` = a regra). Pest 10/10 (1249 assertions).

## ⏳ Aberto — Tier 0 (esperam decisão [W], custo dinheiro/infra)

1. **T1:** `JANA_RETENTION_ENABLED=true` em prod (pós-canary 7d) + subir OTel collector CT 100 (Jaeger/storage)
2. **T2:** subir cadência RAGAS real-mode semanal→diário (~R$ [redacted Tier 0]/mês) + apertar thresholds (rodar eval antes pra provar baseline não fica vermelho)
3. **T3:** réplica/HA do Meilisearch (só se [W] quiser eliminar o ponto único de falha — tem custo)

## Refs
- ADR 0140 (Jana Pro SaaS) · 0110 (Cockpit V2) · 0182 (ghosts) · 0093 (Tier 0) · 0190 (primary roxo)
- `memory/reference/feedback-*.md` (registro de erros → fonte dos casos golden)
- Design bundle Cowork: `prototipos/jana-pro/` (COMPARISON.md + critique-score.json)
