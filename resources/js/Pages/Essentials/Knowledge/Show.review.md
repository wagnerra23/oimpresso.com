---
tela: Essentials/Knowledge/Show
controller: Modules\Essentials\Http\Controllers\KnowledgeBaseController@show
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

# Screen Review — Essentials/Knowledge/Show

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb dinâmico
- ⚠ Charter AUSENTE (`./Show.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104)
- ⚠ Controller `KnowledgeBaseController@show` props leves (1 artigo) — `Inertia::defer` opcional
- ✓ Tela enxuta (184 linhas)
- ⚠ Sem `useMemo`/`useCallback` detectado — render single shot, baixo risco
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 P2: tela leitura, baixo risco UX
- 🟡 P1: charter ausente
- 🟢 P3: render markdown — smoke valida se sintax highlight gera console warnings (lição kb/Index.v2 round 3)

**Pest GUARD recomendado próximo round:**
- Show respeita business_id (404 se tentar ver artigo de outro business — Tier 0 ADR 0093)
- Cross-tenant biz=1 vs biz=99

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Knowledge/Show.tsx` ou `KnowledgeBaseController@show`.
