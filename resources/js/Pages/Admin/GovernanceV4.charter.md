---
page: Admin/GovernanceV4
controller: Modules\Admin\Http\Controllers\GovernanceV4DashboardController@indexV2
route: admin.governance.v4
status: draft
owner: [W] Wagner
persona_principal: Wagner / governance command center (1440px desktop)
persona_secundaria: time MCP futuro (Felipe/Maiara — read-only ONDA 7+)
charter_version: 1.0
charter_at: 2026-05-17
related_adrs:
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0161-governance-v4-aposentar-hacks-0159-redundantes
  - 0162-otel-collector-prod-observability
  - 0163-governance-v4-metas-alcancadas-ondas-19-28
  - 0039-ui-chat-cockpit-padrao
  - 0104-processo-mwart-canonico-unico-caminho
related_briefing: ../../../memory/requisitos/Governance/BRIEFING.md
related_predecessor_visual: resources/js/Pages/kb/Index.v2.tsx
mwart_pattern_reuse:
  blueprint_canonical: resources/js/Pages/kb/Index.v2.tsx (tri-pane validado prod)
  blueprint_screenshot_approval: SKIP (kb_v2 já validado Wagner ONDA 2)
  derived_screens: [Admin/GovernanceV4]
  divergence_from_blueprint: "Sidebar=Buckets (não Categorias) + Lista=Módulos (não Nós) + Leitor=ModuleBreakdown (não NodeReader markdown)"
---

# Charter — `resources/js/Pages/Admin/GovernanceV4.tsx` (DRAFT)

> Tela governance v4 tri-pane. Copia EXATAMENTE pattern visual de `resources/js/Pages/kb/Index.v2.tsx` (blueprint validado Wagner ONDA 2 — gate F1.5 SKIP). Coexiste com Admin/Governance.tsx (v3) durante ONDA 7 até cutover via flag `v4_enabled`.

## Mission

Command center governance v4 — visualizar 28 waves consecutivas (W11-W28) + 34 módulos × 4 buckets canônicos + drift detection + Initiatives (Cortex-style) + AI suggestions READ-ONLY. Permitir Wagner ajustar desvios via 3 mecanismos: criar Initiative manual / disparar bucket override workflow / validar AI suggestion. Layout tri-pane (sidebar buckets + lista módulos + leitor breakdown) portado do blueprint kb_v2 com divergência semântica (buckets ≠ categorias, módulos ≠ nós, breakdown ≠ markdown).

## Goals (faz)

1. **BucketSidebar** 4 buckets canônicos com contagem módulos: `vertical_client_facing` (5) / `cross_cutting_infra` (7) / `ai_central` (2) / `functional_horizontal` (20) + accordion "Wave history W11-W28" (timeline cronológica)
2. **ModuleList** módulos do bucket selecionado, filter chips pills `rounded-full`: `meta-only` (abaixo target bucket) / `drift` (delta >5pts últimos 7d) / score range (60-79 / 80-89 / ≥90)
3. **ModuleReader** header KPIs nota total + bucket + meta + status (✓/⚠/✗) + sparkline 30d nota; abaixo 9 dimensões (D1-D9) com progress bars + paired indicator badge; embaixo lista Initiatives open (deadline countdown) + AiSuggestionPanel READ-ONLY
4. **CommandPaletteV4** ⌘K busca cross-domínio: módulos / initiatives / waves / ADRs
5. **DriftAlertBanner** topo fixo vermelho persistente se >0 drifts >5pts/7d detectados (snapshot diário)
6. **Quick actions** header: "Abrir Initiative manual" / "Override bucket" (PR workflow `bucket-change-approved`) / "Marcar revisão" / "Trigger drift snapshot now"
7. **HealthPanelV4** acionável: ScorecardSnapshot cron last_run + AI baseline 30d countdown + OTel collector ping CT 100
8. **Fallback MOCK** quando `v4_enabled=false` (mostra v3 score como placeholder + banner "v4 em rollout ONDA 7+")
9. **Persistência localStorage** prefix `oimpresso.governance.v4.*` (último bucket selecionado, último módulo, filter state, accordion expandido)

