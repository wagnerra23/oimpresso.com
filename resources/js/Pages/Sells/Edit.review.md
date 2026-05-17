---
page: /sells/{id}/edit
component: resources/js/Pages/Sells/Edit.tsx
charter: resources/js/Pages/Sells/Edit.charter.md
review_round: 1
reviewer: W31 (bulk static review)
review_date: 2026-05-17
charter_exists: true
loc: 396
tier: A
related_adrs: [0104, 0149, 0143, 0093]
---

# Static Review — /sells/{id}/edit

## 1. Conformidade

Wave 1 W1-A MWART. **Guard FSM crítico declarado** comentário linha 3: *"NUNCA toca current_stage_id"* (ADR 0143 IRREVOGÁVEL — trait `GuardsFsmTransitions` bloqueia UPDATE direto).

| Item | Estado |
|---|---|
| Deferred form pesado | ✅ linha 187 `<Deferred data="form" fallback={<FormSkeleton />}>` |
| FormSkeleton custom | ✅ linha 76-85 — skeleton específico p form layout |
| useForm Inertia | ✅ linha 93 (chamado no top-level — regra hooks React, declarado linha 90-91) |
| Headline interface | ✅ linha 17-23 (id, invoice_no, type, status, current_stage_key readonly) |
| FSM safety `current_stage_key` | ✅ exposto **read-only** na headline (linha 22) — não está no form data |
| useForm `put` (PUT method) | ✅ linha 93 (`put` desestruturado) |

## 2. Inertia::defer audit (excelente)

- ✅ **D-14 pattern correto:** Controller envia `headline` eager (rápido) + `form` deferred (pesado). Frontend usa `<Deferred data="form" fallback={<FormSkeleton />}>` linha 187 com **skeleton específico** (não `null`). Validar visualmente.
- ✅ Comentário linha 90-91 explica regra hooks (useForm sempre top-level, re-popula via useEffect) — disciplina React Hooks correta

## 3. Multi-tenant Tier 0 (ADR 0093)

- ⚠️ Não enxergamos do frontend o `business_id` scope — assumir Controller `Sell::find($id)` aciona global scope. Round 2: confirmar Pest cross-tenant biz=1 vs biz=99 retorna 404 em vez de exposar dados.

## 4. FSM safety (ADR 0143)

- ✅ `current_stage_key` exposto na `headline` **readonly** — não está no `useForm` data
- ✅ Comment header linha 3 declara explicitamente "NUNCA toca current_stage_id"
- ✅ Edit deve usar `ExecuteStageActionService` pra mudanças de stage — não via PUT direto

## 5. Tipagem TS

- ✅ `EditFormPayload` exportada, separa transaction/sellDetails/permissions
- ⚠️ `redeemDetails: Record<string, unknown> | []` — union com `[]` é anti-pattern (deveria ser `unknown[]` ou tipo declarado)
- ⚠️ `commissionAgents: Record<number, string> | []` mesmo problema (recorrente — provavelmente backend serializa `[]` quando empty Collection)

## 6. PT-BR e comentários

- ✅ Cabeçalho PT-BR completo com refs ADR 0104/0149/0143/0093
- ✅ Comentários inline PT-BR

## 7. Top riscos

1. **`Record<...> | []` union frágil** (backend serialization issue Laravel Collection empty → JSON `[]`) — refactor backend pra `(object){}` ou Frontend Type Guard
2. Confirmar **`put` Inertia** envia somente fields permitidos pelo Controller (guard backend é o que importa)
3. **FSM guard** assumido — round 2 Pest test cross-tenant + cross-stage
4. **Re-popular form via useEffect** quando deferred chega — risco race condition se user digitar antes do `form` carregar
5. **`statuses` Record<string, string>** — incluir 'draft'/'final' mas NÃO 'cancelled' (canon FSM, não edit)

## 8. Próximos passos round 2

- Pest cross-tenant: GET `/sells/1/edit` com user biz=99 → 404 (não 200)
- Pest FSM: PUT `/sells/1` com `current_stage_id` payload → trait `GuardsFsmTransitions` rejeita
- Confirmar Controller usa `Inertia::defer(fn() => $this->buildFormPayload($sale))`

---

**Append-only.**
