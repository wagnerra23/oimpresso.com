---
page: /sales-order
component: (F1) venda-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-22"
parent_module: Sells
related_prototype: prototipo-ui/cowork/venda-menu/
---

# Charter — Pedido de venda

**Fonte legado:** `sales_order/index + edit_status_modal` · **Permissão:** `so.view_own · so.view_all · so.create (item condicional enable_sales_order)`
**Frescor:** 🟠 catch-up real — medido no `main` em 2026-08-22 (tree `6a8e45998ee5`): nenhum `Inertia::render` no controller, a tela ainda é Blade.

## Missão
Pedido que ainda não virou venda: acompanha status e quantidade restante a atender.

## Regras
- R1 O item só existe no menu com `enable_sales_order` ligado nas configurações do POS.
- R2 Status do pedido: pedido → parcial → concluído; muda por modal próprio.
- R3 "Quantidade restante" é o que falta faturar do pedido.
- R4 Gerar venda a partir do pedido não apaga o pedido.
