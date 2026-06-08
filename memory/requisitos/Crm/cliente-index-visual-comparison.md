# Visual Comparison — Cliente/Index (W1-B3)

## Blueprint Cowork
`prototipo-ui/prototipos/clientes/cowork-app.jsx`

## Approval status (ADR 0114)
- ✅ Pattern reuse aprovado via ADR 0149 (Index = canon do blueprint)
- ⏳ Screenshot Wagner pendente (Wave 1 batch sync após canary biz=1)

## Telas derivadas que herdam aprovação
- Cliente/Index (esta — canon)
- Cliente/Create, Cliente/Show, Cliente/Edit (mesma família visual)

## Telas com divergência declarada
- Cliente/Import (wizard upload)
- Cliente/Ledger (tabela financeira)
- Cliente/Map (split-pane)

## 15 dimensões mwart-comparative V4

| Dimensão | Cowork | Inertia | Status |
|---|---|---|---|
| 1. Hierarchy header | h1 22-24px font-semibold | ✅ aplicado | OK |
| 2. KPI cards | 4 cards rounded-xl border | ✅ aplicado | OK |
| 3. Filter pills | rounded-full canon | ✅ aplicado | OK |
| 4. Table density | px-4 py-3 | ✅ aplicado | OK |
| 5. Status badge | rounded-full border 11px | ✅ aplicado | OK |
| 6. Avatar | rounded-md monocromático | ✅ aplicado | OK |
| 7. Drawer 480px right | Sheet UI canon | ✅ aplicado | OK |
| 8. Cores semânticas | rose/sky/stone/emerald | ✅ aplicado | OK |
| 9. Tabular nums | tabular-nums em dinheiro/data | ✅ aplicado | OK |
| 10. Empty state | mensagem PT-BR centrada | ✅ aplicado | OK |
| 11. Loading state | spinner + label | ✅ aplicado | OK |
| 12. Pagination | controls + per_page select | ✅ aplicado | OK |
| 13. Search debounce | 300ms | ✅ aplicado | OK |
| 14. localStorage prefix | oimpresso.cliente.* | ✅ aplicado | OK |
| 15. PII mask | tax_number_masked server | ✅ aplicado | OK |

## Acessibilidade (a11y)
- ARIA labels em filter pills + sort buttons + nav
- aria-sort em SortableTh
- aria-current em pill ativa
- aria-label em search input + actions menu

## Performance targets
- p95 first-paint < 1200ms (com 50 customers)
- KPIs defer < 800ms
- Customers paginate defer < 1500ms

## Gate F1.5 (Wagner aprova screenshot)
- ⏳ Pendente — Wagner valida após canary biz=1 mostrar render correto em prod
