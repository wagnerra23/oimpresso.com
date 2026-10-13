---
id: requisitos-compras-purchase-requisition-parity
titulo: Paridade de migração — /purchase-requisition (Requisições de compra) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /purchase-requisition
related:
  - ../../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-purchase-requisition-index.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Requisições de compra (`/purchase-requisition`)

## Metadados

- **Tela React:** `resources/js/Pages/PurchaseRequisition/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/purchase_requisition/index.blade.php` (DataTable + modal `view_modal`)
- **Controller:** `App\Http\Controllers\PurchaseRequisitionController::index` (lista); criar, ver e excluir seguem nos métodos de hoje
- **Rota nova / legada:** a mesma `Route::resource('purchase-requisition')->except(['edit','update'])`; a Blade segue com a flag desligada ou `?legacy=1`
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Só requisições do negócio da sessão | F3 | `where transactions.business_id` | Tier 0 | `RequisicoesCompraBaselineTest` (98 × 99) |
| 2 | Só `type = purchase_requisition` | F3 | `where transactions.type` | alta | `RequisicoesCompraBaselineTest` |
| 3 | Locais permitidos (`location.<id>` direto) | F3 | `permitted_locations()` | Tier 0 | `RequisicoesCompraBaselineTest` |
| 4 | "Ver as próprias" (`view_own` sem `view_all`) | F3 | `where created_by` | alta | `RequisicoesCompraBaselineTest` |
| 5 | 403 sem `view_all`/`view_own` | F3 | `abort(403)` no topo do `index()` | alta | `RequisicoesCompraBaselineTest` |
| 6 | "Necessário até" formatado pela `@format_datetime` e filtrável por intervalo | F3 | `editColumn('delivery_date')` · `required_by_start..required_by_end` | média | `RequisicoesCompraBaselineTest` |
| 7 | Filtros: local · status · período | F3 | `request()->location_id/status/start_date..end_date` | média | status em `RequisicoesCompraBaselineTest`; os demais no contrato da F3 |
| 8 | Colunas: data · nº ref · local · status · necessário até · adicionado por | F3 | `index.blade.php:80-86` | média | contrato da F3 |
| 9 | Ações: ver · excluir | F3 (ver via tela clássica); excluir via `?legacy=1` | `addColumn('action')` | média | casos da F3 |
| 10 | JOIN INNER com `users` (requisição sem autor não aparece) | F3 (mesmo SQL) | `->join('users as u', …)` | baixa | mesmo método nos dois caminhos |
| 11 | Filtro `from_dashboard` | não se aplica (é do painel) | `status != 'completed'` | baixa | — |

## Fora da paridade (decisão [W])

- Colunas "pedido gerado" e "motivo" do protótipo: a lista da Blade não tem.
