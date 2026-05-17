---
tela: Atendimento/Inbox/Index
controller: Modules\Whatsapp\Http\Controllers\Admin\InboxController@index
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

# Screen Review — Atendimento/Inbox/Index

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `InboxController@index` usa `Inertia::defer` ✓ (origem D-14 incident — switch conversa 300→50ms, ver `inertia-defer-default` skill)
- Tela inbox WhatsApp unificada — `ChannelSelector` + thread + composer; alvo de partial reload (`thread`/`messages` eager — exceção defer documentada)
- Pattern canon (origem da skill defer) → benchmark de qualidade

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta — defer + partial reload já calibrado D-14
- console errors: 0 esperado
- 1440/1280 sem scroll: layout tri-pane já validado em prod biz=1

**Desvios potenciais:**
- Regressão D-14 se defer remover dos eager (thread/messages) — Pest GUARD obrigatório
- Centrifugo realtime listener cleanup (memory leak em troca de conversa)

**Pest GUARD pendente:**
- Defer aplicado em props pesadas, thread/messages EAGER (anti-regressão D-14), RBAC, multi-tenant

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
