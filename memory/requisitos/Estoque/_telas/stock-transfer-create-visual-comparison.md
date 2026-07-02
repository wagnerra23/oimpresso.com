---
tela: stock_transfers/create
modulo: Inventory / StockTransfer
tipo: FORM CREATE
generated_at: 2026-05-15
generated_by: Agent W2-D
status: aguardando-screenshot-wagner
runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-create.md
draft_tsx: resources/js/Pages/StockTransfer/Create.tsx
controller_delta: app/Http/Controllers/StockTransferController.php@createInertia
cowork_source: prototipo-ui/prototipos/inventario-migracao/visual-source.html
---

# Visual Comparison — `stock_transfers/create` (FORM CREATE)

## Smoke

```
Blade legacy: /stock-transfers/create
Inertia novo: /stock-transfers/create?v=2
```

## Destaques

| Dimensão | Blade legacy | Draft Inertia | Status |
|---|---|---|---|
| Origem→Destino | 2 selects soltos col-6 | Bloco destacado + validação inline R-XFER-004 | melhor |
| Validação origem≠destino | só server-side | client (button disabled) + server | melhor UX |
| Itens repeater | jQuery + manual sum | React useMemo subtotal | igual / melhor |
| view_purchase_price | esconde via CSS class | esconde via condicional JSX | melhor |
| Multi-tenant | ✅ business_locations já filtrado | ✅ idem (param do create()) | OK |

## Limitações MVP1

1. Busca produto = input texto (sem autocomplete API real).
2. Sem lot_number / batch tracking (Fase Inventory ROADMAP).
3. Sem "mark in transit / completed" toggle (status muda via select).

## Decisão Wagner

- [ ] Renderiza biz=1
- [ ] Origem≠Destino bloqueia submit visualmente
- [ ] POST cria 2 transactions (origem sell_transfer + destino purchase) sem erro
- [ ] Tier 0 preservado
