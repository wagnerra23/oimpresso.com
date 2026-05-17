---
tela: Atendimento/CaixaUnificada/Index
controller: Modules\Whatsapp\Http\Controllers\Admin\CaixaUnificadaController@index
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

# Screen Review — Atendimento/CaixaUnificada/Index

> Append-only — rounds anteriores NUNCA editados. Cada merge que toque a tela aciona skill `tela-smoke-pos-merge` (Tier B) → novo round abaixo.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente (`./Index.charter.md`)
- Controller: `CaixaUnificadaController@index` usa `Inertia::defer` ✓ (canon `inertia-defer-default`)
- Tela canal-agregador (5 sub-componentes V4: ChannelChips, Composer, ContextSidebar, ConversationList, ConversationThread) — pattern tri/quad-pane denso
- Multi-tenant: assumido `business_id` global scope nos models WhatsappChannel/Conversation (verificar smoke)

**Smoke browser MCP:** **pendente** — Wagner aprova um caminho:
1. Tailscale CT 100 temporário
2. Bypass `BROWSER_MCP_LOCAL=true` em dev
3. Rota fora `/admin/` só pro smoke

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança média — defer aplicado mas 5 panes simultâneos podem inflar bundle JS
- console errors: 0 esperado se sub-componentes V4 estáveis
- 1280/1440 sem scroll: tri/quad-pane DENSO requer validação manual (risco real)

**Desvios potenciais:**
- TBD smoke — possível scroll horizontal em 1280 (panes muito densos)
- Validar fallback quando 0 conversas/canais (estado vazio)

**Pest GUARD pendente:**
- Cenários defer obrigatório, RBAC, multi-tenant cross biz=1 vs biz=99, fallback vazio

**Decisão Wagner:** [pendente]

---

## Próximos rounds

A skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Index.tsx`, sub-componentes V4 ou `CaixaUnificadaController`.
