---
tela: Essentials/Knowledge/Create
controller: Modules\Essentials\Http\Controllers\KnowledgeBaseController@create
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

# Screen Review — Essentials/Knowledge/Create

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb dinâmico
- ⚠ Charter AUSENTE (`./Create.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104)
- ⚠ Controller `KnowledgeBaseController@create` props leves (form vazio + categorias) — `Inertia::defer` opcional aqui
- ✓ Tela média (207 linhas)
- ⚠ Sem `useMemo`/`useCallback` detectado — form one-shot, baixo risco
- ⚠ Sem `localStorage` prefix `oimpresso.knowledge-create.*` — draft form não persiste (UX miss: artigo longo perdido se fecha)
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 P2: tela pequena, baixo risco UX
- 🟡 P1: draft form não persiste — artigo KB pode ser longo, perda dados ruim
- 🟡 P1: charter ausente

**Pest GUARD recomendado próximo round:**
- POST `/essentials/knowledge` valida payload obrigatório
- Artigo criado scopa business_id correto
- Cross-tenant biz=1 vs biz=99

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Knowledge/Create.tsx` ou `KnowledgeBaseController@create`.
