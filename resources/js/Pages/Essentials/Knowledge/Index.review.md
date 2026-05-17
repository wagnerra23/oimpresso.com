---
tela: Essentials/Knowledge/Index
controller: Modules\Essentials\Http\Controllers\KnowledgeBaseController@index
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

# Screen Review — Essentials/Knowledge/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Essentials › Base de conhecimento`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104); comparável a blueprint canônico `kb/Index.v2.tsx` (tri-pane) — vale alinhar pattern
- ✅ Controller `KnowledgeBaseController@index` usa `Inertia::defer` **2×** — boas práticas
- ✓ Tela média (240 linhas)
- ⚠ Sem `useMemo`/`useCallback` detectado — verificar via smoke se filtro/busca causa re-render
- ⚠ Sem `localStorage` prefix `oimpresso.knowledge-index.*` — categoria/busca não persistem
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 defer usado → first_paint provavelmente ok
- 🟡 P1: pattern divergente do blueprint canônico `kb/Index.v2.tsx` (tri-pane) — comparar formalmente
- 🟡 P1: localStorage prefix ausente
- 🟡 P1: charter ausente

**Pest GUARD recomendado próximo round:**
- Aderência `Inertia::defer` permanece
- Cross-tenant biz=1 vs biz=99
- Index respeita business_id global scope

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Knowledge/Index.tsx` ou `KnowledgeBaseController@index`.
