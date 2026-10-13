---
slug: crm-runbook-pedidos
title: "Crm — Runbook da tela Pedido de ordem (pedidos de clientes)"
type: runbook
module: Crm
tela: Crm/Pedidos/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Pedido de ordem (`/crm/order-request`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `crm/playbook/17`.
> **Fonte de design:** alvo `governance/design/targets/crm--pedidos--index.alvo.json` (thread A2, #9255), medido sobre a rota `crm-pedidos` do espelho (`crm-blade-telas.jsx` → `Pedidos`).

## Qual tela é esta

O `OrderRequestController` serve **duas** listas:

| rota | método | quem usa | nesta thread |
|---|---|---|---|
| `GET /crm/order-request` | `listOrderRequests()` | a equipe (menu Vendas, se `enable_order_request`) | **migra** |
| `GET /contact/order-request` | `index()` | o contato logado no portal | fica na Blade |

O alvo foi medido dentro da navegação do CRM da equipe (14 abas), então a Page é a lista da equipe.
O protótipo cita `order_request/index.blade.php` (a do portal) e traz o botão "Adicionar solicitação de
pedido", que só existe no portal: a equipe não cria pedido de cliente. Esse botão **não entra** (ver §4).

## Estado final esperado

`GET /crm/order-request` responde Inertia `Crm/Pedidos/Index` **quando a flag `useV2CrmPedidos` está ligada**
para o negócio. Desligada (default), ou com `?classico=1`, segue a Blade `crm::order_request.all_list`.
Esta fatia é **só leitura**.

## 1. Objetivo

Listar os pedidos que os clientes fizeram pelo portal (`transactions.type = sales_order` com
`crm_is_order_request = 1`), com data, número, contato, local, status, quantidade restante e total.

## 2. Pré-condições

- Hoje `listOrderRequests()` não checa permissão; a Blade só desenha a grade para `so.view_all` ou
  `so.view_own`, e os dados vêm de `GET /sells?sale_type=sales_order&crm_is_order_request=1`
  (`SellController@index`). A Page segue a mesma regra: sem as duas permissões, nenhuma linha.
- Lane `verticais-pest.yml` (MySQL) roda `Modules/Crm/Tests/Feature/CrmPedidos*`.

## 3. Paridade — Blade de hoje × protótipo × Page

| item | Blade (`all_list.blade.php` + `/sells`) | protótipo (`Pedidos`) | Page `Crm/Pedidos/Index` |
|---|---|---|---|
| título | "Order request" | "Pedido de ordem" | o do protótipo |
| filtros | Local · Cliente · Status · Intervalo de datas | Local do negócio · Status · Intervalo | Local do negócio · Cliente · Status · Intervalo |
| busca | busca global do DataTables | "Buscar por nº ou contato" | nº ou contato, no servidor |
| colunas | Ação · Data · Nº · Cliente · Celular · Local · Status · Qtd. restante · Adicionado por | Data · Nº do pedido · Contato · Local · Status · Quantidade restante · Total | as 7 do protótipo |
| rodapé | — | "Total dos pedidos filtrados" | soma do conjunto filtrado (todas as páginas) |
| linhas | `getListSells(sales_order)` + `crm_is_order_request` + locais permitidos + "só os meus" | mock | a mesma consulta, paginada (25) |

## 4. Divergências declaradas

- **"Adicionar solicitação de pedido"** não entra: é ação do portal (`create()` exige `crm_contact_id` no usuário).
- **Cliente** continua como filtro (a Blade tem; o protótipo não).
- A ação da linha (mudar status do pedido de venda) segue na Blade (`?classico=1`).

## 5. Valor — regra mestre (CÁLCULO DE VALOR)

A Page **mostra** `transactions.final_total` e a **soma** do conjunto filtrado; não grava nem recalcula
nada. Mesmo assim, o rodapé é um número novo na tela, então a soma é provada por dois caminhos no teste
de contrato (fixture com totais conhecidos × soma das linhas devolvidas). Nenhum valor é alterado.

## 6. Regras Tier 0 da tela

- `transactions.business_id` da sessão (vem do `getListSells`), locais permitidos do usuário, e
  "só os meus" (`created_by`) para quem tem só `so.view_own` — como o `SellController`.
- Tenant de teste **98**, vizinho **99** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)). Nunca biz=4.

## 7. Passo-a-passo

1. **F1** — este RUNBOOK.
2. **F2** — `CrmPedidosBaselineTest` trava a Blade de hoje: a view abre; o `/sells` com
   `crm_is_order_request` lista só pedidos de cliente do negócio (98 × 99) e só os meus com `so.view_own`.
3. **F3** — `listOrderRequests()` ganha o ramo Inertia atrás da flag; `Crm/Pedidos/Index.tsx` + charter +
   casos + contrato `crm-pedidos` + `CrmPedidosContratoTest`.
4. **F4** — smoke em biz=1 com a flag ligada (`flag:set useV2CrmPedidos --biz=1`).
5. **F5** — cutover é decisão [W].

## 8. Pegadinhas

- `listOrderRequests()` não tem gate de permissão nem de módulo: qualquer usuário da equipe abre a Blade
  (vazia). Registrado; fechar isso é PR próprio.
- `getListSells` faz `leftJoin` em linhas de venda: sem `groupBy('transactions.id')` o total duplica.
  A soma do rodapé é feita sobre a consulta agrupada (`fromSub`), nunca sobre o join cru.
- O portal (`/contact/order-request`) e a criação de pedido ficam fora desta thread.

## 9. DoD checklist

- [ ] `CrmPedidosBaselineTest` e `CrmPedidosContratoTest` verdes na lane `verticais-pest.yml`, com assertions > 0.
- [ ] `contrato-de-tela --contract crm-pedidos.contract.json` limpo.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
