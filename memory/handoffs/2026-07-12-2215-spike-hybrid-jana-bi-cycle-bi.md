---
date: "2026-07-12"
time: "22:15 BRT"
slug: spike-hybrid-jana-bi-cycle-bi
tldr: "US-COPI-133 aberta (descongelar Jana-BI, recall 0,38→0,60, CYCLE-BI-01 segue planning). Spike hybrid A/B no CT100: context_recall 0,3939→0,4506 (+0,057) — NÃO chega a 0,60; reabertura sozinha não é o PR. Gargalo dominante achado no código: renderFontes corta docs a 400 chars. Régua 0318 descoberta órfã → US-COPI-134."
decided_by: [W]
related_adrs: [0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio, 0318-ragas-eval-real-mata-tautologia-ct100-staging, 0312-decisions-search-fulltext-hybrid-docs-off]
next_steps: ["US-COPI-133 etapa 1: excerpt/chunk query-aware no KbAnswerService::renderFontes + re-medir", "US-COPI-134: instalar runner dos schedules staging no CT100 (mexeu-registra)", "Wagner: revisar/mergear PR desta sessão"]
---

# Handoff 2026-07-12 22:15 — Spike hybrid Jana-BI (US-COPI-133) + régua 0318 órfã

## Estado MCP no momento do fechamento
- `cycles-active` (COPI): **nenhum cycle ATIVO** — CYCLE-BI-01 segue **planning** de propósito (decisão registrada: `client_signal` só faz sentido depois que a Larissa usar; não ativar).
- `my-work` @wagner: 30 tasks (9 review, 8 blocked, 13 todo) — inalterado vs handoff 2026-07-10-1443.
- `brief-fetch`: OK via curl JSON-RPC (harness MCP off nesta sessão; hook SessionStart falhou no parse).
- Handoffs irmãos de hoje: `-1250` charters, `-2200` matriz onboarding, `-2245` Jana medição honesta. Meu tema (spike hybrid CYCLE-BI-01) é novo.

## O que aconteceu (resumo)
Retomada do CYCLE-BI-01 conforme ranking adversarial da grade (item #5, único caminho pro 🔴 `inteligencia-de-negocio`). As 3 ressalvas do adversário foram honradas: (a) número novo — **US-COPI-133** (132 estava ocupado pelo Langfuse); (b) spike hybrid ANTES de bipartir corpus; (c) bipartição do eval anotada junto com a do corpus.

**Spike A/B (CT 100 staging, N=51, mesma régua ADR 0318, ~USD 0,17):**

| métrica | OFF | ON | Δ |
|---|---|---|---|
| faithfulness | 0,7355 | 0,7675 | +0,032 |
| relevancy | 0,8784 | 0,8510 | −0,027 |
| **context_recall** | **0,3939** | **0,4506** | **+0,057** |

**Decisão com número:** 0,45 ≪ 0,60 → reabrir hybrid sozinho NÃO vai pra prod (reproduz o A/B de 04/jul 0,395→0,422). **Causa dominante no código:** `KbAnswerService::renderFontes()` corta cada doc aos primeiros 400 chars (`extrairExcerpt`) — síntese e juiz nunca veem o fato se ele mora no meio da ADR (item (b) pendente da condição de reativação da ADR 0312). Ordem de ROI recalibrada na US-COPI-133: **1) excerpt query-aware → 2) camadas 2-3 US-COPI-130 → 3) bipartição corpus+eval**.

**Achado colateral (US-COPI-134):** régua semanal do 0318 + recall-eval (`environments(['staging'])`) **sem runner** no CT 100 (container sem `schedule:run`; host só tem o publisher dom 08:30). Trend órfã parada em 2026-06-28; `latest.json` era de rodada manual de 04/jul.

## Estado do staging (deixado limpo)
Flag `JANA_MCP_SEARCH_PIPELINE_DOCS` NÃO persistida (só `docker exec -e` por rodada — verificado `printenv` ausente pós-spike). `storage/app/governance/ragas-real-eval-latest.json` restaurado = run OFF de hoje (0,7355/0,8784/0,3939) — trend não poluído. Raw em `/root/ragas-spike-20260712/`.

## Pendências pra quem retomar
1. **US-COPI-133 etapa 1** (excerpt/chunk query-aware) — mexe em `Modules/Jana/Services/Kb/KbAnswerService.php`; pré-flight Modules/Jana obrigatório; re-medir com `jana:ragas-real-eval` (N=51) antes→depois.
2. **US-COPI-134** (runner dos schedules staging) — sem ele a régua do 0318 só roda na mão.
3. `tasks-comment` na US-COPI-133 com o resultado do spike **não foi possível** antes do merge (task só materializa no DB via webhook pós-merge do SPEC) — o resultado está gravado no próprio bloco do SPEC; se quiser o comment no timeline, postar após o merge.
4. Gates humanos do 0334 (confiabilidade → mão da Larissa → `client_signal`) — com Wagner. NÃO reabrir Onda 6/ADS/autonomia.

## Pointers
- Session log: [2026-07-12-spike-hybrid-jana-bi-us-copi-133.md](../sessions/2026-07-12-spike-hybrid-jana-bi-us-copi-133.md)
- SPEC: `memory/requisitos/Jana/SPEC.md` §US-COPI-133 e §US-COPI-134
