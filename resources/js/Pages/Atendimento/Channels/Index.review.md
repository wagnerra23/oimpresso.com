---
tela: Atendimento/Channels/Index
controller: Modules\Whatsapp\Http\Controllers\Admin\ChannelsController@index
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

# Screen Review — Atendimento/Channels/Index

> Append-only — rounds anteriores NUNCA editados. Cada merge que toque a tela aciona skill `tela-smoke-pos-merge` (Tier B) → novo round abaixo.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ChannelsController@index` usa `Inertia::defer` ✓
- Tela de lista de canais WhatsApp (Baileys + Meta) — provavelmente tabela com status QR/connected/banned
- Multi-tenant: query canais por `business_id` (verificar)

**Smoke browser MCP:** **pendente** (igual demais W31).

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta — lista simples
- console errors: 0 esperado
- 1440/1280 sem scroll: tabela com status badges deve caber

**Desvios potenciais:**
- Validar polling/realtime (Centrifugo) sem leaks de listener
- Estado quando 0 canais paireados (empty state copy)

**Pest GUARD pendente:**
- Defer, RBAC `IsAdminOrTeam`, multi-tenant biz=1 vs biz=99, fallback vazio

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2 após próximo merge que toque tela ou controller.
