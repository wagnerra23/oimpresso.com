---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Admin/Governanca/Index
file: resources/js/Pages/Jana/Admin/Governanca/Index.tsx
charter_present: true
charter_file: Index.charter.md
runbook_present: true
runbook_file: memory/requisitos/Jana/RUNBOOK-governanca-mcp.md
append_only: true
---

# Review estática — `Jana/Admin/Governanca/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · charter+RUNBOOK ✓
- ADR 0053 (MCP server) + ADR 0039 (Cockpit) + permission `copiloto.mcp.usage.all` (Wagner)
- `LS_PRESET_KEY = 'oimpresso.copiloto.governanca.preset'` + `LS_SECAO_KEY` — `localStorage` canon prefix ✓
- Secao: 'consumo'|'acesso'|'usuarios' tri-mode
- Preset 'hoje'|'ontem'|'7d'|'30d'|'mes_anterior'|'custom'
- Props ricas: kpis, PorStatus, Latency, TopTool, TopUser, DeniedPorCodigo, dias

## Riscos Tier 0

1. **PERF/M2 — Múltiplos arrays EAGER**: topTools+topUsers+deniedPorCodigo+dias+porStatus — defer obrigatório.
2. **MULTI-TENANT/L3 — Wagner-only (`copiloto.mcp.usage.all`)**: confirm middleware fail-secure 403.
3. **STORAGE/L4 — `LS_PRESET_KEY`/`LS_SECAO_KEY` ✓**: aderente à convenção `oimpresso.<modulo>.<feature>.*`.
4. **LATENCY/L3 — `Latency.p99`/`max`**: monitor SLO interno — log se p99>threshold (alerta `mcp_usage_p99_breached`).
5. **CHARTER/L4 — Drift round 2**.

## Top 5 recomendações

1. P0 — Deferir todas tabelas pesadas (topTools/topUsers/dias/etc).
2. P1 — Pest GUARD: non-Wagner 403 (IsWagner middleware).
3. P1 — Alerta p99 latency breach via cron.
4. P2 — Charter drift round 2.
5. P3 — Export CSV ações para auditoria (já existe? validar).
