---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Admin/Qualidade/Index
file: resources/js/Pages/Jana/Admin/Qualidade/Index.tsx
charter_present: false
charter_file: null
runbook_present: true
runbook_file: memory/requisitos/Jana/RUNBOOK-qualidade-admin.md
append_only: true
---

# Review estática — `Jana/Admin/Qualidade/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+KpiGrid+KpiCard+ScrollArea ✓
- MEM-MET-4 (ADR 0050) + permission `copiloto.mcp.usage.all`
- V1: KPIs por business + gates verde/vermelho + tabela trend
- **8 métricas obrigatórias + 3 RAGAS** (recall@3, precision@3, mrr, latencia p95, tokens, bloat, contradicoes, cross_tenant + faithfulness/answer_relevancy/context_precision)
- Sparklines SVG inline (sem chart libs) ✓
- `cross_tenant_violations: number` (não-null) — TIER 0 must-watch métrica
- `useMemo` ✓

## Riscos Tier 0

1. **CHARTER/M2 — AUSENTE**: criar (tela complexa com 11 métricas).
2. **TIER 0/M1 — `cross_tenant_violations` é alarm bell**: se > 0, banner persistente vermelho (não-dismissable) — ADR 0093 violação grave. Validar render mostra alerta UI.
3. **PERF/M2 — `Serie[]` + `Kpi[]` payload grande**: deferir.
4. **STORAGE/L4 — Sem `localStorage` filter pref**: opcional.
5. **A11Y/L3 — Sparkline SVG sem `<title>`/`aria-label`**: screen reader não lê.

## Top 5 recomendações

1. P0 — Criar `Jana/Admin/Qualidade/Index.charter.md`.
2. P0 — Banner vermelho persistente se `cross_tenant_violations > 0` (Tier 0 sinal).
3. P1 — Deferir `series`+`kpis`.
4. P2 — A11Y: `<title>` em sparkline `<svg>`.
5. P3 — Export CSV pra auditoria histórica.
