---
slug: 0108-regressao-visual-pest-browser-tier-2
number: 108
title: "Regressão visual via Pest 4 Browser snapshot — Tier 2 (CI gate F4 QA)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-08'
quarter: 2026-Q2
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
pii: false
---

# ADR 0108 — Regressão visual via Pest 4 Browser snapshot (CI gate F4 QA)

**Status:** ✅ Aceita
**Data:** 2026-05-08
**Decisão por:** Wagner Rocha
**Não supersede:** complementa ADR 0104 §F4 QA HARDENING e ADR 0107 §gate F1.5

---

## Contexto

3 hotfixes em sequência durante migração `/sells/create` (PRs #245, #247, #248) mostraram que **gates técnicos atuais** (Pest unit + Pest feature + audit cockpit-runbook modo B + CI mwart-gate) **não pegam regressão visual em runtime real**:

- PR #244 passou 24 Pest tests estruturais ✅ + audit modo B 92/100 ✅ + CI green ✅
- Mas em prod: tela branca + React error #31 ("Objects are not valid as React child")
- Causa: shape JSON do controller não bate com tipo TS declarado no Page

Tests estruturais checam **código-fonte** (regex grep no .tsx), não **runtime**. PR #244 passou todos porque o código tinha as strings esperadas — só não funcionou quando renderizou.

Wagner perguntou em sessão 2026-05-08:

> *"Como testar esse tipo de regressão visual?"*

## Decisão

Adotar **Pest 4 Browser plugin** como camada Tier 2 de validação visual, integrada ao processo MWART F4 QA HARDENING (ADR 0104).

### Stack

- `pestphp/pest-plugin-browser` — plugin oficial, integra Playwright em Pest tests
- Playwright (Node) — Chromium headless
- Screenshots baseline armazenados em `tests/Browser/Screenshots/__snapshots__/`
- Diff threshold default: 0.1% pixel difference
- Git LFS opcional pra PNGs (pequenos < 200KB cada — Git puro funciona até ~100 telas)

### Fluxo

```
PR toca Pages/<Mod>/<Tela>.tsx
   ↓
GitHub Action `.github/workflows/visual-regression.yml` dispara
   ↓
Setup Node + Chromium (npm ci + npx playwright install --with-deps)
   ↓
Setup PHP + composer install
   ↓
php artisan serve (local instância) + autenticação fake biz=1
   ↓
./vendor/bin/pest tests/Browser/<Mod>/
   ├─ visit('/<rota>')->assertScreenshotMatches() → 1ª vez gera baseline
   └─ Próximas execuções comparam contra baseline
   ↓
Se diff > 0.1% → falha + comenta no PR com link pro screenshot atual + diff
```

### Gates por fase (ADR 0104)

| Fase | Validação visual atual | Adição ADR 0108 |
|---|---|---|
| F1 PLAN (RUNBOOK) | — | — |
| F1.5 visual-comparison (ADR 0107) | manual Wagner aprova | — |
| F2 BACKEND BASELINE | Pest feature do `store()` | — |
| F3 FRONTEND INCREMENTAL | Pest structural + audit modo B ≥70 | **+ Pest browser snapshot per PR** |
| F4 QA HARDENING | Smoke biz=1 + audit ≥80 + canary 7d | **+ baseline locked, diff 0% sem aprovação** |
| F5 CUTOVER | Monitor 30d | — |

### Update baselines (mudança intencional)

Quando refator visual aprovado em F1.5 muda layout intencionalmente:

```bash
./vendor/bin/pest tests/Browser/ --update-snapshots
git add tests/Browser/Screenshots/__snapshots__/
git commit -m "test(visual): update baseline pra <tela> após refator F1.5 #<PR>"
```

PR de refator visual SEMPRE inclui update das baselines no mesmo commit. CI passa.

### Limites de tolerância

- **Default 0.1% pixel difference** — pega regressão real (campo sumiu, cor mudou)
- **Tolerância 0.5%** em telas com fontes/datas dinâmicas (ex: `defaultDatetime` do dia)
- **Tolerância 1%** em telas com avatars/imagens externas (CDN flutuante)

Configurado per-test:
```php
visit('/sells/create')->assertScreenshotMatches(threshold: 0.5);
```

## Consequências

### Boas

- **Pega regressão runtime** — não só código-fonte. PR #244 teria sido bloqueado pelo CI ANTES de mergear se baseline existisse
- **Custo zero financeiro** — Chromium roda em GitHub Actions free tier (Linux runner padrão)
- **Baseline = doutrina visual** — mudanças requerem `--update-snapshots` deliberado, não acidental
- **Wagner não precisa abrir tela em prod** — CI valida antes de ele ver
- **Histórico** — git log dos commits `--update-snapshots` mostra evolução visual de cada tela

### Ruins / mitigações

- **+30-60s por CI job** (setup Playwright + browsers + headless render). **Mitigação:** cache `~/.cache/ms-playwright` no Action; execução paralela `--parallel`
- **Flakiness em fontes/datas** — `defaultDatetime` muda a cada dia, screenshots diferentes. **Mitigação:** mock de tempo nos testes (`Carbon::setTestNow('2026-01-01 12:00:00')`)
- **Baselines viram peso no git** — 100 telas × 200KB = 20MB. **Mitigação:** Git LFS quando passar 50 telas
- **Setup dev local** — dev novo precisa rodar `npx playwright install` 1×. **Mitigação:** docs em README.md + skill `oimpresso-team-onboarding`

## Plano de aplicação

1. **Hoje (este PR):**
   - [x] ADR 0108 criado
   - [x] `composer require pestphp/pest-plugin-browser --dev`
   - [x] Primeiro teste `tests/Browser/Sells/CreateScreenshotTest.php` (Sells/create baseline)
   - [x] Workflow `.github/workflows/visual-regression.yml`
   - [x] Documentação em README.md + GOTCHAS

2. **Próxima migração** (US-SELL-007 ou outra):
   - Workflow ativo automático
   - Baseline gerada na 1ª execução
   - Diff em PR seguintes bloqueado

3. **Backfill retroativo** (próximo trimestre):
   - Gerar baselines pras 78 Pages Inertia atuais (cron 1× durante deploy janela noturna)
   - Locked baseline = doutrina visual histórica preservada

## Refs

- [Pest 4 Browser plugin docs](https://pestphp.com/docs/browser-testing)
- [Playwright docs](https://playwright.dev)
- [ADR 0104 — Processo MWART canônico §F4 QA HARDENING](0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual comparison gate F1.5](0107-emendation-0104-visual-comparison-gate-f3.md)
- [GOTCHAS cockpit-runbook §UltimatePOS forDropdowns 2026-05-08](../../.claude/skills/cockpit-runbook/GOTCHAS.md) — bugs que motivaram esta ADR

---

**Última atualização:** 2026-05-08
