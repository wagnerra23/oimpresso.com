---
tela: stock_adjustment/index
modulo: Inventory / StockAdjustment
tipo: LIST
generated_at: 2026-05-15
generated_by: Agent W2-D
status: aguardando-screenshot-wagner
runbook: memory/requisitos/Inventory/RUNBOOK-stock-adjustment-index.md
draft_tsx: resources/js/Pages/StockAdjustment/Index.tsx
controller_delta: app/Http/Controllers/StockAdjustmentController.php@indexInertia
cowork_source: prototipo-ui/prototipos/inventario-migracao/visual-source.html
---

# Visual Comparison — `stock_adjustment/index` (LIST)

## Smoke

```
Blade: /stock-adjustments
Inertia: /stock-adjustments?v=2
```

## Destaques

| Dimensão | Blade legacy | Draft Inertia | Status |
|---|---|---|---|
| Hierarquia | H1 + box-primary | PageHeader + Card | melhor |
| Densidade | DataTables 10 linhas | h-11 ~15 linhas | melhor |
| Filtro location | DataTables filter | select sticky | igual / melhor |
| Tipo pill (normal/abnormal) | label texto | shadcn pill semântico | melhor (abnormal = rose) |
| Recuperado destaque | texto plain | emerald-700 se > 0 | melhor UX |
| Multi-tenant | ✅ business_id | ✅ idem | OK |

## Limitações MVP1
1. Sem update inline (legacy view modal). v0.2.
2. Sem totais agregados footer.
