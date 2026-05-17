---
tela: Financeiro/ContasPagar/Index
controller: Modules\Financeiro\Http\Controllers\ContaPagarController@index
charter: (ausente — recomendado criar)
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
created_by: W31 Bulk Review Round 1 (Financeiro)
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Financeiro/ContasPagar/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Financeiro › Contas a Pagar`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104)
- 🔴 Controller `ContaPagarController@index` tem 4 paginate/with mas **0 `Inertia::defer`** — lista de AP pode ter milhares; payload eager estoura UX target. Violação RUNBOOK-inertia-defer-pattern
- ✓ Tela média (256 linhas)
- ✓ 3 ocorrências `useMemo`/`useCallback`
- ⚠ Sem `localStorage` prefix `oimpresso.contas-pagar.*` — filtros range data + status + fornecedor não persistem (UX miss recorrente em telas AR/AP)
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🔴 P0: ausência `Inertia::defer` em tela AR/AP onde dataset cresce com tempo (vendedores antigos têm centenas)
- 🟡 P1: localStorage prefix ausente
- 🟡 P1: charter ausente diverge canon MWART

**Pest GUARD recomendado próximo round:**
- Cross-tenant biz=1 vs biz=99 (CRÍTICO — vazar AP cross-tenant é violação Tier 0)
- Filtros range data + status + fornecedor
- Paginate respeita scoped business_id

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `ContasPagar/Index.tsx` ou `ContaPagarController@index`.
