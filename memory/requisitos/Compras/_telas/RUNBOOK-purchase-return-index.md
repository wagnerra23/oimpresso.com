---
slug: compras-runbook-purchase-return-index
title: "Compras — Runbook da tela Devoluções de compra"
type: runbook
module: Compras
tela: PurchaseReturn/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Devoluções de compra (`/purchase-return`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `compras/playbook/08`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/compras-extras.jsx` → `telaDevs()` (rota `cmp-devolucoes`, `window.ComprasExtrasPage`). Alvo medido: `governance/design/targets/compras--devolucoes--index.alvo.json` (thread A2, #9195).
> **É TELA DE VALOR** (regra mestre de `proibicoes.md`). A lista mostra o total da devolução, o status do pagamento e o devido; o rodapé da Blade soma total e devido. A tela nova não recalcula nem soma valor no navegador: os números vêm do mesmo SQL e da mesma conta da DataTable, e qualquer soma nova entra só com dupla prova e antes→depois apresentado ao [W].

## Estado final esperado

`GET /purchase-return` (`PurchaseReturnController::index`) responde Inertia `PurchaseReturn/Index` **quando a flag
`useV2ComprasDevolucoes` está ligada** para o negócio; desligada, ou com `?legacy=1`, segue a Blade
`purchase_return/index`. A página lista as devoluções com data, nº de referência, compra de origem, local, fornecedor,
status do pagamento, total e devido, com os filtros da Blade (local, fornecedor, período).

## 1. Objetivo

Trocar a Blade (DataTable + modais) pela tela React sem mudar o SQL da lista, a conta do devido, o escopo por negócio,
o filtro por local nem as permissões.

## 2. Pré-condições

- Permissão `purchase.view` **ou** `purchase.create` (sem nenhuma das duas: 403). Não há "ver as próprias".
- Devolução = `transactions.type = 'purchase_return'`; `return_parent_id` aponta a compra de origem (vazio na devolução
  combinada, que edita por `CombinedPurchaseReturnController`).
- Pago = `SUM(transaction_payments.amount)` (LEFT JOIN, agrupado por devolução). Devido = `final_total − pago`, calculado
  em PHP na DataTable; sem pagamento, o SUM é nulo e o devido é o total.

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`purchase-return-parity.md`](./purchase-return-parity.md) + `DevolucoesCompraBaselineTest`
   na lane `purchase-pest.yml` (que passa a disparar também com `PurchaseReturnController.php`).
2. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). A consulta sai para um
   método único, usado pelo DataTable e pela tela; o devido continua sendo a mesma conta. Page + charter + casos + contrato.
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: lista com devoluções · filtro sem resultado · vazio. Tabela rola abaixo de 768px. `/` busca.
`data-contract`: `page-header`, `devolucoes-aviso`, `devolucoes-kpis`, `devolucoes-table`, `devolucoes-filtros`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99; total, devido e soma do rodapé por dois caminhos)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos + contrato (F3)
- [ ] Pagamento, editar e excluir pela tela nova — PR próprio (regra mestre de valor)
- [ ] Smoke biz=1 (F4) · cutover e remoção da Blade `purchase_return/index` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- **Inertia v3 manda `X-Requested-With`.** O ramo DataTable é `if (request()->ajax())`: sem `! request()->inertia()` ele
  engole a visita React.
- **O rodapé da Blade soma no navegador** (`sum_table_col` sobre `data-orig-value`), e só as linhas da página. Na tela
  nova, qualquer total vem do servidor, com dupla prova e aprovação [W] — nunca somado no front.
- **Devido em PHP:** `final_total − amount_paid` com `amount_paid` nulo quando não há pagamento (PHP trata como 0).
- **Editar** vai para `PurchaseReturnController@add` (com compra de origem) ou `CombinedPurchaseReturnController@edit`
  (sem); **pagar** e **ver pagamentos** são modais do `TransactionPaymentController`.
- **Protótipo × Blade.** O protótipo tem o KPI "Por status de pagamento" e o aviso "devolução nasce de uma compra"; a
  contagem por status existe no rodapé da Blade. Entram só com decisão [W] se exigirem soma de valor.

## 11. ADR de origem

[ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
