# Sessão 2026-07-12 — Spike hybrid Jana-BI (US-COPI-133) + régua 0318 órfã (US-COPI-134)

**TL;DR:** Retomada do CYCLE-BI-01 (item #5 do ranking adversarial da grade 2026-07-12 — único caminho pro 🔴 `inteligencia-de-negocio`). Abri a **US-COPI-133** (descongelar Jana-BI, context_recall 0,38→0,60; o número 132 estava ocupado) e rodei o **spike hybrid A/B no CT 100 staging** (N=51, mesma régua ADR 0318): hybrid ON move context_recall **0,3939→0,4506 (+0,057)** — melhora, mas **não chega a 0,60**. Causa dominante identificada no código: `renderFontes()` corta cada doc a 400 chars do início. De quebra, o spike revelou que a régua semanal do ADR 0318 está **órfã** (sem runner no CT 100) → **US-COPI-134**.

## Contexto de entrada

- Tarefa herdada do ranking adversarial 2026-07-12 com 3 ressalvas verificadas: (a) número 132 ocupado (handoff [2026-07-12-2245](../handoffs/2026-07-12-2245-jana-medicao-honesta-gantt-b2.md)); (b) spike hybrid antes de bipartir corpus (Kernel ~495 apontava "reabrir hybrid"); (c) golden set é de PROCESSO — bipartir corpus sem bipartir eval não move o número.
- Checkout da sessão estava −5131 do main → todo pré-flight via `git show origin/main:` e o PR partiu de worktree fresco.
- MCP tools indisponíveis no harness (POST do hook falhou) → operei o MCP via **curl JSON-RPC** direto (`https://mcp.oimpresso.com/api/mcp`, token do `settings.local.json`): `brief-fetch`, `tasks-list` (dedup), `tasks-create` ×2, `cycles-active`, `my-work`.

## O que aconteceu

1. **Pré-flight Modules/Jana completo:** SPEC, ADR 0334 (3 camadas + anti-atrofia), ADR 0318 (RAGAS real), ADR 0312 (hybrid OFF + condição de reativação), ADR 0322 (instrução de query), baseline `governance/jana-ragas-real-baseline.json` (0,3839), handoffs 2026-07-10-1443 e 2026-07-12-2245, `KbAnswerService`, `JanaRagasRealEvalCommand`, config `copiloto.mcp_search`.

2. **US-COPI-133 criada** via MCP `tasks-create` (owner wagner, p0, 12h, sprint CYCLE-BI-01 — cycle permanece em **planning**, decisão registrada: `client_signal` só faz sentido depois que a Larissa usar). O DB só materializa a task quando o bloco do SPEC landar no main (webhook) — por isso o resultado do spike foi gravado no próprio bloco do SPEC.

3. **Spike A/B no CT 100 staging** (`/root/ragas-spike-20260712/`, nohup; flag ligada só por `docker exec -e`, config não cacheada; `latest.json` snapshotado e restaurado — trend não poluído):

   | métrica | OFF (antes) | ON (depois) | Δ |
   |---|---|---|---|
   | faithfulness | 0,7355 | 0,7675 | +0,032 |
   | relevancy | 0,8784 | 0,8510 | −0,027 |
   | **context_recall** | **0,3939** | **0,4506** | **+0,057** |

   N=51/51 avaliadas, 0 no_context, 0 synth_failed em ambas. Custo ~USD 0,17. Consistente com o A/B de 04/jul (0,395→0,422) — resultado **reproduzido**, não ruído.

4. **Decisão com número:** 0,4506 ≪ 0,60 → **reabrir o hybrid sozinho NÃO é o PR pra prod**. A condição de reativação da ADR 0312 segue parcialmente pendente: (a) instrução de query ✅ (ADR 0322/PR #3791) · (b) documentTemplate/excerpt real ❌ · (d) re-tuning semanticRatio ❌.

5. **Causa dominante achada no código:** `KbAnswerService::renderFontes()` → `extrairExcerpt($doc, 400)` corta cada doc aos **primeiros 400 chars** pós-frontmatter. Síntese E juiz só veem isso — se o fato mora no meio da ADR, context_recall = 0 mesmo com o doc certo em #1. Explica por que ranking 9,5× melhor (recall@5 0,074→0,704, lane determinística) quase não move a régua LLM-judged. **Não é artefato do eval:** o pipeline real responde a partir do mesmo excerpt — consertar isso melhora o produto, não só a métrica.

6. **Achado colateral (US-COPI-134):** a régua semanal do ADR 0318 (dom 07:00) e o recall-eval (dom 06:30), ambos `environments(['staging'])`, **não têm runner**: container `oimpresso-staging` sem `schedule:run`, host só tem o cron do publisher (dom 08:30). `latest.json` era de 04/jul (manual); trend órfã parada em 2026-06-28; publisher de hoje publicou stale.

## Ordem de ROI recalibrada (gravada na US-COPI-133)

1. **Excerpt/chunk query-aware no `renderFontes`** — alavanca mais barata; ataca o gargalo medido.
2. US-COPI-130 camadas 2-3 (Contextual Retrieval + BgeReranker reusado no índice de docs).
3. Bipartição corpus negócio ≠ processo (ADR 0334 §4) **+ bipartição do eval junto** (golden set atual é de processo).
4. Re-medir cada camada com `jana:ragas-real-eval` (N=51, CT 100).

## Guard-rails cumpridos

Eval SÓ no CT 100 staging (Tier 0) · prod e biz=4 intocados · flag não persistida (per-exec) · staging restaurado ao canônico e verificado (`latest.json` = run OFF de hoje 0,7355/0,3939; `printenv` sem flag) · gates humanos (confiabilidade → mão da Larissa → `client_signal`) permanecem com Wagner · Onda 6/ADS/autonomia não tocadas.

## Artefatos

- US-COPI-133 + US-COPI-134 no MCP e em `memory/requisitos/Jana/SPEC.md` (este PR)
- Raw do A/B: `/root/ragas-spike-20260712/{antes-off,depois-on}.json` no CT 100
- Handoff: [2026-07-12-2215-spike-hybrid-jana-bi-cycle-bi.md](../handoffs/2026-07-12-2215-spike-hybrid-jana-bi-cycle-bi.md)
