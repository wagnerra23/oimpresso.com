---
title: "Plano Gap 6 — Visual regression snapshots update pos-Wave 5"
date: "2026-05-26"
type: gap-plan
status: draft
gap_id: 6
modulo: OficinaAuto + Sells (CreateScreenshotTest)
us_relacionada: governance visual-regression
cliente: CI/CD
esforco_estimado: "2-4h IA-pair (fator 10x ADR 0106) + 1h Wagner valida diffs"
roi: medio-CI-saude
bloqueia_demo: nao (eh CI gate)
---

# Plano Gap 6 — Visual regression snapshots update

## Contexto

Wave 5 OficinaAuto mergeou 4 PRs hoje com mudancas visuais detectaveis:
- PR #1624: drawer rico ServiceOrderRichSheet (KV grid polimorfica + secao PECAS&MO)
- PR #1631: ServiceOrders/Show.tsx ganhou section "Itens da OS" + PageHeaderPrimary roxo
- PR #1631: ServiceOrders/Edit.tsx ganhou section inline mode FOCO
- PR #1635: header is-scrolled compactify Onda 2.2 (5 drawers fiscal/a11y)

Mudancas visuais quebram snapshots ja capturados. Se CI rodar `vendor/bin/pest tests/Browser/`, vai falhar com diff visual.

## Inventario oimpresso

Glob retornou apenas **1 arquivo** de teste browser:
- `tests/Browser/Sells/CreateScreenshotTest.php` (Sells, nao OficinaAuto)

**Nenhum snapshot encontrado** em `tests/**/Snapshots/` nem `tests/**/__snapshots__/`. Provavel que infraestrutura Pest browser/visual ainda esta nascente — apenas 1 spec Sells captura screenshot.

## Estado real do gap

**Gap 6 e MENOR do que aparentava no levantamento.** Realidade:

1. Apenas Sells/Create tem screenshot test. Wave 5 NAO tocou Sells/Create.tsx — snapshot Sells Create deve estar OK.
2. OficinaAuto NAO tem testes browser visuais hoje. Sem snapshot pra quebrar.
3. CI gates visuais com Wave 5 NAO devem ter falhado (porque nao existem ainda pra OficinaAuto).

**Reinterpretacao do gap:** Wagner provavelmente queria:
- (a) Verificar se CreateScreenshotTest Sells passa (alheio Wave 5 mas saude geral)
- (b) **Criar** snapshots OficinaAuto agora que Wave 5 estabilizou — baselining NOVO, nao update
- (c) Documentar strategy de visual regression baseline pra waves futuras

## Research estado-da-arte 2026

Visual regression testing 2026 patterns:

1. **Percy / Chromatic** — SaaS, $39+/mes, integrate GitHub Action, diff highlight
2. **Pest browser plugin (Laravel canon)** — open source, headless Chromium, snapshot PNG em git
3. **Playwright + visual comparisons** — `expect(page).toHaveScreenshot('name.png')` — threshold pixel diff
4. **Storybook + Chromatic** — component-level, nao page-level

oimpresso usa Pest browser (1 spec em uso). **Strategy proposta:** continuar Pest browser (sem novo SaaS), criar baseline OficinaAuto agora que Wave 5 estabilizou, doc strategy em ADR proposta.

## Arquivos a tocar

| Arquivo | Operacao | Notas |
|---|---|---|
| `tests/Browser/Sells/CreateScreenshotTest.php` | RUN — `php artisan test --filter=CreateScreenshotTest` | Validar passa (sanidade Sells nao quebrou) |
| `tests/Browser/OficinaAuto/ServiceOrdersShowScreenshotTest.php` | NOVO — captura Show.tsx Wave 5 (section items + roxo 295) | Baseline novo |
| `tests/Browser/OficinaAuto/ServiceOrdersEditScreenshotTest.php` | NOVO — captura Edit.tsx Wave 5 (mode FOCO embedded) | Baseline |
| `tests/Browser/OficinaAuto/ProducaoOficinaDrawerScreenshotTest.php` | NOVO — captura ServiceOrderRichSheet drawer 2 modos (manutencao + locacao) | Baseline |
| `tests/Browser/OficinaAuto/AprovacaoPublicaScreenshotTest.php` | NOVO — captura mobile 360px AprovacaoPublica Wave 4 | Baseline |
| `tests/Browser/Snapshots/OficinaAuto/*.png` | NOVO — baselines capturadas primeira execucao | Commit em git Git LFS opcional |
| `memory/decisions/proposals/visual-regression-strategy.md` | NOVO — ADR proposta strategy baseline + threshold + CI integration | Wagner aprova |
| `phpunit.xml` ou config Pest | EDIT — habilitar suite Browser com filtro `--testsuite=Browser` | Opt-in pra rodar paralelo |
| `.github/workflows/pest-browser.yml` | NOVO — job CI manual-trigger (workflow_dispatch) pra rodar suite browser sob demanda | Anti-CI-minute-burn |

## Restricoes Tier 0 deste gap

