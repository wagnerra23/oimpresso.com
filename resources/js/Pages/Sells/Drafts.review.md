---
page: /sells/drafts
component: resources/js/Pages/Sells/Drafts.tsx
charter: resources/js/Pages/Sells/Drafts.charter.md
review_round: 1
reviewer: W31 (bulk static review)
review_date: 2026-05-17
charter_exists: true
loc: 244
tier: A
related_adrs: [0104, 0149, 0110, 0093]
---

# Static Review — /sells/drafts (Rascunhos)

## 1. Conformidade

Wave 1 W1-A MWART. Pattern reuso da Sells/Index (ADR 0149 citada).

| Item | Estado |
|---|---|
| AppShellV2 layout | ✅ linha 244 |
| Head title PT-BR | ✅ "Rascunhos de venda" linha 123 |
| KpiCard shared | ✅ linha 13 |
| EmptyState shared | ✅ linha 14 |
| Deferred customers | ✅ linha 235 (pre-load filter dropdown) |
| Atalho `N` (nova venda) | ✅ linha 100-103 |
| Atalho `Esc` (voltar) | ✅ linha 104-107 |
| Botão "Continuar" → `/sells/{id}/edit` | ✅ linha 220-224 |

## 2. Inertia::defer audit

- ✅ **Uso correto:** `<Deferred data="customers" fallback={null}>` linha 235 — preload assíncrono customers dropdown grande (canon §RUNBOOK-inertia-defer-pattern)
- ⚠️ Fallback `null` (não skeleton) — aceitável pra dropdown não visível ainda; aceitar como exceção

## 3. Anti-padrões

- ✅ Sem `sessionStorage`
- ✅ Sem cor crua (`text-muted-foreground`, `bg-muted/20`, `bg-card`, `border-border` — tokens Tailwind shadcn semânticos)
- ✅ Sem Modal pra detail (linka `/sells/{id}/edit` legacy edit — aceitável MWART transição)
- ⚠️ Tabela linha alternada manual `idx % 2 === 1 ? 'bg-muted/20' : ''` — pattern aceitável mas considerar `tbody [&_tr:nth-child(even)]:bg-muted/20`

## 4. Tipagem TS

- ✅ `DraftRow`, `SellsDraftsPageProps` exportada
- ✅ Mapping fetch JSON → DraftRow com `String()` / `Number()` guards
- ⚠️ Linha 73 `r.name ? String(r.name) : null` — funciona, mas o JSON do DataTables legacy fornece `name` em vez de `customer_name`. Mapping diferente do contract `customer_name` em DraftRow é intencional mas frágil — comment inline ajudaria

## 5. PT-BR

- ✅ Tudo PT-BR (Header, EmptyState, Tabela, ações)

## 6. Top riscos

1. **Fetch direto via `fetch()` no useEffect** (linha 56-89) — não usa SWR/React Query; loading manual; sem retry/cancel cleanup em unmount
2. **DataTables legacy contract** (`json.data`, `DT_RowId`) — acoplamento legacy frágil, mas pragmático na MWART
3. **Sem paginação** — limite implícito do DataTables (round 2 verificar)
4. **EmptyState** sem botão "Voltar pra lista" — só "Nova venda" (aceitável)
5. **Charter** existe mas precisa validar status `live` (round 2)

## 7. Próximos passos round 2

- Rodar Pest `SellsDraftsPageTest` se existir
- Cleanup fetch em unmount (`AbortController`)
- Confirmar `Inertia::defer` no Controller
- Confirmar charter §UX targets monitor 1280px

---

**Append-only.**
