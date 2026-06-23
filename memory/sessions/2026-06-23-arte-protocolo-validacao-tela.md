---
slug: 2026-06-23-arte-protocolo-validacao-tela
title: "Estado-da-arte — protocolo de validação de mudança de tela (antes do merge)"
type: arte-session
date: 2026-06-23
agent: estado-da-arte
status: draft
escopo: "Como validar uma modificação de tela (sessão limpa aplica protótipo na tela viva) ANTES de aceitar/mergear"
related_adrs: [0107, 0108, 0114, 0255, 0261, 0264, 0271, 0286, 0093, 0101]
---

# Protocolo de validação de mudança de tela — estado-da-arte vs oimpresso

> Missão: atacar adversarialmente o protocolo ATUAL de validação de tela e desenhar o MELHOR
> protocolo em camadas. Fase 1 pesquisa limpa (web). Fase 2 compara com o repo. Fase 3 gaps + recomendação.

---

## Fase 1 — PESQUISE OS MELHORES (SOTA 2026, pesquisa limpa)

Como os líderes validam uma mudança de UI antes do merge — mecanismo concreto, não buzzword.

| Player | Mecanismo concreto | Por que é referência |
|---|---|---|
| **Chromatic** (Storybook) | Snapshot visual por **story** × viewport × tema, em browser real na cloud. **TurboSnap** snapshota só o que o git-diff tocou (−80% custo). Roda **axe** por componente + **interaction tests** (play functions) antes do snapshot. Revisor humano só aprova o **diff genuíno**. | Padrão de facto pra design system; resolve o gargalo humano via "humano só no diff" + baseline em código real. |
| **Percy** (BrowserStack) | **DOM snapshot** (não screenshot local): captura o DOM, re-renderiza em browsers reais na cloud, diffa contra baseline. Determinismo via render server-side controlado + masking de regiões dinâmicas. | DOM-snapshot é mais determinístico que screenshot local; cross-browser real. |
| **Playwright `toHaveScreenshot`** | VRT **determinístico** open-source: `maxDiffPixels`/`maxDiffPixelRatio` **por componente** (hero ≠ tabela), `mask` de regiões voláteis, `stylePath` desliga animação, baseline gerado **em CI/Docker** (não local). | É exatamente o motor que o oimpresso já usa (Pest 4 Browser = Playwright). SOTA acessível sem SaaS. |
| **Storybook Test (Vitest addon)** | Cada **estado** isolado vira story (`empty`/`error`/`loading`/`long-data`/`dark`/`rtl`). `play()` simula interação; runner roda interaction + a11y + visual num passe, em browser mode. | Resolve o furo nº1 do oimpresso: validação por-estado isolado, não só o "happy path renderizado". |
| **Lighthouse CI (`@lhci/cli`)** | `budget.json` falha o build quando **LCP > 2.5s / CLS > 0.1 / TBT** regridem. Assertions por-métrica e por-recurso. LHCI 0.15.x / Lighthouse 12.6. | Perf/CLS como gate de regressão é commodity em 2026 — e o oimpresso não tem. |
| **Padrão HITL "double-threshold"** (review queues 2026) | Auto-aprova acima de limiar alto, auto-rejeita abaixo de limiar baixo, **humano só na zona cinza**. Alvo de escalonamento 10-15%. Trate HITL como prática de SRE: defina limiares, roteie por risco, meça escapes. | É o princípio que tira o Wagner-screenshot do caminho crítico de N telas sem perder o olho humano onde importa. |
| **LLM-as-judge robusto** (PoLL / DAG / rubrica analítica) | Single-judge é não-determinístico e alucina. SOTA: **painel de juízes** (modelos disjuntos) + **self-consistency** (mesma entrada n×) + decompor em **DAG de decisões binárias** por nó; rubrica analítica > holística. | Diz como NÃO usar LLM-judge: nunca como gate único/binário sobre o vácuo; sempre com golden + multi-amostra. |

**Síntese SOTA:** a mudança de UI é validada em **camadas determinísticas baratas primeiro** (lint estrutural → estados isolados → VRT pixel com threshold → a11y runtime → perf budget), e **humano/LLM só na zona cinza** (diff genuíno acima do limiar). O LLM-judge é coadjuvante calibrado por golden, nunca o gate. Ninguém valida olhando 1 viewport no happy path.

---

## Fase 2 — COMPARE COM O QUE O WAGNER TEM

