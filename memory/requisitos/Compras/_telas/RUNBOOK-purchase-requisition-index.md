---
slug: compras-runbook-purchase-requisition-index
title: "Compras — Runbook da tela Requisições de compra"
type: runbook
module: Compras
tela: PurchaseRequisition/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Requisições de compra (`/purchase-requisition`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `compras/playbook/07`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/compras-extras.jsx` → `telaReqs()` (rota `cmp-requisicoes`, `window.ComprasExtrasPage`). Alvo medido: `governance/design/targets/compras--requisicoes--index.alvo.json` (thread A2, #9195).
> **Não é tela de valor nem de estoque.** A requisição não tem total nem movimenta estoque; a lista mostra datas, local, status e autor. A sugestão de itens (estoque ≤ alerta) e a conversão em pedido acontecem no cadastro e no pedido, fora desta tela.

## Estado final esperado

`GET /purchase-requisition` (`PurchaseRequisitionController::index`) responde Inertia `PurchaseRequisition/Index` **quando
a flag `useV2ComprasRequisicoes` está ligada** para o negócio; desligada, ou com `?legacy=1`, segue a Blade
`purchase_requisition/index`. A página lista as requisições com data, nº de referência, local, status, "necessário até"
e quem adicionou, com os filtros da Blade (local, status, período, necessário até).

## 1. Objetivo

Trocar a Blade (DataTable + modal) pela tela React sem mudar o SQL da lista, o escopo por negócio, o filtro por local,
o "ver as próprias" nem as permissões.

## 2. Pré-condições

- Permissão `purchase_requisition.view_all` **ou** `purchase_requisition.view_own` (sem nenhuma das duas: 403).
- Requisição = `transactions.type = 'purchase_requisition'`; nasce `ordered`. Status: `ordered` · `partial` · `completed`.
- "Necessário até" = `transactions.delivery_date` (pode ser nulo).

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`purchase-requisition-parity.md`](./purchase-requisition-parity.md) +
   `RequisicoesCompraBaselineTest` na lane `purchase-pest.yml` (que passa a disparar também com
   `PurchaseRequisitionController.php`).
2. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). A consulta sai para um
   método único, usado pelo DataTable e pela tela. Page + charter + casos + contrato.
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: lista com requisições · filtro sem resultado · vazio. Tabela rola abaixo de 768px. `/` busca.
`data-contract`: `page-header`, `requisicoes-kpis`, `requisicoes-table`, `requisicoes-filtros`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99; "necessário até" formatado e filtrado)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos + contrato (F3)
- [ ] Excluir pela tela nova — PR próprio
- [ ] Smoke biz=1 (F4) · cutover e remoção da Blade `purchase_requisition/index` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- **Inertia v3 manda `X-Requested-With`.** O ramo DataTable é `if (request()->ajax())`: sem `! request()->inertia()` ele
  engole a visita React.
- **JOIN com `users` é INNER.** Requisição cujo `created_by` não existe não aparece. A tela nova mantém o mesmo SQL.
- **Datas pela diretiva.** `@format_datetime` carrega o shift de fuso preservado de propósito
  ([ADR 0066](../../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)); a tela nova recebe a data já formatada
  pela mesma expressão, nunca formata no navegador.
- **Rotas sem edit/update** (`->except(['edit', 'update'])`): a requisição não se edita; ações da Blade são ver e excluir.
- **`from_dashboard`** filtra `status != 'completed'`. É do painel; a tela nova não o usa.
- **Protótipo × Blade.** O protótipo mostra o pedido gerado a partir da requisição e o motivo; a lista da Blade não tem
  essas colunas. Entram só com decisão [W].

## 11. ADR de origem

[ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