## Non-Goals (NÃO faz)

> Wagner aprova lista. Cada item vira Pest GUARD.

- Editar scorecard YAML inline (vai pra `Admin/GovernanceYaml.charter.md` futuro)
- Spawn waves direto da UI (continua via `/evolui` slash command Claude Code)
- Substituir `php artisan module:grade-v4 --all` CLI (UI complementa, não substitui)
- Edição de ADRs inline (vai pra `Admin/AdrEditor.charter.md` futuro)
- Dashboard cross-business (Wagner-only via IsWagner middleware — governance é repo-wide intencional)
- Substituir `Admin/Governance.tsx` (v3 atual) — paralelo até cutover ONDA 7 via flag

## UX Targets

- First-paint < 800ms (5 props pesadas em `Inertia::defer`: modules / scorecards / drifts / initiatives / aiSuggestions)
- DriftAlertBanner vermelho persistente se ≥1 drift detectado (não dismissable até resolução)
- Sparkline trend instantâneo (Recharts ou linha SVG inline leve — sem network call extra)
- ⌘K CommandPaletteV4 abre < 100ms (cache local 200 primeiros módulos + lazy ADRs/waves)
- 1440px desktop sem scroll horizontal (Wagner monitor primário)
- Cores semânticas Cockpit V2 (NÃO `bg-(red|green)-N` cru) — drift vermelho via `text-destructive` token
- Tipografia canon ADR 0110

## UX Anti-patterns

- Modal pra detalhe módulo (canon = pane direito tri-pane — divergir = retrabalho gate visual)
- Tabs `border-b-2` em filter chips (canon = pills `rounded-full`)
- `sessionStorage` (canon = `localStorage` prefixed `oimpresso.governance.v4.*`)
- Cor crua hardcoded sem semantic token (`bg-red-500` proibido)
- Atualização tempo-real Centrifugo (ONDA 7 — não W29; polling 30s ou refresh manual)
- Edição inline de scorecard/ADR (read-mostly tela; ações = botões disparam workflows externos)

## Automation Hooks

- `GET /admin/governance/v4` — `GovernanceV4DashboardController@indexV2` (Inertia::defer obrigatório nas 5 props pesadas — RUNBOOK-inertia-defer-pattern)
- `POST /admin/governance/v4/initiative` — criar Initiative manual (Wagner-only, IsWagner middleware)
- `POST /admin/governance/v4/override-bucket` — trigger PR workflow GitHub Actions com label `bucket-change-approved`
- `GET /admin/governance/v4/refresh` — re-run `module:grade-v4` + `ScorecardSnapshotCommand` on-demand (Wagner action)
- `GET /admin/governance/v4/health` — ping ScorecardSnapshot cron + AI baseline + OTel collector
- IsWagner middleware (canon admin — governance é Wagner-only por ora)

## Automation Anti-hooks

> Wagner aprova lista. Vira Pest GUARD.

- NÃO envia emails/SMS/WhatsApp ao abrir (read-mostly)
- NÃO escreve no DB no render (read-only — initiatives create é POST explícito, não Inertia::render)
- NÃO dispara `module:grade-v4` automático no render (custoso; refresh é ação Wagner)
- NÃO chama Brain B/Sonnet no render (AI suggestions já materializadas via baseline 30d cron)
- NÃO acessa scorecards de outro `business_id` (governance é repo-wide intencional — documentar `withoutGlobalScopes` com `// SUPERADMIN: governance is repo-wide`)
- NÃO loga PII em audit (governance não toca dados cliente, mas sanitizer obrigatório por convenção)
- NÃO permite acesso fora Wagner (IsWagner middleware fail-secure)

## Sub-components em `_components/` (W29-C scope)

10 sub-components a serem criados pela W29-C (Agent C):

