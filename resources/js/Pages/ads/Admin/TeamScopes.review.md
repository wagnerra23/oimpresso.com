---
tela: ads/Admin/TeamScopes
controller: ADS\Http\Controllers\Admin\TeamScopesController (inferido)
charter: null
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
created_by: bulk-w31-agent-static
ux_targets:
  first_paint_ms: 800
  no_console_errors: true
  responsive_1440: true
  responsive_1280: true
---

## Round 1 — 2026-05-17 (bulk review estática)

**Status:** awaiting-smoke-browser

**Análise estática:**
- Charter: ausente
- AppShellV2: sim
- Inertia::defer: 0 props (`users` + `modules` — pra 5 pessoas time small, eager ok)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (ShieldCheck, ShieldAlert, Eye, Edit3, Play, GitCommit) + shared (PageHeader, KpiGrid, KpiCard) + shadcn (Card/Badge/Button/Switch)
- `useState` user selecionado

**Risco prévio:**
- Charter ausente
- Tela governance per-user — `expires_at` UI precisa estar visível (RBAC temporal)
- Toggles concorrentes (Switch × 4 permissões × N módulos) — optimistic UI? rollback?
- Sem audit log na própria tela (quem alterou quando)

**Smoke pendente:**
- screenshot 1440 + 1280
- toggle Switch rápido 2x — race condition?
- mudar selectedUser — state preserva pendentes?

**Decisão Wagner:** [pendente — recomendar audit trail visível + expiry UI]
