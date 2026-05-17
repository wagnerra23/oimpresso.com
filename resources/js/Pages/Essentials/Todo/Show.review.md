---
tela: Essentials/Todo/Show
controller: Modules\Essentials\Http\Controllers\ToDoController@show
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

# Screen Review — Essentials/Todo/Show

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb dinâmico
- ⚠ Charter AUSENTE (`./Show.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104); tela 632 linhas é a maior do batch — merece spec formal
- ⚠ Controller `ToDoController@show` props (todo + comments + history + assignees) — verificar se `Inertia::defer` aplicado nas listas
- 🔴 Tela MUITO grande (632 linhas) — candidato URGENTE a decomposição em sub-componentes `_components/` (comments, history, attachments)
- ✅ 8 ocorrências `useMemo`/`useCallback` — boa memoização proporcional
- ⚠ Sem `localStorage` prefix `oimpresso.todo-show.*` — comentário draft + tab ativa não persistem
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🔴 P0: tela 632 linhas — RE-render cascata risco alto sem decomposição
- 🟡 P1: comentário draft não persiste
- 🟡 P1: charter ausente
- 🟡 P1: localStorage prefix ausente

**Pest GUARD recomendado próximo round:**
- Show respeita business_id (Tier 0 ADR 0093)
- Comments + history scopados business_id
- Cross-tenant biz=1 vs biz=99

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Todo/Show.tsx` ou `ToDoController@show`.
