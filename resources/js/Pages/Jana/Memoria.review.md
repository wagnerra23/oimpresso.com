---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Memoria
file: resources/js/Pages/Jana/Memoria.tsx
charter_present: true
charter_file: Memoria.charter.md
runbook_present: true
runbook_file: memory/requisitos/Jana/RUNBOOK-MEMORIA-SEMANAL.md
append_only: true
---

# Review estática — `Jana/Memoria.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · Card+Badge+FabJana ✓ · charter+RUNBOOK ✓
- Status: implementada
- US-COPI-MEM-005/008/012 + R-COPI-MEM-LGPD-001/MULTITENANT-001 + ADRs 0031/0033/0035/0036/0037
- **MemoriaFato** com `business_id`+`user_id` explícitos no payload — multi-tenant visível
- **CATEGORIA_LABELS** usa `bg-blue-100 text-blue-800` etc — **CORES CRUAS** (anti-pattern Cockpit V2)
- `useForm` (edit memoria) + `router` (delete)
- `valid_from`/`valid_until` — TTL/expiry pattern

## Riscos Tier 0

1. **CORES/M1 — `CATEGORIA_LABELS` usa cores Tailwind cruas** (`bg-blue-100`, `bg-red-100`, etc) — viola tokens semantic (anti-pattern explícito em charters Cockpit V2/Admin). Migrar para `text-info`, `text-destructive`, `text-warning`.
2. **LGPD/M1 — R-COPI-MEM-LGPD-001**: validar Edit/Delete respeita `business_id` + audit log (memoria contém PII Wagner-explicit).
3. **PII/M2 — `fato` é texto livre user-gerado**: pode conter CPF/dados sensíveis. PiiRedactor antes de display?
4. **PERF/L3 — `memorias[]` EAGER**: se biz tem 1000+ memórias, defer.
5. **CHARTER/L4 — Drift round 2** (memoria semanal evolui).

## Top 5 recomendações

1. P0 — Substituir cores cruas `CATEGORIA_LABELS` por tokens semantic (`text-info`, `text-destructive`, `text-warning`, etc).
2. P0 — Pest GUARD multi-tenant: usuário biz=4 NÃO vê memoria biz=1 (R-COPI-MEM-MULTITENANT-001).
3. P0 — PiiRedactor display `fato` (preview safe) + revelar full com audit.
4. P1 — Deferir `memorias` + paginar.
5. P2 — Charter drift round 2.
