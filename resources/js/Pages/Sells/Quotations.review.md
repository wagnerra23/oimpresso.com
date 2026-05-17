---
page: /sells/quotations
component: resources/js/Pages/Sells/Quotations.tsx
charter: resources/js/Pages/Sells/Quotations.charter.md
review_round: 1
reviewer: W31 (bulk static review)
review_date: 2026-05-17
charter_exists: true
loc: 242
tier: A
related_adrs: [0104, 0149, 0110, 0143, 0093]
---

# Static Review — /sells/quotations

## 1. Conformidade

Wave 1 W1-A MWART. Pattern espelhado do Drafts (filtro `?is_quotation=1`).

| Item | Estado |
|---|---|
| AppShellV2 | ✅ linha 242 |
| Head title "Cotações" | ✅ linha 120 |
| KpiCard shared | ✅ linha 13 |
| EmptyState shared | ✅ linha 14 |
| Deferred customers | ✅ linha 234 |
| Atalho `N` → `/sells/create?sub_status=quotation` | ✅ linha 97-100 |
| Atalho `Esc` voltar | ✅ linha 101-104 |
| Botão "Enviar" → print quotation | ✅ linha 219-224 |

## 2. Reuso vs Drafts (ADR 0149 — pattern reuse)

- ✅ **Esperado**: estrutura quase idêntica a Drafts (boa aplicação ADR 0149)
- ⚠️ **Code duplication**: `formatDateTime`, fetch DataTables, useEffect atalhos repetidos byte-a-byte entre Drafts/Quotations. Candidato extract → `_components/SellsSimpleList.tsx` ou hook `useSellsListFetch(url)`. **Pragmatismo MWART: aceitar duplicação na safra, refactor depois F4 estabilizar.**

## 3. Inertia::defer audit

- ✅ `<Deferred data="customers" fallback={null}>` linha 234 — mesmo pattern Drafts

## 4. Anti-padrões

- ✅ Sem `sessionStorage`
- ✅ Sem cor crua
- ✅ Sem Modal
- ⚠️ Mesmo `idx % 2 === 1` que Drafts (manter consistência, mas pode CSS-only)

## 5. Tipagem TS

- ✅ `QuoteRow`, `SellsQuotationsPageProps` exportada
- ⚠️ Mesmo issue Drafts: mapping JSON `r.name` → `customer_name`

## 6. Specifics quotation

- ✅ **"Enviar" via `target="_blank"`** linha 219-223 — abre print PDF nova aba. UX correto.
- ⚠️ **Falta** botão "Converter em venda" inline — quotation → final é fluxo crítico FSM ([ADR 0143] action `aprovar_quote`); por hora só botão "Editar" via `/sells/{id}/edit` (canal indireto)

## 7. Top riscos

1. **Code duplication Drafts/Quotations** — extract refactor pós-F4
2. **Falta CTA "converter em venda"** inline — fluxo FSM `quote_*` → `final` requer click extra
3. Mesma análise Drafts (fetch sem AbortController, DT_RowId acoplamento)
4. **Charter** confirma se "Enviar" deveria abrir modal de email em vez de print quotation (round 2)
5. **`permissions` prop não usada na render** (linha 50 `const { kpis, urls } = props;` — `permissions` recebido mas não consumido) → dead prop ou TODO?

## 8. Próximos passos round 2

- Confirmar fluxo FSM `aprovar_quote` está acessível UI (via `/sells/{id}/edit` → FsmActionPanel?)
- Pest cross-tenant
- Validar quotation print template existe e tem layout próprio

---

**Append-only.**
