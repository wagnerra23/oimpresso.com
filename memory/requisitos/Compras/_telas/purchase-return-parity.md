---
id: requisitos-compras-purchase-return-parity
titulo: Paridade de migração — /purchase-return (Devoluções de compra) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /purchase-return
related:
  - ../../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-purchase-return-index.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Devoluções de compra (`/purchase-return`)

## Metadados

- **Tela React:** `resources/js/Pages/PurchaseReturn/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/purchase_return/index.blade.php` (DataTable + modais de pagamento)
- **Controller:** `App\Http\Controllers\PurchaseReturnController::index` (lista); editar/pagar/excluir seguem nos métodos de hoje
- **Rota nova / legada:** a mesma `Route::resource('/purchase-return')->except('create')`; a Blade segue com a flag desligada ou `?legacy=1`
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Só devoluções do negócio da sessão | F3 | `where transactions.business_id` | Tier 0 | `DevolucoesCompraBaselineTest` (98 × 99) |
| 2 | Só `type = purchase_return` | F3 | `where transactions.type` | alta | `DevolucoesCompraBaselineTest` |
| 3 | Locais permitidos (`location.<id>` direto) | F3 | `permitted_locations()` | Tier 0 | `DevolucoesCompraBaselineTest` |
| 4 | 403 sem `purchase.view`/`purchase.create` | F3 | `abort(403)` no topo do `index()` | alta | `DevolucoesCompraBaselineTest` |
| 5 | Total da devolução (`final_total`) | F3 | `editColumn('final_total')` + `data-orig-value` | **valor** | `DevolucoesCompraBaselineTest` (dois caminhos) |
| 6 | Devido = total − Σ pagamentos (nulo = 0) | F3 | `addColumn('payment_due')` | **valor** | `DevolucoesCompraBaselineTest` (dois caminhos, com e sem pagamento) |
| 7 | Rodapé: soma de total e de devido; contagem por status de pagamento | decisão [W] | `index.blade.php:159-166` (JS) | **valor** | `DevolucoesCompraBaselineTest` trava os números que o rodapé soma |
| 8 | Compra de origem (`parent_purchase`) | F3 | `leftJoin transactions AS T` | média | `DevolucoesCompraBaselineTest` |
| 9 | Filtros: local · fornecedor · período | F3 | `request()->location_id/supplier_id/start_date..end_date` | média | local em `DevolucoesCompraBaselineTest`; os demais no contrato da F3 |
| 10 | Status do pagamento (rótulo "recebido" quando `paid`) | F3 | `editColumn('payment_status')` | média | contrato da F3 |
| 11 | Ações: editar · pagar · ver pagamentos · excluir | Blade (`?legacy=1`) | `addColumn('action')` | média | PR próprio |

## Fora da paridade (decisão [W])

- KPI "Por status de pagamento" e total do rodapé na tela nova: é soma/contagem de valor — só com dupla prova e [W].
