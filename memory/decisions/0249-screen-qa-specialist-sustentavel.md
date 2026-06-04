---
slug: 0249-screen-qa-specialist-sustentavel
number: 249
title: "QA-de-tela sustentável: enforcement determinístico do screen-grade (seed + catraca + sentinela) + agente-autor"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-04"
accepted_at: "2026-06-04"
accepted_via: "Wagner aceitou nesta sessão — fundação PR #2215, enforcement PR #2223"
module: governance
quarter: 2026-Q2
tags: [qa, screen-grade, governance, ci, ratchet, testing, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ['0101', '0108', '0153', '0155', '0156', '0160', '0230', '0231', '0232']
pii: false
---

# ADR 0249 — QA-de-tela sustentável: enforcement do screen-grade + agente-autor

**Complementa:** [0108](0108-regressao-visual-pest-browser-tier-2.md) (visual Tier 2), [0101](0101-sistema-charter-capterra-governanca-escopo.md) (smoke biz=1), [0155](0155-rubrica-module-grade-v3.md)/[0156](0156-template-scorecard-canonico.md)/[0160](0160-scoped-scorecards-v4-bucket-yaml.md) (module-grade/scorecards), [0230](0230-metodo-governance-scorecard.md)/[0231](0231-processo-trabalho-canonico-especialista-por-area.md)/[0232](0232-modelo-peso-real-classificacao-por-meta.md) (método/especialista/peso real).

## Contexto

Pedido Wagner (2026-06-04): garantir QA das 275 telas com um "especialista Full Tester + QA" por tela, comparado ao estado-da-arte, **que sobreviva e evolua no tempo**.

Baseline (`scripts/qa/screen-coverage-map.mjs`, 2026-06-04): 275 telas · charter 132 (48%) · **E2E 3 (1,1%)** · **a11y 0** · **scorecard persistido 0**.

**Descoberta decisiva** (do estudo das rotinas internas): o método `screen-grade` **já existe por inteiro** (`SCREEN-GRADE-METODO.md` — 16 dims, persona, níveis) + um **baseline de 222 telas gradeadas** (`screen-grades-baseline-2026-05-30.json`, média 75). A nota é **LLM-as-judge** (comparação com Stripe/Linear/Bling *com o mecanismo*) — **não determinística** como o `module:grade` (filesystem inspection). O próprio método (§6) aponta o gap: *"enforcement (ratchet): **ligar**"*.

Logo: **o que falta não é a grade — é o enforcement determinístico em volta dela.** Um command que "computa 16 dimensões subjetivas" seria inventar heurística (anti-padrão Tier 0).

## Decisão

Separar **julgamento** (LLM) de **vigilância** (CI determinístico):

1. **Agente-autor `screen-qa-specialist`** (`.claude/agents/`, PR #2215) — produz a nota 16-dim (LLM-as-judge), deriva e escreve os E2E (Pest Browser, viewports 1280/1440, **biz=1**), injeta axe, propõe baseline visual. É o **autor**, não o guardião. Não commita, não roda teste local (CT 100 only), não edita Page sem gate visual.

2. **Enforcement determinístico** (PR #2223):
   - `scripts/qa/screen-grade-seed.mjs` — baseline 30/mai → **222 `scorecards/screens/*.yaml`** (template SCREEN-GRADE-METODO §5). **Não inventa nota.**
   - `scripts/qa/screen-grades-ratchet.mjs` + `.github/workflows/screen-grades-gate.yml` — catraca espelhando o `module-grades-gate` ([0155](0155-rubrica-module-grade-v3.md)): nota por tela não cai vs `origin/main`; override label `screen-grades-allowed-regression`. **Smoke-validada** (exit 1 com regressão · 0 com override).
   - `scripts/qa/screen-coverage-map.mjs --check` — catraca de cobertura (telas cobertas só sobem).

**CI vigia · agente julga.** Nada finge computar o que é subjetivo.

## Sobrevivência — 4 anéis (estado pós-implementação)

| Anel | Mecanismo | Estado |
|---|---|---|
| 1. Catraca de nota | scorecard YAML + `screen-grades-gate` vs `origin/main` | ✅ no ar (#2223) |
| 2. Catraca de cobertura | `screen-coverage-map.mjs --check` | ✅ script (#2215); wire CI pendente |
| 3. Sentinela de freshness | `charter:health` cron 06:30 (stale A30/B60/C90) + estender "TELAS SEM RE-SMOKE" no brief | parcial — `charter:health` já roda |
| 4. Self-healing | `.tsx` muda → agente regenera E2E | a ligar (`screen-smoke-after-merge`) |

**Métrica-mãe:** `% telas ≥ nota 70` + `% com E2E` no Daily Brief. Critério é score-as-code: estado-da-arte muda → adiciona dimensão → catraca recalibra → reavalia tudo. O QA acompanha os melhores porque o critério é doc vivo, não número congelado.

## Roadmap

- ✅ **Onda 0** (#2215): agente-autor + coverage map + baseline.
- ✅ **Onda 1** (#2223): seed 222 scorecards + catraca de nota (gate no ar) + hotfix FK NFSe (#2218) que destravou a suíte MySQL.
- **Onda 2:** wire `coverage-map --check` no CI + dim-16 mecânica (charter? `@/ui`? tokens v4? zero inline-style?) como piso grep no PR.
- **Onda 3:** re-seed pós-regrade do agente + métrica-mãe no brief + sentinela "TELAS SEM RE-SMOKE".
- **Onda 4:** self-healing via `screen-smoke-after-merge` (agente regenera E2E no drift).
- **Onda 5+:** agente rola por módulos P0 (Sells → Financeiro → NfeBrasil → Ponto), catraca subindo a cada PR.

## Consequências

**Positivas:** nota + cobertura monotônicas (só sobem); **reusa o método + baseline existentes** (não reinventa); honesto sobre natureza (LLM-as-judge vs CI determinístico); QA-de-tela vira ativo durável, não esforço heróico; operacionaliza a filosofia especialista-por-área ([0231](0231-processo-trabalho-canonico-especialista-por-area.md)).

**Custos/riscos:** Pest Browser só no CT 100 (CI já está lá); baseline visual exige curadoria humana (anti-drift = atrito intencional); axe pega ~30-40% das violações WCAG (trava o piso, não substitui humano); a nota subjetiva depende do agente rodar.

**Não-objetivos:** command que finge computar 16 dims subjetivas; rodar testes na máquina local; tornar a catraca um número congelado.
