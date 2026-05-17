---
tela: ads/Admin/Learning
controller: ADS\Http\Controllers\Admin\LearningController (inferido)
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
- Inertia::defer: 0 props (`stages` + `throughput` série temporal + `kpis` — throughput cresce, candidato defer)
- localStorage: ausente
- Tailwind tokens canon: **misto** — `colorMap` hardcoded `bg-zinc-100`/`bg-blue-100`/etc; OK pra config map mas dark mode...
- useMemo/useCallback: 0
- Imports: lucide (ArrowDown, ArrowRight) + Icon custom + shared (PageHeader, KpiGrid, KpiCard)

**Risco prévio:**
- Charter ausente
- `throughput: ThroughputPoint[]` série temporal — quantos pontos? se hora-a-hora 24h ok, se minuto-a-minuto fica caro
- Sem chart lib visível (apenas array no source) — render como tabela ou bar custom?
- `colorMap` precisa atenção dark mode

**Smoke pendente:**
- screenshot 1440 + 1280
- janela 24h vs 7d (param `janela_horas`)
- responsivo throughput visual

**Decisão Wagner:** [pendente]
