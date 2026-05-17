---
tela: Essentials/Holidays/Index
controller: Modules\Essentials\Http\Controllers\EssentialsHolidayController@index
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

# Screen Review — Essentials/Holidays/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `HRM › Feriados`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104)
- ✅ Controller `EssentialsHolidayController@index` usa `Inertia::defer` **3×** — boas práticas
- ⚠ Tela grande (392 linhas) — verificar via smoke decomposição
- ⚠ Sem `useMemo`/`useCallback` detectado — risco re-render em calendário/grid
- ⚠ Sem `localStorage` prefix `oimpresso.holidays.*` — ano selecionado não persiste
- ✓ Sem `bg-*-N` crus problemáticos
- ⚠ Breadcrumb diz "HRM" (categoria legacy) mas tela está em Essentials/ — verificar IA consistência com sidebar

**Riscos identificados (sem smoke):**
- 🟢 defer usado → first_paint provavelmente ok
- 🟡 P1: tela 392 linhas sem memoização — calendário pode re-renderar
- 🟡 P1: localStorage prefix ausente
- 🟡 P1: charter ausente
- 🟢 P2: breadcrumb HRM vs path Essentials — divergência menor

**Pest GUARD recomendado próximo round:**
- Aderência `Inertia::defer` permanece
- Cross-tenant biz=1 vs biz=99
- CRUD feriado respeita business_id

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Holidays/Index.tsx` ou `EssentialsHolidayController@index`.
