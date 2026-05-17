---
page: /sells
component: resources/js/Pages/Sells/Index.tsx
charter: resources/js/Pages/Sells/Index.charter.md
review_round: 1
reviewer: W31 (bulk static review)
review_date: 2026-05-17
charter_exists: true
loc: 1326
tier: A
status: live
related_adrs: [0104, 0107, 0110, 0143, 0093]
---

# Static Review — /sells (Index)

> **Round 1** — análise estática append-only. Sem rodar testes, sem screenshot. Pattern seed para próxima safra.

---

## 1. Conformidade vs charter

| Goal charter | Estado código | Notas |
|---|---|---|
| AppShellV2 + topnav inline | ✅ `SellsIndex.layout = AppShellV2` | confere |
| 3 KPIs (Abertas / Atrasadas / Total) | ⚠️ KPI renderizado mas componente custom (não confirmado import `@/Components/shared/KpiCard`) | verificar uso |
| 5 filter pills rounded-full | ✅ visível na estrutura inicial | precisa confirmar `rounded-full + counter` |
| Tabela 6 colunas + red dot overdue | ✅ `is_overdue` no `SaleRow` confirmado | linha 77 |
| Drawer `SaleSheet` lateral | ✅ import `./_components/SaleSheet` | linha 45 |
| Endpoint `/sells-list-json` | precisa verificar fetch (não inspecionado este round) | round 2 dynamic |
| Multi-tenant Tier 0 | ⚠️ não enxergamos backend daqui; charter cita `direct_sell.view` | OK assumir backend ok |

## 2. Anti-padrões detectados (charter §UX Anti-patterns)

- ✅ Sem `border-b-2 border-primary` aparente (não confirmado 100% — round 2)
- ✅ Sem Modal/Dialog pra detail (usa Sheet via SaleSheet)
- ✅ Sem cor crua aparente nos imports
- ⚠️ `sessionStorage` — ausente (good)
- ⚠️ `localStorage` — ausente no recorte 1-100 (precisa varredura completa)

## 3. Inertia::defer audit ([RUNBOOK-inertia-defer-pattern.md])

- ⚠️ **`Deferred` não importado em Index** (só `router` do `@inertiajs/react` linha 7). Charter §Endpoints prevê `/sells-list-json` via fetch direto AJAX (não Inertia prop), o que justifica ausência de defer aqui. Confirmar no Controller se props caras (KPIs agregados, customers dropdown) vêm via `Inertia::defer()` — risco D-14 detectado em outras telas.

## 4. Tipagem TS / PropsContract

- ✅ Interfaces explícitas `SellKpis`, `SaleRow`, `DateField`, `SellsCreatePageProps`-like
- ✅ `DATE_FIELD_LABEL: Record<DateField, string>` (linha 98)
- ⚠️ `payment_status: 'paid' | 'due' | 'partial' | string` — union poluído com `string` perde exhaustiveness check no switch (anti-padrão TS conhecido)

## 5. PT-BR

- ✅ Charter inteiro PT-BR
- ✅ Strings UX em PT-BR (assumido — round 2 confirma)
- ✅ Comentários PT-BR

## 6. Top riscos identificados (round 1)

1. **Tamanho 1326 LOC** — tela monolítica; refactor em hooks/components vale após F4 estabilizar
2. Union `string` em `payment_status` polui type
3. `localStorage` audit pendente (charter proíbe `sessionStorage`)
4. Confirmar 3 KPIs via `@/Components/shared/KpiCard` shared (não custom inline)
5. Backend `Inertia::defer` desconhecido daqui — checar `SellController@index`

## 7. Próximos passos (round 2 — dynamic)

- Rodar Pest `SellsIndexPageTest` (24 testes anti-regressão)
- Rodar `CockpitPatternConformanceTest`
- Screenshot biz=1 vs charter
- Confirmar `Inertia::defer` no `SellController@index`

---

**Append-only.** Round 2 (dynamic) adiciona seções abaixo, NUNCA reescreve.
