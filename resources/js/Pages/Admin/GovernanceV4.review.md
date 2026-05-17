---
tela: Admin/GovernanceV4
controller: Modules\Admin\Http\Controllers\GovernanceV4DashboardController@indexV2
charter: ./GovernanceV4.charter.md
current_round: 1
status: pending-wagner
created_at: 2026-05-17
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Admin/GovernanceV4

> Append-only — rounds anteriores NUNCA editados. Cada merge que toque a tela aciona skill `tela-smoke-pos-merge` (Tier B) → novo round abaixo.

---

## Round 1 — 2026-05-17 (criada W29)

**Status:** pending-wagner

**Entregue (W29 Agents A + B + C):**
- Tela tri-pane copy `kb/Index.v2.tsx` (blueprint validado Wagner ONDA 2 — gate F1.5 SKIP)
- `BucketSidebar.tsx` 4 buckets canônicos + accordion "Wave History W11-W28" (timeline cronológica)
- `ModuleList.tsx` lista módulos do bucket selecionado + filter chips pills `rounded-full`: `meta-only` / `drift` / score range (60-79 / 80-89 / ≥90)
- `ModuleReader.tsx` header KPIs nota total + bucket + meta + status + sparkline 30d; 9 dimensões D1-D9 com progress bars + paired indicator badge; lista Initiatives open + `AiSuggestionPanel.tsx` READ-ONLY
- `DriftAlertBanner.tsx` topo fixo vermelho persistente se >0 drifts >5pts/7d
- `CommandPaletteV4.tsx` ⌘K busca cross-domínio (módulos / initiatives / waves / ADRs)
- `HealthPanelV4.tsx` ScorecardSnapshot cron + AI baseline 30d countdown + OTel collector ping
- Quick actions header: "Abrir Initiative manual" / "Override bucket" / "Marcar revisão" / "Trigger drift snapshot now"
- Persistência localStorage prefix `oimpresso.governance.v4.*`
- Fallback MOCK quando `v4_enabled=false`

**Smoke browser MCP:** **pendente** — Wagner precisa decidir 1 de 3 opções W29-smoke:
1. Ativar Tailscale temporário pra browser MCP CT 100 (~5min setup)
2. Aprovar bypass dev-only via `php artisan serve` + `BROWSER_MCP_LOCAL=true`
3. Promover rota fora `Admin/` (ex: `/governance/v4` sem IsWagner) só pro smoke run

**UX targets esperados (sem smoke ainda):**
- first_paint_ms: meta 800ms — 5 props pesadas em `Inertia::defer` (modules / scorecards / drifts / initiatives / aiSuggestions) → confiança alta atinge meta
- console errors: 0 esperado (Tailwind canon, sem libs custom)
- 1440 + 1280 sem scroll horizontal: copy direto kb_v2 que já valida ambos

**Desvios potenciais (sem smoke pra confirmar):**
- TBD — Wagner roda smoke manual ou bypass primeiro

**Pest GUARD pendente W29-D:**
- 11 cenários listados em `GovernanceV4.charter.md` §"Métricas vivas"
- Validam: defer obrigatório, IsWagner 403, localStorage prefix, fallback v3, ⌘K abre, DriftAlertBanner trigger, withoutGlobalScopes documentado

**Decisão Wagner:** [pendente]

---

## Próximos rounds

A skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `GovernanceV4.tsx` ou Controller `indexV2`. Comparará UX targets atuais vs charter + destacará desvios.
