---
tela: Financeiro/Categorias/Index
controller: Modules\Financeiro\Http\Controllers\CategoriaController@index
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

# Screen Review — Financeiro/Categorias/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Financeiro › Categorias` ok
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104) antes do próximo edit estrutural
- ⚠ Controller `CategoriaController@index`: 4 paginate/with + **0 `Inertia::defer`** — lista categorias eager. Risco médio (lista pequena tipicamente)
- ✓ Tela enxuta (198 linhas)
- ✓ 3 ocorrências `useMemo`/`useCallback` — proporcional ao tamanho
- ✓ Sub-componente `CategoriaSheet.tsx` separado (boa separação)
- ⚠ Sem `localStorage` prefix `oimpresso.categorias.*` — busca/filtro não-persistente

**Riscos identificados (sem smoke):**
- 🟡 P1: Charter ausente — diverge canon MWART pós-W30
- 🟢 P2: defer faltando mas dataset tipicamente pequeno (categorias por business)

**Pest GUARD recomendado próximo round:**
- CRUD categoria (store/update/delete) preserva `business_id` (Tier 0 ADR 0093)
- Cross-tenant biz=1 vs biz=99

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Categorias/Index.tsx` ou `CategoriaController@index`.
