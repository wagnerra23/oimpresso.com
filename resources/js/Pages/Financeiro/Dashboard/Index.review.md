---
tela: Financeiro/Dashboard/Index
controller: Modules\Financeiro\Http\Controllers\DashboardController@index
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

# Screen Review — Financeiro/Dashboard/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Financeiro › Dashboard`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104); dashboard é tela showcase, charter formal recomendado
- ✅ Controller `DashboardController@index` usa `Inertia::defer` **5×** — boas práticas seguidas (KPIs + gráficos + agregações via defer)
- ⚠ Tela grande (415 linhas) — alta complexidade visual; smoke deve medir mount
- ✓ 3 ocorrências `useMemo`/`useCallback` (esperado mais pra gráficos; smoke vai dizer se há re-render desnecessário)
- ⚠ Sem `localStorage` prefix `oimpresso.financeiro-dashboard.*` — range de período (mês/trimestre/ano) não persiste
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 defer usado → first_paint provavelmente atinge meta
- 🟡 P1: tela 415 linhas pode ter sub-componentes não memoizados → re-renders
- 🟡 P1: localStorage prefix ausente

**Pest GUARD recomendado próximo round:**
- Aderência `Inertia::defer` permanece (não regredir)
- KPIs respeitam business_id (cross-tenant biz=1 vs biz=99)
- Range de período altera defer payload corretamente

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Dashboard/Index.tsx` ou `DashboardController@index`.
