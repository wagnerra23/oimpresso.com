---
tela: Essentials/Reminders/Index
controller: Modules\Essentials\Http\Controllers\ReminderController@index
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

# Screen Review — Essentials/Reminders/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Essentials › Lembretes`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104)
- 🔴 Controller `ReminderController@index` tem 3 paginate/with mas **0 `Inertia::defer`** — lista lembretes eager. Violação RUNBOOK-inertia-defer-pattern
- ⚠ Tela grande (293 linhas)
- ⚠ Sem `useMemo`/`useCallback` detectado — risco re-render
- ⚠ Sem `localStorage` prefix `oimpresso.reminders.*` — filtros (próximos, atrasados, todos) não persistem
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🔴 P0: ausência `Inertia::defer` viola RUNBOOK
- 🟡 P1: tela 293 linhas sem memoização
- 🟡 P1: localStorage prefix ausente
- 🟡 P1: charter ausente

**Pest GUARD recomendado próximo round:**
- Refactor Controller pra `Inertia::defer` validado
- Cross-tenant biz=1 vs biz=99
- Lembretes scopados business_id + por user_id (lembrete privado)

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Reminders/Index.tsx` ou `ReminderController@index`.
