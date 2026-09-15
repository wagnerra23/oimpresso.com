---
id: requisitos-estoque-telas-stock-adjustment-create-visual-comparison
tela: stock_adjustment/create
modulo: Inventory / StockAdjustment
tipo: FORM CREATE
generated_at: 2026-05-15
generated_by: Agent W2-D
status: aguardando-screenshot-wagner
runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-create.md
draft_tsx: resources/js/Pages/StockAdjustment/Create.tsx
controller_delta: app/Http/Controllers/StockAdjustmentController.php@createInertia
# corrigido em 2026-09-09: apontava prototipo-ui/cowork/Wagner/legado/inventario-migracao/,
# que e o relatorio "Migracao Blade -> React" (medido: Blade=67 React=36 · estoque=0 SKU=0
# saldo=0 ajuste=0), nao o desenho desta tela -- casou por homonimo. Alinhado ao main:
# o PR #7079 fez a mesma correcao nos 4 charters. Regiao que desenha esta tela: FormAjuste (estoque-forms.jsx:157).
cowork_source: prototipo-ui/cowork/Wagner/estoque-forms.jsx
---

# Visual Comparison — `stock_adjustment/create` (FORM CREATE)

## Smoke

```
Blade: /stock-adjustments/create
Inertia: /stock-adjustments/create?v=2
```

## Destaques

| Dimensão | Blade legacy | Draft Inertia | Status |
|---|---|---|---|
| Layout | row-col bootstrap | Cards + grid responsivo | melhor |
| Tipo destaque | select plain | select com bg-rose se abnormal | melhor |
| Recuperado vs Total | só visual JS | useMemo + AlertCircle se exceder | melhor UX |
| Perda líquida | n/a (não calculado) | calculado e destacado rose | novo |
| Multi-tenant | ✅ business_id | ✅ idem | OK |

## Limitações MVP1

1. Busca produto = input texto (sem autocomplete API).
2. Sem lot_no picker — `lot_no_line_id` null por enquanto.
3. Sem batch tracking (Fase Inventory ROADMAP F2).

## Decisão Wagner

- [ ] Renderiza biz=1
- [ ] Recuperado > total bloqueia submit
- [ ] POST cria transaction stock_adjustment + decrementa estoque
- [ ] Tier 0 preservado
