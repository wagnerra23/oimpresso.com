---
tela: Essentials/Settings/Index
controller: Modules\Essentials\Http\Controllers\EssentialsSettingsController@index
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

# Screen Review — Essentials/Settings/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `HRM › Configurações`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104)
- ⚠ Controller `EssentialsSettingsController@index` props leves (1 row settings) — `Inertia::defer` opcional aqui
- ✓ Tela média (220 linhas)
- ⚠ Sem `useMemo`/`useCallback` detectado — form settings, baixo risco
- ⚠ Sem `localStorage` prefix `oimpresso.essentials-settings.*` — tab/seção ativa não persiste
- ✓ Sem `bg-*-N` crus problemáticos
- ⚠ Breadcrumb diz "HRM" mas path Essentials — divergência IA com sidebar (mesma observação Holidays)

**Riscos identificados (sem smoke):**
- 🟢 P2: tela settings, baixo risco UX
- 🟡 P1: charter ausente
- 🟢 P2: breadcrumb HRM vs path Essentials

**Pest GUARD recomendado próximo round:**
- Settings save preserva business_id (Tier 0 ADR 0093)
- Cross-tenant biz=1 vs biz=99 (NÃO sobrescrever settings de outro business)

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Settings/Index.tsx` ou `EssentialsSettingsController@index`.
