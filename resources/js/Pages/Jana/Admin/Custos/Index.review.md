---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Admin/Custos/Index
file: resources/js/Pages/Jana/Admin/Custos/Index.tsx
charter_present: false
charter_file: null
runbook_present: true
runbook_file: memory/requisitos/Jana/RUNBOOK-custos-admin.md
append_only: true
---

# Review estática — `Jana/Admin/Custos/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+KpiGrid+KpiCard shared ✓
- US-COPI-070 + permission `copiloto.admin.custos.view`
- Preset filters: `mes_atual|mes_anterior|90d|custom` + `router.get`
- Props: KPIs (custo_brl, mensagens, tokens, usuarios_ativos) + `UsuarioRow[]` + `DiaRow[]` + Periodo
- Sem `Deferred`, sem `localStorage`
- Tela cara: tabela usuários + série temporal

## Riscos Tier 0

1. **CHARTER/M2 — AUSENTE**: tela com ADRs (arq/0003, 0029) e US definida mas sem charter `.md` ao lado. Hook deveria ter bloqueado.
2. **PERF/M2 — Tabelas EAGER**: `usuarios[]`+`dias[]` agregação custosa — Inertia::defer candidate.
3. **CUSTO IA/L2 — Tela mostra custos**: NÃO chama LLM (read-only de tabela `mcp_audit_log`), mas validar query é cacheada.
4. **MULTI-TENANT/L3 — Custos por business**: confirmar query respeita `business_id` (ou superadmin scope explícito).
5. **PII/L3 — `UsuarioRow.nome` exposto**: permission gate.

## Top 5 recomendações

1. P0 — Criar `Jana/Admin/Custos/Index.charter.md`.
2. P0 — Deferir `usuarios`+`dias` no controller (agregação custosa).
3. P1 — Cache controller (5min) query agregada.
4. P2 — Multi-tenant gate Pest: biz=4 vê só biz=4 (ou superadmin = todos com `withoutGlobalScopes` documentado).
5. P3 — `localStorage` preset preferido (`oimpresso.jana.custos.preset`).
