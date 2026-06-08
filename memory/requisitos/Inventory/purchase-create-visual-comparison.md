---
tela: purchase/create
modulo: Purchase
tipo: FORM CREATE
generated_at: 2026-05-15
generated_by: Agent W2-D (Wave2 B5 Stock/Purchase MWART)
status: aguardando-screenshot-wagner
runbook: memory/requisitos/Inventory/RUNBOOK-purchase-create.md
draft_tsx: resources/js/Pages/Purchase/Create.tsx
controller_delta: app/Http/Controllers/PurchaseController.php@createInertia
cowork_source: prototipo-ui/prototipos/compras/visual-source.html
---

# Visual Comparison — `purchase/create` (FORM CREATE)

> Gate visual ADR 0114. Wagner aprova SCREENSHOT, não tabela.

## Como rodar smoke local

```bash
# 1. Build front
npm run build
# 2. Login user biz=1 em http://oimpresso.test/login
# 3. Acessar Blade legacy: http://oimpresso.test/purchases/create
# 4. Acessar Inertia novo: http://oimpresso.test/purchases/create?v=2
# 5. Comparar lado-a-lado
```

## 15 dimensões — visão executiva

| # | Dimensão | Blade legacy | Draft Inertia | Status |
|---|---|---|---|---|
| 1 | Hierarquia visual | H1 sem ícone, sections widget | PageHeader + 4 Cards | OK |
| 2 | Densidade campos | 18+ campos pull-request espalhados | 8 sempre visíveis + 4 cards focados | melhor |
| 3 | Filtros | n/a (FORM) | n/a | OK |
| 4 | Tabela itens | Sortable+inline JQuery | React state + inline edit | reduzido MVP |
| 5 | Ações | 4 botões (Save/Cancel/Print/Stock) | 2 botões (Cancelar/Salvar) | reduzido MVP |
| 6 | Status pill | (não no form) | select dropdown | OK |
| 7 | Tipografia | system 14px | system 13px | mais compacto |
| 8 | Cores | bootstrap blue | stone-50 + rose-700 | aderente |
| 9 | Espaçamento | row gutter 20px | gap-3 grid | OK |
| 10 | Interatividade | jQuery select2 + datepicker | shadcn Input HTML5 | simplificado |
| 11 | Multi-tenant Tier 0 | ✅ business_id sessão | ✅ business_id via Controller param | OK |
| 12 | Performance | JS pesado (~120kb select2+datepicker) | useForm Inertia | melhor |
| 13 | a11y | bootstrap default | shadcn defaults | OK |
| 14 | Responsividade | row-col bootstrap | grid md/lg | OK |
| 15 | i18n | __() lang keys | PT-BR hardcoded MVP | 🟡 v0.2 |

## Limitações MVP1 (conscientes)

1. **Autocomplete fornecedor reduzido** — Input texto + ID (não busca). v0.2: ContactSearchAutocomplete reutilizar do Sells.
2. **Autocomplete produto reduzido** — Input texto manual. v0.2: ProductSearchAutocomplete.
3. **Sem pagamento embutido** — pagamento V2 (drawer/modal). Foco entrada estoque primeiro.
4. **Sem custom_field_1..4** — v0.2 dentro de `<details>` "Mais opções".
5. **Sem upload document** — v0.2.

## Decisão Wagner — aprova screenshot?

- [ ] Renderiza sem erro com biz=1
- [ ] Permite criar compra mínima (filial + fornecedor_id + 1 item + total)
- [ ] POST `/purchases` aceita FormData e cria `transactions.type='purchase'`
- [ ] Tier 0 preservado (locations só da business)
