---
tela: Produto/BulkEdit
controller: App\Http\Controllers\ProductController@bulkEdit
charter: ./BulkEdit.charter.md
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

# Screen Review — Produto/BulkEdit

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ProductController@bulkEdit` usa `Inertia::defer` ✓
- Edição em massa — possivelmente upload CSV ou seleção da lista; preview diff antes commit

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança média (preview pode inflar com N produtos)
- console errors: 0 esperado
- 1440/1280 sem scroll: tabela preview densa

**Desvios potenciais:**
- Preview diff (antes/depois) — UI clara obrigatória
- Validação inline antes commit
- Multi-tenant: bulk só afeta produtos do business
- Undo após commit (impossível?) — confirmação obrigatória

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant (CRITICAL — bulk update biz=1 NÃO afeta biz=99), preview accuracy

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
