---
tela: Financeiro/Relatorios/Index
controller: Modules\Financeiro\Http\Controllers\RelatoriosController@index
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

# Screen Review — Financeiro/Relatorios/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Financeiro › Relatórios`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104); tela com 592 linhas merece spec formal
- 🔴 Controller `RelatoriosController@index` tem 6 paginate/with mas **0 `Inertia::defer`** — relatórios são intensivos em DB; payload eager estoura UX target. Violação RUNBOOK-inertia-defer-pattern
- 🔴 Tela MUITO grande (592 linhas) — candidato urgente a decomposição em sub-componentes `_components/`; risco de re-render cascata
- ✓ 2 ocorrências `useMemo`/`useCallback` (insuficiente pra 592 linhas)
- ⚠ Sem `localStorage` prefix `oimpresso.relatorios.*` — relatório selecionado + filtros não persistem (UX miss crítico em tela de uso recorrente)
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🔴 P0: ausência total de `Inertia::defer` em tela de relatórios (queries pesadas por natureza)
- 🔴 P0: tela 592 linhas precisa decomposição — risco de re-render cascata + bundle inchado
- 🟡 P1: localStorage prefix ausente — UX miss recorrente
- 🟡 P1: charter ausente

**Pest GUARD recomendado próximo round:**
- Cross-tenant biz=1 vs biz=99 (CRÍTICO — vazar relatório = grave Tier 0)
- Cada relatório individual respeita business_id
- Defer pattern aplicado pós-refactor

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Relatorios/Index.tsx` ou `RelatoriosController@index`.
