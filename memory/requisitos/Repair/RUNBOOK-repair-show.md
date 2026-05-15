# RUNBOOK MWART — Repair/Show

> **Tela:** `/repair/repair/{id}` · **Componente:** `resources/js/Pages/Repair/Show.tsx`
> **Wave:** W3-B6 Repair · **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/repair/show.blade.php` |
| Inertia branch | `RepairController::show($id)` linha ~862 (NOVO) |
| Flag | `MWART_REPAIR_SHOW` |

## F1 PLAN

1. **Pattern reuse**: blueprint `prototipo-ui/prototipos/os/cowork-app.jsx` (detalhe OS).
2. Diferente de `JobSheet/Show`: Repair/Show é a **VENDA-de-reparo** (Transaction com `sub_type='repair'`) — mostra invoice/sell-lines/payments/warranty.
3. Sections: Header (invoice_no, status, valor) · Cliente · Aparelho · Sell lines (peças/serviços faturados) · Pagamentos · Warranty · Activities.
4. **Sem FSM em Repair**: Transaction sub_type='repair' usa estado-da-venda via FSM Sells (Sprint 4 já LIVE). Mostra `<FsmActionPanel saleId={id}>` shared componente quando flag `repair_fsm_panel_in_show=true`.

## F2 BASELINE

Pest `Wave3B6RepairShowBaselineTest.php`:
- Flag OFF → Blade preservado
- biz=99 → 404

## F3 CODE

Controller:
```php
if ($this->mwartEnabled('repair_show', (int) $business_id)) {
    return Inertia::render('Repair/Show', [
        'sell' => $this->buildRepairSellPayload($sell),
        'payment_types' => $payment_types,
        'order_taxes' => $order_taxes,
        'activities' => Inertia::defer(fn () => $this->buildRepairActivitiesPayload($sell)),
        'warranty_expires_in' => $warranty_expires_in,
        'is_warranty_enabled' => $is_warranty_enabled,
        'checklists' => $checklists,
        'fsm' => [
            'enabled' => (bool) config('mwart.repair_show_fsm_panel.enabled'),
            'sale_id' => (int) $sell->id,
        ],
    ]);
}
```

UI:
- Layout 2 cols: detalhe (esq) + sidebar pagamentos/FSM (dir)
- Sell lines table
- `<FsmActionPanel saleId={...} enabled={fsm.enabled} />` quando ativo

## F4 QA

Pest `Wave3B6RepairShowInertiaTest.php`:
- Flag ON → Inertia componente
- biz=99 → 404
- Activities defer

## F5 CUTOVER

Canary biz=1.

## Riscos

- **R1 (BAIXO)** — Sell-lines podem ser grandes — `Inertia::defer` mitiga.
- **R2 (BAIXO)** — Activities log heavy — defer.