- `BucketSidebar.tsx` — 4 buckets + contagem + accordion Wave history W11-W28
- `ModuleList.tsx` — lista filtrada do bucket + filter chips pills
- `ModuleReader.tsx` — KPIs nota + sparkline + 9 dimensões + initiatives + ai panel
- `DimensionProgressBar.tsx` — progress bar D1-D9 com paired indicator badge
- `SparklineTrend.tsx` — linha SVG 30 pontos (no network call extra)
- `InitiativeBadge.tsx` — deadline countdown + status badge (open/in_progress/done/overdue)
- `DriftAlertBanner.tsx` — vermelho fixo topo se drifts > 0 (não dismissable)
- `AiSuggestionPanel.tsx` — READ-ONLY 30d list (suggestions baseline AI)
- `HealthPanelV4.tsx` — extends kb HealthPanel (ScorecardSnapshot cron / AI baseline / OTel ping)
- `CommandPaletteV4.tsx` — extends kb CommandPalette cross-search (módulos/initiatives/waves/ADRs)

## Métricas vivas (Pest GUARD pendente W29-D)

```php
it('renders /admin/governance/v4 in <800ms p95 with 34 modules + defer')
it('does not dispatch jobs on render (read-mostly)')
it('does not mutate state on GET')
it('blocks non-Wagner users via IsWagner middleware (403)')
it('renders at 1440px without horizontal scroll')
it('uses localStorage prefix oimpresso.governance.v4.* (never sessionStorage)')
it('falls back to v3 score when v4_enabled=false')
it('keyboard ⌘K opens CommandPaletteV4')
it('shows DriftAlertBanner when drifts >5pts in 7d exist')
it('respects withoutGlobalScopes only for governance repo-wide queries')
it('all 5 heavy props use Inertia::defer (modules, scorecards, drifts, initiatives, aiSuggestions)')
```

## Comparáveis canônicos (`mwart-comparative` V4)

- **Linear** (command palette ⌘K densidade + tri-pane issue navigator) — referência principal layout
- **Cortex** (Initiatives + Scorecards governance pattern) — referência conceitual buckets + initiatives
- **Datadog** (drift alert banner persistente + sparkline trend) — referência visual drift
- **Excluir:** Jira (overhead enterprise), Confluence (sem tri-pane), Notion (sem governance score)

## Refs

- Blueprint canônico tri-pane: [`resources/js/Pages/kb/Index.v2.tsx`](../kb/Index.v2.tsx) (validado Wagner ONDA 2)
- Charter blueprint: [`kb/Index.v2.charter.md`](../kb/Index.v2.charter.md)
- [ADR 0160 — Governance v4 scoped scorecards + buckets](../../../memory/decisions/0160-governance-v4-scoped-scorecards-buckets.md)
- [ADR 0161 — Aposentar hacks 0159 redundantes](../../../memory/decisions/0161-governance-v4-aposentar-hacks-0159-redundantes.md)
- [ADR 0162 — OTel collector prod observability](../../../memory/decisions/0162-otel-collector-prod-observability.md)
- [ADR 0163 — Metas alcançadas ondas 19-28](../../../memory/decisions/0163-governance-v4-metas-alcancadas-ondas-19-28.md)
- [ADR 0039 — UI Chat Cockpit Padrão](../../../memory/decisions/0039-ui-chat-cockpit-padrao.md)
- [ADR 0104 — Processo MWART canônico](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0110 — Cockpit Pattern V2](../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- BRIEFING: [`memory/requisitos/Governance/BRIEFING.md`](../../../memory/requisitos/Governance/BRIEFING.md)
- Backend: `Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php` (indexV2 pendente W29-B)
- RUNBOOK Inertia::defer: [`memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md`](../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | W29 Agent A | Charter draft criado pra GovernanceV4.tsx (tri-pane copy kb_v2). Gate F1.5 SKIP (kb_v2 blueprint já validado Wagner ONDA 2). Pendente aprovação Wagner em Non-Goals + Anti-hooks pra `status: live`. |