Lido no repo (worktree `D:/oimpresso.com`): `ui-architecture-gate.yml`, `contrato-de-tela.yml`,
`visual-regression.yml` (Pest 4 Browser + PixelBaselineTest + A11yAxeBrowserTest + ConformanceProbesTest),
`a11y-axe-gate.yml`, `design-spec-gate.yml` (ADR 0255), `RUNBOOK-contrato-de-tela.md`, skills
`screen-grade` / `tela-smoke-pos-merge` / `mwart-comparative` V4.

O protocolo atual é **mais maduro do que o briefing sugeria** — não é "Wagner aprova 1 screenshot + 4 gates frouxos". É um trio-de-tela (comportamento/uso/fidelidade) + 6 gates CI + rubrica 16-dim + smoke pós-merge.

| Dimensão (emergiu da Fase 1) | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| **VRT pixel determinístico** | Playwright/Chromatic threshold por componente, baseline em CI | `PixelBaselineTest` (Pest 4 Browser = Playwright), baseline commitado, gerado no runner — **mas advisory** (`continue-on-error`), mergeou vermelho 2× (#2544/#2548) | **curta** (motor existe, falta morder) |
| **Estados isolados** (empty/error/loading/long/dark) | Storybook stories por estado + play | Pixel cobre **viewport default no happy path**; `ConformanceProbesTest` abre 1 drawer; sem matriz de estados; sem Storybook | **longa** — furo nº1 |
| **a11y runtime** | axe em browser real (contraste/foco) | `a11y-axe-gate` (jsdom: ARIA) + `A11yAxeBrowserTest` (Chromium real, 0 CRITICAL) — **required-real** | **curta / paridade** |
| **Contrato estrutural determinístico** | DOM-diff / design-spec | `contrato-de-tela.mjs` (âncora+copy+ordem) + catraca semântica state backend↔frontend + `design-spec-gate` (ADR 0255, required) | **oimpresso À FRENTE** — a catraca PHP↔TS é incomum |
| **Perf budget (LCP/CLS)** | Lighthouse CI, falha build | `tela-smoke-pos-merge` coleta FCP/LCP/TTI/TBT **pós-merge, advisory, sem budget que morde** | **longa** — gap limpo |
| **Humano só na zona cinza** | double-threshold, escalonar 10-15% | Wagner aprova screenshot **toda** mudança de UI (1280/1440 light+dark) — gate manual em tudo | **média** — não escala pra N telas |
| **LLM-judge calibrado** | painel + self-consistency + golden | charter = intenção julgada por LLM (`design-critique` na mwart-comparative); single-judge, sem golden formal, sem multi-amostra | **média** |
| **Rubrica de maturidade** | — (raro ter) | `screen-grade` 16-dim por persona + Peso Real + níveis | **oimpresso À FRENTE** |
| **Smoke pós-merge automatizado** | re-run agendado | `tela-smoke-pos-merge` cron daily + PII auto-mask + review.md PDCA | **oimpresso À FRENTE** |
| **Multi-tenant no render** | (não-aplicável à maioria) | seed biz=1, sem assert de **isolamento no render** (tela B não vaza dado de tenant A) | **longa** — P0 Tier 0 latente |

### Onde o oimpresso está À FRENTE do mercado
1. **Trio-de-tela + catraca semântica state backend↔frontend** (ADR 0286 §5) — pega bug `paired`≠`connected` que Chromatic/Percy **não veem** (eles não cruzam PHP↔TS).
2. **screen-grade 16-dim por persona + Peso Real** — ninguém no mercado grada tela por persona-dona.
3. **smoke pós-merge com PII auto-mask + PDCA** — Tier 0 embutido no loop visual.
4. **Anti-teatro explícito** (RUNBOOK §3): "réu não escreve a justificativa", veredito mecânico, sem skip-as-pass. Disciplina rara.

### Onde está ATRÁS (os furos, rankeados — onde uma tela RUIM passa)
1. **VRT advisory** — pixel-diff existe mas `continue-on-error`; regressão pixel mergeia vermelho. Furo nº1 do briefing **já tem motor**, falta promover a required (ADR 0261: 2 verdes → enforcing).
2. **Sem matriz de estados** — empty/error/loading/long-data/dark/rtl não têm snapshot isolado. Regressão num estado não-renderizado-no-seed passa batido. Furo mais largo e mais barato de fechar em SOTA.
3. **Sem perf budget que morde** — CLS/LCP coletados pós-merge advisory; nenhum gate falha por LCP > 2.5s ou layout shift.
4. **Wagner-screenshot em tudo** — gargalo humano; não escala; subjetivo; 1 viewport/estado por vez (o próprio briefing). Sem double-threshold pra rotear só o diff genuíno pro olho dele.
5. **LLM-judge sem golden/self-consistency** — `design-critique` julga screenshot real, mas single-shot, sem âncora golden → alucina "ok" (Fase 1 confirma: single-judge não-determinístico).
6. **Isolamento multi-tenant no render não-asserido** (Tier 0) — nenhum teste prova que a tela renderizada com biz=A não vaza linha de biz=B. Gate que vaza tenant = P0 (ADR 0093).

---

## Fase 3 — AVALIE O QUE ESTÁ FALTANDO

Esforço em IA-pair (ADR 0106: 10x humano + margem 2x). Gates novos nascem advisory → 2 verdes → enforcing (ADR 0261).

| Gap | Impacto | Esforço (IA-pair) | Pré-req? |
|---|---|---|---|
| **Promover `visual-regression` (pixel-diff) a required** — remover `continue-on-error` do step pixel | **alto** | ~30 min (1 linha + 2 runs verdes p/ baseline estável) | Baseline núcleo-6 estável (já gerado); decisão branch-protection [W] |
| **Matriz de estados isolados** — snapshot por estado (empty/error/loading/long/dark) via rota de fixture ou story | **alto** | ~6-10 h (harness de fixture por-tela + N snapshots) | VRT required (acima) ser o motor |
| **Lighthouse CI budget** (`budget.json`: LCP<2.5s, CLS<0.1, TBT) como gate, reusa Chromium do visreg | **médio** | ~3-5 h (workflow + budget + 2 verdes) | nenhum bloqueante |
| **Double-threshold no Wagner-screenshot** — humano só quando pixel-diff > limiar OU charter-deviation declarado; abaixo do limiar auto-segue | **médio** | ~2-4 h (regra no comment-bot + roteamento) | VRT required (define o "limiar") |
| **Assert isolamento multi-tenant no render** (Tier 0) — teste browser: biz=A logado não vê linha de biz=B em tela de lista | **alto (P0 se vazar)** | ~3-4 h (seed 2 tenants + assert no harness auth-bridge existente) | seed VisregTenant 2º tenant |
| **LLM-judge calibrado** — `design-critique` com golden de referência + 3 amostras self-consistency (median) | **baixo-médio** | ~3-5 h | golden screenshot versionado por tela |

### Recomendação concreta

**Comece por promover `visual-regression` (pixel-diff) a required.** É alto-impacto-baixo-esforço, **sem pré-req bloqueante**: o motor (PixelBaselineTest), o baseline (snapshot commitado, gerado no runner determinístico) e o padrão de promoção (ADR 0261) **já existem**. É literalmente remover o `continue-on-error` do step "Pixel-diff núcleo-6" após 2 runs verdes. Fecha o furo nº1 (regressão pixel mergeia vermelho — provado #2544/#2548) e estabelece o **limiar** que destrava o double-threshold (tirar o Wagner do caminho crítico depois).

**Próxima ação hoje:** abrir uma ADR-proposal "promover pixel-diff núcleo-6 a enforcing" — checar se as últimas 2 execuções do step `pixel-diff` no CI vieram verdes; se sim, propor o PR de 1 linha (remover `continue-on-error` em `visual-regression.yml:233`) sob `enforce_admins`, condicionado ao falso-positivo = 0 (mesma régua do `contrato-de-tela` §3).

---

## Fontes (Fase 1)
- Playwright VRT 2026: https://www.browserstack.com/guide/playwright-snapshot-testing · https://testquality.com/playwright-visual-regression-guide/
- Chromatic test (layers + TurboSnap): https://www.chromatic.com/docs/test/
- Percy tools 2026: https://percy.io/blog/visual-regression-testing-tools
- Storybook interaction/test-runner + Vitest addon: https://storybook.js.org/docs/writing-tests/interaction-testing · https://storybook.js.org/docs/writing-tests/integrations/test-runner
- Lighthouse CI budgets: https://googlechrome.github.io/lighthouse-ci/ · https://unlighthouse.dev/learn-lighthouse/lighthouse-ci/budgets
- HITL double-threshold review queues 2026: https://alldaystech.com/guides/artificial-intelligence/human-in-the-loop-ai-review-queue-workflows · https://www.maviklabs.com/blog/human-in-the-loop-review-queue-2026/
- LLM-as-judge (PoLL/DAG/rubrica): https://deepeval.com/guides/guides-llm-as-a-judge · https://arxiv.org/pdf/2504.17087
