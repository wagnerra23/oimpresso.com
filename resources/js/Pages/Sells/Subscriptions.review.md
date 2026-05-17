---
page: /sells/subscriptions
component: resources/js/Pages/Sells/Subscriptions.tsx
charter: resources/js/Pages/Sells/Subscriptions.charter.md
review_round: 1
reviewer: W31 (bulk static review)
review_date: 2026-05-17
charter_exists: true
loc: 307
tier: A
related_adrs: [0104, 0149, 0110, 0093]
---

# Static Review — /sells/subscriptions

## 1. Conformidade

Wave 1 W1-A MWART. Vendas recorrentes (`is_recurring=1`) com toggle start/stop inline. Integração futura `Modules/RecurringBilling` mencionada cabeçalho linha 4.

| Item | Estado |
|---|---|
| AppShellV2 | ✅ |
| KpiCard shared (3 KPIs: total/active/stopped) | ✅ linha 33 + import linha 13 |
| EmptyState shared | ✅ |
| Deferred customers | ✅ provavelmente (charter padrão) |
| Toggle inline start/stop | ✅ state `togglingId` linha 65 + `Pause`/`Play` icons linha 10 |
| `recur_interval` formatação | ✅ helper `intervalLabel()` linha 50-58 (singular/plural PT-BR) |

## 2. Helper i18n PT-BR

- ✅ `intervalLabel`: `'dia'/'dias', 'mês'/'meses', 'ano'/'anos'` — singular/plural correto PT-BR (linha 51-55)
- ⚠️ Type fallback `['?', '?']` se `recur_interval_type` desconhecido — silent failure; melhor lançar console.warn

## 3. Tipagem TS

- ✅ `SubscriptionRow`, `SellsSubscriptionsPageProps` exportada
- ⚠️ `recur_interval_type: 'days' | 'months' | 'years' | string` — mesma união poluída
- ⚠️ `upcoming_invoice: string | null` — formato data string sem timezone marker (`Date` ou ISO 8601?). Round 2 verificar `formatDateTime` consumer

## 4. Multi-tenant Tier 0

- ⚠️ Toggle endpoint `urls.toggle` — confirma backend aplica `business_id` scope ao buscar subscription antes de UPDATE `recur_stopped_on`

## 5. Side-effect crítico: toggle stop/start

- ⚠️ **Risco P1**: toggle stop **NÃO cancela próximas faturas já agendadas** no `recurring_invoice_payments` table (se houver) — UX ambígua. Charter precisa documentar. Round 2 confirmar comportamento backend.
- ⚠️ Auditoria: toggle gera entrada no `activity_log` (Spatie ActivityLog)? Round 2 verificar.

## 6. Anti-padrões

- ✅ Sem sessionStorage
- ✅ Sem cor crua (icons lucide + tokens shadcn)
- ✅ Botões `Pause`/`Play` semânticos

## 7. Top riscos

1. **Toggle stop semantics ambíguas** — invoices futuras geradas pelo `subscriptions:generate` cron seguem ou param? Documentar charter
2. **Auditoria toggle** — sem activity_log = mudança fantasma
3. **`recur_interval_type` union poluída** com `string` — mesmo padrão recorrente do batch
4. **`urls.toggle` confiança backend** — Pest gateway: user biz=99 não consegue togglar subscription biz=1
5. **Cron `subscriptions:generate`** integration desconhecida daqui — RecurringBilling module canonical?

## 8. Próximos passos round 2

- Pest: cross-tenant toggle bloqueado
- Verificar `activity_log` entry gerado
- Confirmar cron `subscriptions:generate` interação com `recur_stopped_on`
- Extract `Types/Sale.ts` (recorrente entre 7 telas Sells)

---

**Append-only.**
