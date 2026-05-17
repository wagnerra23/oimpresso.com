---
tela: Financeiro/Fluxo/Index
controller: Modules\Financeiro\Http\Controllers\FluxoController@index
charter: ./Index.charter.md
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

# Screen Review — Financeiro/Fluxo/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039)
- ✓ Charter EXISTE (`./Index.charter.md`) — Fluxo de Caixa tem doc canônico (visual-comparison rodou)
- ⚠ Controller `FluxoController@index` delega tudo pra `FluxoCaixaService->build($shape)` mas **0 `Inertia::defer`** — agregações pesadas (D30/D60/D90 saldo + previsto vs realizado) sobem eager. Risco P0.
- ✓ Tela média (209 linhas)
- ✓ 2 ocorrências `useMemo`/`useCallback`
- ⚠ Sem `localStorage` prefix `oimpresso.fluxo.*` — range/preset não persiste
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🔴 P0: agregações fluxo de caixa via Service sem `Inertia::defer` — query pesada eager pode estourar UX target em business com volume
- 🟡 P1: localStorage prefix ausente — preset período não persiste

**Pest GUARD recomendado próximo round:**
- Service `FluxoCaixaService->build()` respeita business_id (Tier 0 ADR 0093)
- Refactor Controller pra `Inertia::defer(fn () => $service->build(...))` validado por GUARD
- Cross-tenant biz=1 vs biz=99

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Fluxo/Index.tsx` ou `FluxoController@index`.
