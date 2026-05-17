---
tela: Essentials/Documents/Index
controller: Modules\Essentials\Http\Controllers\DocumentController@index
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

# Screen Review — Essentials/Documents/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Essentials › Documentos`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104); tela 552 linhas merece spec formal
- ✅ Controller `DocumentController@index` usa `Inertia::defer` **3×** — boas práticas
- 🔴 Tela MUITO grande (552 linhas) — candidato urgente a decomposição em sub-componentes `_components/`
- ⚠ Sem `useMemo`/`useCallback` detectado — alto risco de re-render cascata em tela grande; smoke vai medir
- ⚠ Sem `localStorage` prefix `oimpresso.documents.*` — filtros + view selecionada não persistem
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 defer usado no Controller → first_paint provavelmente ok
- 🔴 P0: tela 552 linhas sem memoização → re-render perceptível ao digitar/filtrar
- 🟡 P1: localStorage prefix ausente
- 🟡 P1: charter ausente

**Pest GUARD recomendado próximo round:**
- Aderência `Inertia::defer` permanece (não regredir)
- Cross-tenant biz=1 vs biz=99
- Upload documento respeita business_id no storage path

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Documents/Index.tsx` ou `DocumentController@index`.
