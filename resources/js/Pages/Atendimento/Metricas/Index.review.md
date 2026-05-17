---
tela: Atendimento/Metricas/Index
controller: Modules\Whatsapp\Http\Controllers\Admin\MetricsController@index
charter: ./Index.charter.md
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Atendimento/Metricas/Index

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `MetricsController@index` usa `Inertia::defer` ✓
- Dashboard métricas atendimento — TMA, FRT, SLA, volume canal, segmentação agente

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança média (agregações histórico)
- console errors: 0 esperado
- 1440/1280 sem scroll: dashboards são candidatos clássicos a quebrar 1280

**Desvios potenciais:**
- Charts pesados — validar bundle
- Range picker período + presets
- Multi-tenant: agregações por business
- Drill-down conversa específica

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant, vazio (0 conversas no período)

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
