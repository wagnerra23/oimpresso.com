---
id: requisitos-compras-purchase-order-parity
titulo: Paridade de migração — /purchase-order (Pedidos de compra) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /purchase-order
related:
  - ../../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-purchase-order-index.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Pedidos de compra (`/purchase-order`)

## Metadados

- **Tela React:** `resources/js/Pages/PurchaseOrder/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/purchase_order/index.blade.php` (DataTable + modais `view_modal`)
- **Controller:** `App\Http\Controllers\PurchaseOrderController::index` (lista); criar/editar/excluir/status/PDF seguem nos métodos de hoje
- **Rota nova / legada:** a mesma `Route::resource('purchase-order')`; a Blade segue com a flag desligada ou `?legacy=1`
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Só pedidos do negócio da sessão | F3 | `where transactions.business_id` | Tier 0 | `PedidosCompraBaselineTest` (98 × 99) |
| 2 | Só `type = purchase_order` | F3 | `where transactions.type` | alta | `PedidosCompraBaselineTest` |
| 3 | Locais permitidos (`location.<id>` direto) | F3 | `permitted_locations()` | Tier 0 | `PedidosCompraBaselineTest` |
| 4 | "Ver os próprios" (`view_own` sem `view_all`) | F3 | `where created_by` | alta | `PedidosCompraBaselineTest` |
| 5 | 403 sem `view_all`/`view_own` | F3 | `abort(403)` no topo do `index()` | alta | `PedidosCompraBaselineTest` |
| 6 | Quantidade restante = Σ(`quantity` − `po_quantity_purchased`) | F3 | `SUM(...) as po_qty_remaining` + `@format_quantity` | média | `PedidosCompraBaselineTest` (dois caminhos) |
| 7 | Filtros: local · fornecedor · status · status do envio · período | F3 | `request()->location_id/supplier_id/status/shipping_status/start_date..end_date` | média | status em `PedidosCompraBaselineTest`; os demais no contrato da F3 |
| 8 | Colunas: data · nº ref · local · fornecedor (+ nome fantasia) · status · restante · envio · adicionado por | F3 | `index.blade.php:89-97` | média | contrato da F3 |
| 9 | Ações: ver · imprimir · baixar PDF · editar · excluir · editar envio · documento · notificação | F3 (ver/editar/PDF); demais via `?legacy=1` | `addColumn('action')` | média | casos da F3 |
| 10 | Alterar status inline (admin, fora de `completed`) | Blade (`?legacy=1`) | `editColumn('status')` | média | PR próprio |
| 11 | Filtro `from_dashboard` | não se aplica (é do painel) | `orHavingRaw('po_qty_remaining > 0')` | baixa | — |

## Fora da paridade (decisão [W])

- KPI "Vindos de requisição" do protótipo: a Blade não tem.
- Total do pedido na lista: a Blade não mostra; o protótipo também não.
