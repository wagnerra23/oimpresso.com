---
page: /sells/{id}
component: resources/js/Pages/Sells/Show.tsx
charter: resources/js/Pages/Sells/Show.charter.md
review_round: 1
reviewer: W31 (bulk static review)
review_date: 2026-05-17
charter_exists: true
loc: 465
tier: A
related_adrs: [0104, 0149, 0107, 0143, 0093]
---

# Static Review — /sells/{id} (Show)

## 1. Conformidade

Wave 1 W1-A MWART. Layout 2 cols (8/4) — esquerda detalhes + direita FSM action panel + timeline.

| Item | Estado |
|---|---|
| AppShellV2 | ✅ (linha 244+ provavelmente) |
| Head title | ✅ provavelmente PT-BR |
| KpiCard shared | ✅ linha 23 |
| EmptyState shared | ✅ linha 24 |
| Deferred detail | ✅ comentário linha 7 declara: *"Detail vem DEFERRED (Inertia::defer no controller — RUNBOOK-inertia-defer-pattern)"* |
| FSM action panel direita | ⚠️ inferido charter — confirmar import `FsmActionPanel` round 2 |
| Timeline atividades | ✅ interface `Activity` linha 67-71 |

## 2. Inertia::defer audit (correto e auto-documentado)

- ✅ **Excelente**: comentário cabeçalho cita explicitamente o pattern RUNBOOK-inertia-defer
- ✅ Pattern D-14: headline eager (~50ms), detail (`lines`, `payments`, `taxes`, `activities`, `shipping`) deferred
- ⚠️ Round 2 confirmar `<Deferred data="detail" fallback={...}>` com fallback útil (skeleton, não null)

## 3. Multi-tenant Tier 0

- ⚠️ Show é o endpoint mais sensível pra vazamento cross-tenant (URL `/sells/{id}` adivinhável). Pest `Sell::find($id)` deve aplicar global scope → 404 cross-biz, NÃO retornar dados.

## 4. FSM Pipeline (ADR 0143)

- ✅ Headline expõe `current_stage_key: string | null` (linha 42)
- ✅ Sub-painel direita prepara `FsmActionPanel` (charter + comentário) — ações dinâmicas por stage
- Round 2: verificar timeline `sale_stage_history` audit append-only

## 5. Tipagem TS

- ✅ Interfaces ricas: `Customer`, `Headline`, `SaleLine`, `SalePayment`, `Activity`, `ShowDetail`
- ⚠️ `payment_status: 'paid' | 'due' | 'partial' | string` (linha 40) — mesma union poluída que Index (cross-tela inconsistency? deveriam compartilhar tipo `@/Types/SaleStatus`)
- ⚠️ `status: 'final' | 'draft' | 'quotation' | 'proforma' | string` (linha 41) — idem

## 6. PT-BR

- ✅ Cabeçalho PT-BR completo

## 7. Top riscos

1. **Tipos `payment_status`/`status` duplicados** entre Index/Show/Edit — extract `resources/js/Types/Sale.ts` shared
2. **Cross-tenant adivinhação** `/sells/{id}` — Pest crítico (mais sensível Sells)
3. **`Inertia::defer` no Controller** — assumido pelo comment, confirmar
4. **FsmActionPanel** charter cita — confirmar render condicional se `current_stage_key !== null` (legacy sem FSM)
5. **Timeline activities** vem deferred — fallback render ordem cronológica?

## 8. Próximos passos round 2

- Pest cross-tenant GET `/sells/1` user biz=99 → 404
- Pest FSM Show: render `FsmActionPanel` se stage_key não-null
- Extract `Types/Sale.ts` shared
- Screenshot 2-col 8/4 conformidade Cockpit V2

---

**Append-only.**
