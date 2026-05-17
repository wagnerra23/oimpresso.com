---
tela: Financeiro/Unificado/Index
controller: Modules\Financeiro\Http\Controllers\UnificadoController@index
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

# Screen Review — Financeiro/Unificado/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) — charter confirma
- ✓ Charter EXISTE (`./Index.charter.md`) + tem flag `Cockpit ADR 0039`
- 🔴 Controller `UnificadoController@index` tem 9 paginate/with mas **0 `Inertia::defer`** — Unificado é visão consolidada AR+AP+saldo, payload eager pesado. Violação RUNBOOK-inertia-defer-pattern
- 🔴 Tela grande (455 linhas) — candidato a decomposição em sub-componentes
- ✓ 14 ocorrências `useMemo`/`useCallback` — boa memoização (proporcional ao tamanho)
- ⚠ Sem `localStorage` prefix `oimpresso.unificado.*` — filtros + período + view selecionada não persistem
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🔴 P0: ausência `Inertia::defer` em tela consolidada AR+AP (visão executiva, queries pesadas)
- 🟡 P1: tela 455 linhas — verificar via smoke se há re-render cascata
- 🟡 P1: localStorage prefix ausente

**Pest GUARD recomendado próximo round:**
- Cross-tenant biz=1 vs biz=99 (CRÍTICO — visão executiva consolidada)
- Aderência `Inertia::defer` pós-refactor
- Sem mistura de business_id em joins consolidados (alto risco vazar)

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Unificado/Index.tsx` ou `UnificadoController@index`.
