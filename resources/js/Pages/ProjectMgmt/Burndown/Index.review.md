# Review Round 1 — ProjectMgmt/Burndown/Index.tsx

**Tela:** `/project-mgmt/burndown` · **Stories:** US-TR-206 · **Charter:** ❌ ausente
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática

## Resumo

Burndown chart SVG inline (sem dep recharts/chart.js) — linhas ideal (tracejada) vs real (azul sólida) + 5 KPIs (total/done/restantes/pace/forecast). Empty state pra cycle sem dados (`series.length < 2`). Cycle picker no header.

## Pontos fortes

- **SVG puro** = zero dep externa, render rápido, A11y `role="img" aria-label="Burndown chart"` ✅
- `useMemo` em `max` (evita recompute em re-render)
- Forecast com tone dinâmico (`success` se cabe nos dias restantes, `danger` se excede) — comunicação clara
- Empty state explícito (cycle ausente + cycle curto demais)
- KPI `description` no card de Previsão (contexto extra)

## Riscos / gaps (top 5)

1. **R1 — Charter ausente** ([ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)). Bloqueador MWART F3.
2. **R2 — Sem tooltip hover** nos pontos `<circle>` da série real. UX gap — usuário não vê "qual data, qual valor" sem inspecionar SVG. Pattern: `<title>` SVG nativo (simples) ou state-driven popover (sofisticado).
3. **R3 — Cores `stroke-blue-500` / `stroke-muted-foreground/60` hardcoded** — fora do design token. `BoardColumn`/`badges` usa Tailwind tokens consistentes (e.g. `text-primary`). Audit visual: skill `design-system` deveria sinalizar.
4. **R4 — `xLabels` step calculado** `Math.ceil(series.length / 8)` pode dar 0 labels intermediários em cycles muito curtos (e.g. 14d → step=2 → 8 labels OK; mas se 5d → step=1 → 5 labels OK). Edge ok mas validar visualmente com 90d.
5. **R5 — `useMemo(max)`** mas `xAt`/`yAt` não memoizados — recalculados a cada render. Em cycles 90d com series.length=90 pode pesar. Não crítico mas pattern.

## Veredito round 1

Chart minimalista e funcional. SVG-only é virtude pro stack (Tailwind 4). **Pendências:** charter (R1), tooltip hover (R2), cor tokens (R3).

**Status:** APROVA com pendências P3 (polish + bloqueador formal MWART).
