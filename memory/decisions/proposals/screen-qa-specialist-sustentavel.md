---
slug: screen-qa-specialist-sustentavel
title: "Especialista Full Tester + QA por tela com sobrevivência embutida (catraca + sentinela + dono + self-healing)"
type: adr
status: proposed
authority: canonical
lifecycle: proposta
decided_by:
  - W
quarter: 2026-Q2
related:
  - '0108'
  - '0101'
  - '0230'
  - '0231'
  - '0232'
pii: false
---

# ADR (proposta) — QA-de-tela sustentável: especialista por tela + 4 anéis de sobrevivência

**Status:** 🟡 Proposta (aguarda aceite Wagner)
**Complementa:** [0108](../0108-regressao-visual-pest-browser-tier-2.md) (visual regression Tier 2), [0101](../0101-sistema-charter-capterra-governanca-escopo.md) (smoke biz=1), [0230](../0230-metodo-governance-scorecard.md)/[0231](../0231-processo-trabalho-canonico-especialista-por-area.md)/[0232](../0232-modelo-peso-real-classificacao-por-meta.md) (scorecard/especialista/peso real)

---

## Contexto

Pedido do Wagner (2026-06-04): garantir cobertura de QA das telas com um "especialista Full Tester + QA" por tela, comparado ao estado-da-arte, e — crucial — **que sobreviva e evolua no tempo**.

Baseline factual medido (`scripts/qa/screen-coverage-map.mjs`, 2026-06-04):

| Camada | Cobertura | Leitura |
|---|---|---|
| Telas Inertia | 275 | universo |
| Charter (contrato) | 132/275 (48%) | metade tem oráculo |
| E2E (Pest Browser) | **3/275 (1,1%)** | buraco crítico |
| A11Y (axe) | **0/275 (0%)** | inexistente |
| Scorecard (nota persistida) | **0/275 (0%)** | método existe, nunca persistido |

O paradoxo: o oimpresso **já desenhou** a camada de QA-de-tela (skills `screen-grade` 16-dim, `pr-ui-judge`, `tela-smoke-pos-merge`; ADR 0108; runner Pest Browser/Playwright instalado) mas a **execução está off/manual**: `visual-regression.yml` em `continue-on-error` (não bloqueia), `pr-ui-judge.yml` com kill-switch OFF, smoke pós-merge semi-manual. QA de **backend** é forte (1.177 testes, multi-tenant-gate); QA de **tela** está em ~34/100.

**O problema central não é atingir 100% — é não regredir depois.** Cobertura sem mecanismo apodrece pelos 4 modos: cobertura regride (tela nova sem teste), flakiness (gate é desligado), baseline drift (snapshot vira carimbo), oráculo stale (charter desatualizado). Entropia ganha por padrão.

## Decisão

1. **Agente `screen-qa-specialist`** (`.claude/agents/`) — especialista Opus por tela, ciclo agentic **Planner → Automator → Maintainer** (estado-da-arte 2026: QA Wolf/Autonoma) adaptado às regras Tier 0: Pré-Flight → nota (screen-grade) → derivar casos do charter → gerar Pest Browser (viewports 1280/1440, biz=1) → axe + baseline visual → smoke prod com evidência → scorecard YAML + gaps. Não commita, não roda teste local (CT 100 only), não edita Page sem gate visual.

2. **Catraca de cobertura** (`scripts/qa/screen-coverage-map.mjs`) — `--json` grava `memory/governance/screen-coverage-baseline.json`; `--check` falha o CI se charter/e2e/a11y/scorecard agregados regredirem. **Telas cobertas só sobem.**

3. **Sobrevivência embutida** (seção abaixo) — não é promessa, é mecanismo, espelhando o `module-grades-gate` que o time já confia.

## Sobrevivência — os 4 anéis (o coração desta ADR)

| Anel | Mecanismo | Espelha o que já existe | Estado |
|---|---|---|---|
| **1. Catraca de nota** | scorecard YAML versionado vira baseline; gate bloqueia PR que derrube a nota da tela | `module-grades-gate.yml` | a criar (`screen-grades-gate`) |
| **2. Catraca de cobertura** | `screen-coverage-map.mjs --check` no CI; tela nova sem teste = PR vermelho | governance append-only / drift gate | ✅ script entregue, falta wire no CI |
| **3. Sentinela de freshness** | charter/baseline/teste > limiar → flag no Daily Brief; cron 09:00 re-smoka telas live ≥7d | seção "CHARTERS APODRECENDO" do brief + cron `tela-smoke` | parcial (estender pra "TELAS SEM RE-SMOKE") |
| **4. Self-healing (Maintainer)** | `.tsx` muda → agente re-disparado regenera E2E + propõe novo baseline visual | `screen-smoke-after-merge.yml` (detecta diff) | a ligar (hoje só comenta "pendente") |

**Métrica-mãe** (no Daily Brief, igual cycle goals): **`% de telas ≥ nota 70` e `% de telas com E2E`** — trackadas no tempo. Evolução do critério é versionada: quando o estado-da-arte mudar (ex: Visual-AI virar padrão), adiciona-se uma dimensão ao método 16-dim → a catraca recalibra → todas as telas reavaliadas. O QA acompanha o estado-da-arte porque o critério é doc vivo (score-as-code), não número congelado.

## Roadmap de ativação (ondas, por impacto×esforço)

- **Onda 0 (feito nesta proposta):** script de cobertura + baseline + agente + esta ADR.
- **Onda 1:** wire `screen-coverage-map.mjs --check` no CI (anel 2) + desbloquear `visual-regression.yml` (tirar `continue-on-error`, resolver migration-order legacy).
- **Onda 2:** piloto end-to-end numa tela P0 (`Sells/Create`, tela-ouro) — primeiro scorecard YAML + primeiro E2E com axe → prova o fluxo do agente.
- **Onda 3:** `screen-grades-gate` (anel 1) + métrica-mãe no brief (anel 3 estendido).
- **Onda 4:** self-healing — ligar `screen-smoke-after-merge` pra invocar o agente (anel 4).
- **Onda 5+:** rolar agente por módulos P0 (Sells → Financeiro → NfeBrasil → Ponto), catraca subindo a cada PR.

## Consequências

**Positivas:** cobertura passa a ser monotônica (só sobe); QA-de-tela vira ativo durável, não esforço heróico; reusa 80% do que já existe (não reinventa); especialista por tela operacionaliza a filosofia ADR 0231.

**Custos/riscos:** Pest Browser roda só no CT 100 (CI já está lá); baseline visual exige curadoria humana (anti-drift = atrito intencional); a11y automatizado pega só ~30-40% das violações WCAG (não substitui revisão humana — apenas trava o piso).

**Não-objetivos:** substituir QA humano de exploração; rodar testes na máquina local; tornar a catraca um número congelado.
