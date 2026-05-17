---
tela: ads/Admin/Confidence
controller: ADS\Http\Controllers\Admin\ConfidenceController (inferido)
charter: null
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
created_by: bulk-w31-agent-static
ux_targets:
  first_paint_ms: 800
  no_console_errors: true
  responsive_1440: true
  responsive_1280: true
---

## Round 1 — 2026-05-17 (bulk review estática)

**Status:** awaiting-smoke-browser

**Análise estática:**
- Charter: ausente
- AppShellV2: sim
- Inertia::defer: 0 props (`scores: Score[]` + `kpis` agregados — candidatos defer)
- localStorage: ausente
- Tailwind tokens canon: **misto** — `bg-emerald-600`/`bg-blue-600`/`bg-amber-600`/`bg-zinc-300` hardcoded em `scoreColor()`; idem `bg-emerald-100`/etc em `hitlBadge()` — design-system review recomenda tokens semânticos
- useMemo/useCallback: 0
- Imports: lucide ausente (zero ícones — só Cards/Badge) + shared (PageHeader, KpiGrid, KpiCard, EmptyState)
- Helper `num()` Intl.NumberFormat pt-BR (bom)

**Risco prévio:**
- Charter ausente
- Cores hardcoded `bg-emerald-600 text-white` — quando dark mode entrar, contraste vai quebrar; recomenda `bg-success-strong` ou similar token
- `scores: Score[]` (não paginated) — em ADS maduro pode ter 200+ par (domain × event_type) → renderizar tabela inteira eager pode lentar
- KPIs `media_score` calculada server-side ok

**Smoke pendente:**
- screenshot 1440 + 1280
- ordenação por score? (sem header sortable visível)
- responsivo tabela 1280 — scroll horizontal?

**Decisão Wagner:** [pendente — recomendar tokens semânticos + paginated se >50 rows]
