---
tela: Atendimento/Channels/Show
controller: Modules\Whatsapp\Http\Controllers\Admin\ChannelsController@show
charter: null
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

# Screen Review — Atendimento/Channels/Show

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — risco MWART (ADR 0104 §F1). **Criar `Show.charter.md` é pré-req pra Round 2.**
- Controller: `ChannelsController@show` usa `Inertia::defer` ✓
- Tela detalhe de canal individual + tab `ChannelUsersTab.tsx` (gestão usuários per canal)

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança média (detalhe + tabs + métricas histórico)
- console errors: 0 esperado
- 1440/1280 sem scroll: validar tabs ChannelUsersTab + QR re-pair

**Desvios potenciais:**
- Re-pair QR code workflow (estado intermediário)
- Métricas histórico (carga 30d defer obrigatório)
- Multi-tenant: ID canal autorizado ao business sessão

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant cross-biz (canal biz=99 NÃO acessível biz=1), 404 quando ID inexistente

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2 após próximo merge que toque tela ou controller.
