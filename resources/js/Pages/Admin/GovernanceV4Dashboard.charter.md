---
page: /admin/governance-v4
component: resources/js/Pages/Admin/GovernanceV4Dashboard.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-05-17"
parent_module: Admin
related_adrs: [160, 156, 122, 94, 93, 101]
tier: A
charter_version: 2
---

# Page Charter — /admin/governance-v4 (DRAFT)

> **Status:** draft Wave 24 Agent B; expandido Wave 27 polish + drift visualization (2026-05-17). Wagner aprova Non-Goals + Anti-hooks ANTES de virar `status: live`.
>
> Backend: `Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php` agrega rubricas YAML `memory/governance/scorecards/*.yaml` + `mcp_scorecard_runs` (W21/W24-A, coluna `score`) + `mcp_scorecard_ai_suggestions` (Wave 24-B baseline) + `mcp_observability_aggregates_daily` (Wave 26, p99 7d, opcional).

---

## Mission

Dashboard Wagner-only que mostra ranking intra-bucket dos módulos sob rubricas Scoped Scorecards (ADR 0160). Substitui rubrica v3 meta-única (97.75 teto natural) por 4 buckets canônicos com metas diferenciadas. Inclui AI baseline READ-ONLY 30d (anti-Goodhart Jellyfish 2025).

---

## Goals — Features (faz)

- 4 abas/seções (uma por bucket canônico ADR 0160):
  - **Vertical Client-Facing** (meta ≥85): Vestuario, ComunicacaoVisual, OficinaAuto, Repair
  - **Cross-Cutting Infra** (meta ≥90): Governance, Admin, Auditoria, Infra
  - **AI Central** (meta ≥85): Jana, Brief, RAGAS, Memory
  - **Functional Horizontal** (meta ≥80): Sells, Accounting, Stock, NfeBrasil, etc
- Cada módulo card: nota atual + meta bucket + **status badge ok/warn/crit** + barra progresso + sparkline trend 30d + **p99 lat 7d** (se Wave 26 disponível)
- Paired violations badge vermelho quando >0 (anti-Goodhart)
- AI suggestions sidebar: top módulos com |avg_delta| > 1pt, READ-ONLY (NÃO altera score oficial)
- Inertia::defer pra props caras (modules/drift_alerts/ai_suggestions/paired_violations)
- Top-bar `BASELINE READ-ONLY 30d` quando AI suggestions ativas
- Footer: ADR 0160 link + generated_at
- AppShellV2 layout + PageHeader canon

### Goals Wave 27 (polish + drift visualization)

- **Drift alerts banner**: módulos que oscilaram > ±5pts entre snapshots consecutivos nos últimos 30d, ordenados por maior delta absoluto (quedas em destaque vermelho)
- **Status filtro**: ok / warn / crit / todos (cliente-side, payload `Inertia::defer` único)
- **Drift filtro**: checkbox "Só com drift" cruza com filtros bucket+status
- **Banner verde anti-pânico** quando 0 drifts (sinal saúde positivo, não silêncio)
- **p99 latência inline** por módulo (Wave 26 `mcp_observability_aggregates_daily` 7d, graceful fallback se tabela ainda não criada)
- **Bug fix Wave 24 herdado**: usar coluna `score` (real) em `mcp_scorecard_runs`, não `score_total` (alias inventado)

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO altera score oficial — AI é READ-ONLY observacional 30d
- ❌ NÃO substitui `mcp_scorecard_runs` (W21/W24-A canon determinístico)
- ❌ NÃO permite edit de rubrica YAML pela UI (YAML é canon git, edit via PR)
- ❌ NÃO acessível pelo time (Maiara/Felipe/Luiz/Eliana) — middleware `is-wagner`
- ❌ NÃO acessível pela internet pública — Tailscale CIDR
- ❌ NÃO cross-business — Wagner cross-tenant intencional (ver Admin charter)
- ❌ NÃO escreve em `mcp_scorecard_ai_suggestions` no GET (escrita só via job/command externo `governance:ai-baseline-run`)

---

## UX targets

- Carregamento <2s via Inertia::defer (modules/ai/paired lazy)
- 4 buckets visíveis em laptop 1280px (Wagner padrão)
- Mobile 1-col responsive — celular Tailscale
- Empty state graceful pra cada bucket (sem rubricas matching)
- Sparkline inline SVG (zero JS chart lib) — 30 pontos max

---

## Automation hooks (faz)

- `Inertia::defer` lazy props (D-14 pattern)
- `OtelHelper::span` instrumentation custo agregado
- `mcp_scorecard_ai_suggestions` query window 30d único SELECT
- YAML parse `symfony/yaml` cache OPCache (filesystem)

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO dispara LLM (AiScorecardJudge) no GET — separado em job
- ❌ NÃO recomputa rubricas YAML no GET — só lê
- ❌ NÃO escreve auditoria no GET
- ❌ NÃO mostra PII (rubricas YAML não contêm PII; defense in depth via PiiRedactor no judge)

---

## Métricas de sucesso

- ✅ Wagner abre `https://admin.oimpresso.com/governance-v4` → 4 buckets <2s
- ✅ Time tentando acessar → 403 (IsWagner)
- ✅ AI baseline 30d gera ≥3 sugestões úteis em modules-piloto (Jana/Admin)
- ✅ Paired violations destacadas em buckets críticos (CrossCutting)

---

## Pendências pós-Wave 24

- [ ] Job/command `governance:ai-baseline-run` (alimenta `mcp_scorecard_ai_suggestions`)
- [ ] Pest matriz Inertia render IsWagner gate
- [ ] Visual comparison Cowork mockup F1.5 (skip Wave 24, dashboard interno)
- [ ] Após 30d baseline, Wagner decide se delta-AI vira sub-rule oficial OR permanece observacional

## Pendências pós-Wave 27

- [x] Drift visualization (banner top + filtro cliente)
- [x] Status badges (ok/warn/crit) + filtro
- [x] p99 lat inline (graceful Wave 26)
- [x] Smoke Pest controller resolves + props defer existem
- [ ] Wave 26 `mcp_observability_aggregates_daily` migration ativa (atualmente fallback graceful)
- [ ] Drift threshold ±5pts configurável via `config('admin.governance.drift_threshold_pts')` (atualmente const)
- [ ] Filtro server-side (`?status=crit&drift=1` query string) se payload >100 módulos
