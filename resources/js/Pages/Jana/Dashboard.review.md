---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Dashboard
file: resources/js/Pages/Jana/Dashboard.tsx
charter_present: true
charter_file: Dashboard.charter.md
runbook_present: true
runbook_file: memory/requisitos/Jana/RUNBOOK-dashboard.md
append_only: true
---

# Review estática — `Jana/Dashboard.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader/Card/Badge + FabJana ✓ · charter+RUNBOOK ✓
- Status: implementada
- US-COPI-010/011/012 + R-COPI-002/FAROL-001 + ADRs 0026/0031/0035/0036
- **Métricas Jana com farol verde/amarelo/vermelho/cinza** (função `calcularFarol` local)
- `Meta` payload com `periodo_atual`, `ultima_apuracao`, `apuracoes_recentes[]`
- `FabJana` floating action button — coerente shared

## Riscos Tier 0

1. **PERF/M3 — `metas[]` EAGER**: se biz tem 20+ metas, payload cresce — defer.
2. **FAROL/L3 — `calcularFarol` lógica client-side**: dever ser server-side (single source of truth + cache). Refatorar se possível.
3. **FAB/L4 — `FabJana` z-50 fixed**: pode sobrepor modal-stacks; validar z-index hierarchy global.
4. **MULTI-TENANT/L3 — `metas` por business_id**: validar global scope.
5. **CHARTER/L4 — Drift round 2** (Dashboard evoluiu desde sprint inicial).

## Top 5 recomendações

1. P1 — Deferir `metas` no controller se >10 itens.
2. P1 — Mover `calcularFarol` pra backend (Service) — consistência com cron + UI.
3. P2 — FabJana z-index doc canon (z-50 reserved? colidir com Sheet/Dialog?).
4. P2 — Pest GUARD multi-tenant biz=4 vê só biz=4.
5. P3 — Charter drift round 2.