1. **Headless Chromium pesado** — NAO rodar suite browser em todo PR. Manual-trigger via `workflow_dispatch` no GitHub Actions OU `[browser-test]` flag em PR body.
2. **CI free tier 2.000 min/mes ja esgotou hoje** (licao 3.9 sessao 2026-05-26 4PRs). Headless Chromium = ~1-2min/spec. 5 specs = 5-10min. Self-hosted runner CT 100 (mencionado backlog Wagner) seria ideal mas adia.
3. **Determinismo cross-platform** — Chromium Windows vs Linux pode renderizar fonts diferente. Pin docker image se for Linux-only CI.
4. **Threshold pixel diff** — usar `--threshold=0.01` (1% pixel diff) pra absorver anti-aliasing minor. Diff maior precisa Wagner aprovar.
5. **Multi-tenant ADR 0093** — fixtures Pest seedam business_id 164 fake Martinho. Snapshots NAO podem capturar PII real.
6. **LGPD** — snapshots de tela com dados clientes precisam usar fixtures faker, NUNCA prod data.

## Mini-comparativo atual → target

| Aspecto | Hoje | Target Gap 6 |
|---|---|---|
| Snapshots OficinaAuto | nenhum | 4 telas baseline (Show + Edit + Drawer + AprovacaoPublica) |
| Snapshot Sells/Create | 1 spec existente | validado pos-Wave 5 (sanidade) |
| Strategy doc | nenhuma | ADR proposta visual-regression-strategy |
| CI workflow browser | rodando sempre? validar | manual-trigger only V0 |
| Threshold pixel diff | N/A | 0.01 (1%) configurado |
| Cross-platform determinismo | N/A | docker pinned image Linux-only |

## Esforco estimado

- Validar CreateScreenshotTest passa: 15min (smoke local)
- ADR proposta visual-regression-strategy: 1h
- 4 specs Pest browser baseline OficinaAuto: 2h
- Capturar baselines pela primeira vez: 30min
- Workflow `.github/workflows/pest-browser.yml` manual-trigger: 30min
- **Total: 3-4h IA-pair** (fator 10x ADR 0106) + 1h Wagner valida diffs

## Smoke criteria

- [ ] `php artisan test --filter=CreateScreenshotTest` passa local
- [ ] 4 baselines novos capturados, commit Git LFS opcional
- [ ] `gh workflow run pest-browser.yml` rodando manual-trigger, suite verde
- [ ] Diff teste: mudar arbitrariamente cor de 1 botao Show.tsx, rodar suite, snapshot diff highlight aponta mudanca
- [ ] Wagner aprova ADR proposta strategy

## Dependencias

- **DEVE rodar POR ULTIMO** dos 6 gaps — depende de Gaps 1-5 mergeados pra baselines refletirem estado final estavel
- Se gap 1 ainda nao mergeado, baseline ServiceOrderRichSheet **vai mudar de novo** quando Gap 1 plugar fotos reais
- Recomendado pos-merge ondem proposta:
  1. Gap 5 (charter) — 2h, nao muda visual
  2. Gap 3 (print PDF) — 5h, nao muda visual
  3. Gap 1 (upload foto) — 6h, muda drawer
  4. Gap 2 (DVI UI) — 12h (3 sub-PRs), muda drawer + adiciona pagina
  5. Gap 4 (SMS) — 8h, nao muda visual
  6. **Gap 6 (snapshots) — APOS todos** — 4h baseline
- Se Wagner quiser baseline parcial agora (so do estado pos-Wave 5 antes de Gaps 1-2), pode rodar Gap 6 em duas etapas: baseline-1 (hoje) + baseline-2 (apos Gaps 1+2 mergeados).

## DRAFT task pra Wagner copy-paste

```yaml
title: "Gap 6 — Visual regression snapshots baseline OficinaAuto pos-Wave 5"
module: OficinaAuto + tests/Browser
us: governance-visual-regression
priority: low-mas-saude-CI
estimated_hours: 4
owner_proposal: claude-paralelo
description: |
  Criar baselines visuais OficinaAuto (4 telas Wave 5 estabilizadas) + ADR
  proposta strategy visual-regression. NAO eh "update" porque nao havia
  snapshots OficinaAuto antes — eh baselining inicial.

  Validar CreateScreenshotTest Sells passa (sanidade).

  Threshold 0.01 (1% pixel) + manual-trigger CI (anti-CI-minute-burn).

  Pre-req CRITICO: gaps 1, 2, 3, 4, 5 mergeados ANTES (snapshots refletem
  estado final). Caso Wagner prefira baseline parcial hoje, registrar
  "baseline-1" pre-Gaps 1-2 e "baseline-2" pos.

  Refs: ADR 0093, ADR 0094, ADR 0106, licao 3.9 CI free tier
acceptance_criteria:
  - "CreateScreenshotTest Sells passa local"
  - "4 baselines novos OficinaAuto commitados"
  - "ADR visual-regression-strategy proposta criada"
  - "Diff teste: mudanca cor 1 botao detectada pelo snapshot diff"
```

## Refs

- ADR 0094 Constituicao v2 §Loop fechado por metrica
- ADR 0093 Multi-tenant fixtures
- ADR 0106 Recalibracao 10x
- `tests/Browser/Sells/CreateScreenshotTest.php` (unico exemplo atual)
- Sessao 2026-05-26 4 PRs §3.9 (CI free tier limit)
- Pest browser plugin Laravel docs
- Playwright visual comparisons (referencia tecnica)
