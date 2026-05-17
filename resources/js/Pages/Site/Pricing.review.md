---
tela: Site/Pricing
controller: Modules\Superadmin\Http\Controllers\PricingController@index
charter: null
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Site/Pricing

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — criar `Pricing.charter.md` pré-req Round 2.
- Controller: `PricingController@index` — **NÃO usa `Inertia::defer`** ⚠ (canon violado)
- Tabela planos pricing pública — cards comparativos + CTA signup

**Smoke browser MCP:** **pendente** (página pública, alto valor — converte leads).

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: ⚠ risco se planos vêm de query pesada sem defer
- console errors: 0 OBRIGATÓRIO (high-conversion page)
- 1440/1280/768/375 sem scroll: cards comparativos costumam quebrar em mobile

**Desvios potenciais:**
- **Inertia::defer ausente** (planos podem ser eager pequeno OK, mas verificar)
- Toggle anual/mensal (estado UI client-side)
- Comparison table densa (feature × plan matrix) — risco mobile
- CTA conversion tracking (analytics gtag etc)

**Pest GUARD pendente:**
- Pública sem auth, sem business_id leak, defer recomendado se planos crescerem

**Decisão Wagner:** [pendente] — avaliar se defer vale (planos pequenos talvez não justifica).

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
