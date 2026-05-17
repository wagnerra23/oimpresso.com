# Review Round 1 — Auditoria/Index.tsx

**Tela:** `/auditoria` · **ADRs:** 0079 Art.9, 0127 · **Charter:** ✅ existe (W23 — `Index.charter.md`)
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática

## Resumo

Lista de `activity_log` (spatie/laravel-activitylog) — tabela 6 colunas (Quando/Log/Descrição/Entidade/Por/Ações). Aceita `activities` como array OU Paginator (defensivo `normalizeActivities`). Color-coded log_name por prefixo (`sales`/`financeiro`/`whatsapp`/`crm`/`manufacturing`). Link `/auditoria/{id}` pro Detail.

## Pontos fortes

- **Charter existe (W23)** — única da família Auditoria com gate F3 cumprido na round prévia
- Defensivo: aceita array OU Paginator (`normalizeActivities`)
- Color-coding por prefixo é visualmente útil (skim rápido)
- EmptyState shared component (`@/Components/shared/EmptyState`) — SoC
- PageHeader `subtitle` cita Constituição Art. 9 (rastreabilidade)
- Footer cita ADR 0084 (trigger MySQL) + RevertService pendente (US-AUDIT-008)

## Riscos / gaps (top 5)

1. **R1 — `Paginator` aceito mas SEM controles de paginação UI.** Mostra `total` no header mas usuário não tem next/prev. Em prod com 10k+ rows, vê só 1ª página. Gap funcional crítico.
2. **R2 — `filters` exibe só `period` no header** — outros filtros (`log_name`/`subject_type`/`causer_id`) não têm UI de input. Tipados mas sem `<Select>` ou `<Input>` pra mudar.
3. **R3 — `logNameColor` whitelist hardcoded** — `sales/financeiro/whatsapp/crm/manufacturing`. Novos módulos (Repair, RecurringBilling, Vestuario, ComVis, MemCofre, Project) caem todos em fallback `bg-zinc-100`. Drift visual com expansão modular ([ADR 0121](../../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md)).
4. **R4 — `subject_type.split('\\').pop()`** assume FQCN com `\` mas não testa null antes (cobre com `?` mas se string vazia retorna `''` muddy). Edge raro.
5. **R5 — Sem indicação visual de `business_id`** na tabela. Multi-tenant Tier 0 IRREVOGÁVEL — admin global precisa ver `business_id` pra debug cross-tenant. Hoje só aparece no Detail.

## Veredito round 1

Tela base funcional, charter cumprido. **Pendências:** paginação UI (R1, crítico), filtros input (R2), color expansion (R3).

**Status:** APROVA com pendências P1 (R1 bloqueia uso em prod com volume).
