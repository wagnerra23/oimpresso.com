---
tela: stock_transfers/index
modulo: Inventory / StockTransfer
tipo: LIST
generated_at: 2026-05-15
generated_by: Agent W2-D
status: aguardando-screenshot-wagner
runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-index.md
draft_tsx: resources/js/Pages/StockTransfer/Index.tsx
controller_delta: app/Http/Controllers/StockTransferController.php@indexInertia
cowork_source: prototipo-ui/prototipos/inventario-migracao/visual-source.html
---

# Visual Comparison — `stock_transfers/index` (LIST)

## Smoke

```
Blade legacy: /stock-transfers
Inertia novo: /stock-transfers?v=2
```

## 15 dimensões — destaques

| Dimensão | Blade legacy | Draft Inertia | Status |
|---|---|---|---|
| Hierarquia | H1 sem ícone + box-primary | PageHeader + Card | melhor |
| Densidade | DataTables fixed 10 linhas | h-11 ~15 linhas | melhor |
| Filtros | DataTables search global | 4 filtros + busca | melhor |
| Status pills | label bg-{color} | shadcn pill semântico | aderente |
| Origem→Destino | duas colunas separadas | 1 coluna com ArrowRight | clean |
| Multi-tenant | ✅ business_id sessão | ✅ via Controller param | OK |
| Permissions view_purchase_price | ✅ esconde valor | ✅ "—" se sem permissão | OK |

## Limitações MVP1

1. Sem update inline de status (modal `update_status_modal` legacy não migrado). v0.2.
2. Sem paginação real — limit 200.
3. Sem ações Edit (status `final` bloqueia legacy — preservar regra).

## Decisão Wagner

- [ ] Renderiza com transferências reais biz=1
- [ ] Filtro location funciona
- [ ] Tier 0 preservado (origem e destino ambas da business)
