---
sessao: "_saida-06"
thread: "06 · Pedidos de compra em React"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "#9200 (F1/F2) + PR desta thread (F3, claude/compras-06-f3-react)"
base_lida: wagnerra23/oimpresso.com@main 530ca1864f
---
# _saida-06

## 1 · Feito
- **F1/F2 (#9200, no `main`):** RUNBOOK `memory/requisitos/Compras/_telas/RUNBOOK-purchase-order-index.md`, mapa
  `purchase-order-parity.md` e `PedidosCompraBaselineTest` (7 casos sobre a DataTable da Blade, 98 × 99). Lane
  `Purchase · Pest (MySQL)`, run 38401261373: 7 ✓.
- **F3 (este PR):** `GET /purchase-order` responde Inertia `PurchaseOrder/Index` quando a flag `useV2ComprasPedidos`
  está ligada para o negócio; desligada (default) ou com `?legacy=1`, segue a Blade.
  - O SQL da lista saiu do `index()` para `PurchaseOrderController::consultaPedidos()`, usado pela DataTable e pela tela.
    Mudança só de lugar: o bloco movido é idêntico ao original, trocado `request()` por `$request`.
  - O ramo DataTable passou a exigir `! request()->inertia()` (o Inertia v3 manda `X-Requested-With`).
  - Trio: `Index.tsx` + `Index.charter.md` + `Index.casos.md` (UC-PEDC-01..05) + `PedidosCompraContratoTest`.
  - Forma: a do alvo da A2 (`compras--pedidos--index`): cabeçalho, KPIs, busca + filtros, tabela, nota de rodapé.

## 2 · Não feito, e por quê
- **Alterar status, editar envio, excluir, notificação:** seguem nos modais da Blade (`?legacy=1`). Escrita entra em PR próprio.
- **KPI "Vindos de requisição"** do protótipo: a Blade não tem; depende de `purchase_requisition_ids`. Decisão [W].
- **"Ver" o pedido:** o `show()` devolve fragmento de modal, não página. A tela oferece PDF e Editar; o detalhe fica na
  tela clássica.
- **F4 (smoke biz=1 com a flag) e F5 (cutover):** decisão [W].
- Comparação medida da tela React contra o alvo (`secao-check` em produção) só depois da flag ligada.

## 3 · Pedido literal
> "Sua próxima tarefa é a Compras 06, Pedidos de compra (PurchaseOrderController@index) em React, atrás de flag OFF,
> com o trio, um teste de contrato na lane Purchase/Compras e o _saida-06." — Gerente da fila de merges (13/10),
> continuando o "pega a próxima thread do playbook de Compras" do [W].

## 4 · Descobertas
- A lane `purchase-pest.yml` não disparava com `PurchaseOrderController.php` e não rodava teste nenhum dele. Agora dispara
  e roda o baseline e o contrato.
- O filtro `from_dashboard` da Blade faz `where status != 'completed'` seguido de `orHavingRaw(...)`. Fica como está; a
  tela nova não o usa.

## 5 · Prefixo tocado
`app/Http/Controllers/PurchaseOrderController.php` · `resources/js/Pages/PurchaseOrder/{Index.tsx,Index.charter.md,Index.casos.md}` ·
`tests/Feature/Purchase/PedidosCompraContratoTest.php` · `.github/workflows/purchase-pest.yml` · este recibo (arquivo novo).
