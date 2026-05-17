---
tela: Financeiro/Boletos/Index
controller: Modules\Financeiro\Http\Controllers\BoletoController@index
charter: ./Index.charter.md
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

# Screen Review — Financeiro/Boletos/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039)
- ✓ Charter EXISTE (`./Index.charter.md`) — referência canônica
- ✓ 26 ocorrências `useMemo`/`useCallback` — memoização heavy, OK pra grid grande
- ⚠ Controller `BoletoController@index` tem 7 paginate/with mas **0 `Inertia::defer`** — props pesadas (lista boletos + filtros + agregações) sobem todas eager. **Violação RUNBOOK-inertia-defer-pattern** (ADR `inertia-defer-default`). Estimativa: first_paint pode > 800ms.
- ⚠ Tela com 429 linhas — alta complexidade; smoke browser deve medir interatividade após mount
- ✓ Sem `bg-*-N` crus problemáticos
- ⚠ Sem `localStorage` prefix `oimpresso.boletos.*` — filtros/range data não-persistentes entre sessões (UX miss)

**Riscos identificados (sem smoke):**
- 🔴 P0: ausência total de `Inertia::defer` no Controller — payload eager pode estourar UX target
- 🟡 P1: persistência localStorage ausente (filters resetam a cada visita)

**Pest GUARD recomendado próximo round:**
- Aderência `Inertia::defer` em pelo menos `boletos` lista (RUNBOOK)
- Cross-tenant biz=1 vs biz=99
- Filtros range data + status + situação validados

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Boletos/Index.tsx` ou `BoletoController@index`.
