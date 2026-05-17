---
tela: Admin/ScreenReview
controller: Modules\Admin\Http\Controllers\ScreenReviewController@index
charter: ./ScreenReview.charter.md
current_round: 1
status: pending-wagner
created_at: 2026-05-17
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Admin/ScreenReview

> Append-only — meta-tela do próprio sistema Screen Review PDCA (ADR 0164 W30). Reviewer reviewing the reviewer.

---

## Round 1 — 2026-05-17 (criada W30)

**Status:** pending-wagner

**Entregue (W30 Agents A + B + C + D):**
- Tela lista todos `<Tela>.review.md` cross-módulos + filter chips por status (`pending-wagner` / `approved` / `rejected` / `needs-iteration`)
- Reader pane mostra round atual + histórico append-only do `.review.md` selecionado
- Quick action "Aprovar round atual" (Wagner-only) edita frontmatter `status: approved` + adiciona "Decisão Wagner: APROVADO" no rodapé do round
- Quick action "Rejeitar com razão" (Wagner-only) abre modal pra razão → append rodapé + `status: rejected` → trigger skill `tela-smoke-pos-merge` próximo merge cria Round N+1
- Cross-link cada review.md pro charter irmão + Controller + última smoke
- Indicador visual desvios `ux_targets` (smoke last vs meta charter) com cores semânticas Cockpit V2
- Persistência localStorage prefix `oimpresso.screen-review.*` (último filter, última review aberta)
- IsWagner middleware (governance Wagner-only por ora)

**Smoke browser MCP:** **pendente** — mesma decisão Wagner W29-smoke (3 opções).

**UX targets esperados:**
- first_paint_ms: meta 800ms — props review.md list em `Inertia::defer`, reader carregado on-click
- console errors: 0 esperado
- 1440 + 1280 sem scroll horizontal: tri-pane responsivo (sidebar collapsa em < 1280)

**Desvios potenciais (sem smoke):**
- TBD

**Pest GUARD pendente:**
- `it('lists all review.md across resources/js/Pages/**')`
- `it('append-only: rejects PATCH on previous rounds')`
- `it('Wagner-only via IsWagner middleware (403 for non-Wagner)')`
- `it('renders at 1440px without horizontal scroll')`
- `it('does not mutate review.md on GET (read-only render)')`

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `ScreenReview.tsx` ou Controller.
