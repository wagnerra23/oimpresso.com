---
tela: Financeiro/ContasReceber/Index
controller: Modules\Financeiro\Http\Controllers\ContaReceberController@index
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

# Screen Review — Financeiro/ContasReceber/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Financeiro › Contas a Receber`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104)
- 🔴 Controller `ContaReceberController@index` tem 2 paginate/with mas **0 `Inertia::defer`** — lista AR pode crescer rápido (volume vendas); payload eager. Violação RUNBOOK-inertia-defer-pattern
- ✓ Tela média (213 linhas)
- ✓ 3 ocorrências `useMemo`/`useCallback`
- ⚠ Sem `localStorage` prefix `oimpresso.contas-receber.*` — filtros range + cliente + status não persistem
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🔴 P0: ausência `Inertia::defer` em tela AR — ROTA LIVRE biz=4 tem alto volume vendas, eager pode estourar UX target
- 🟡 P1: localStorage prefix ausente
- 🟡 P1: charter ausente

**Pest GUARD recomendado próximo round:**
- Cross-tenant biz=1 vs biz=99 (CRÍTICO — AR vazar = quebra Tier 0)
- Filtros range data + status + cliente
- Paginate respeita business_id global scope

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `ContasReceber/Index.tsx` ou `ContaReceberController@index`.
