---
tela: Essentials/Todo/Index
controller: Modules\Essentials\Http\Controllers\ToDoController@index
charter: (ausente — recomendado criar)
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
created_by: W31 Bulk Review Round 1 (Essentials)
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Essentials/Todo/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Essentials › Tarefas`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104); tela 476 linhas merece spec formal (Kanban tarefas)
- ✅ Controller `ToDoController@index` usa `Inertia::defer` **3×** — boas práticas
- 🔴 Tela MUITO grande (476 linhas) — candidato a decomposição em sub-componentes `_components/`
- ✅ 8 ocorrências `useMemo`/`useCallback` — boa memoização proporcional
- ⚠ Sem `localStorage` prefix `oimpresso.todo-index.*` — filtros (status, prioridade, assigned) + view (lista/kanban) não persistem
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 defer usado → first_paint provavelmente ok
- 🟢 useMemo proporcional → re-render controlado
- 🟡 P1: tela 476 linhas — verificar decomposição via smoke
- 🟡 P1: localStorage prefix ausente
- 🟡 P1: charter ausente

**Pest GUARD recomendado próximo round:**
- Aderência `Inertia::defer` permanece
- Cross-tenant biz=1 vs biz=99
- Filtros + Kanban scopados business_id

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Todo/Index.tsx` ou `ToDoController@index`.
