---
slug: sells-runbook-sell-return-create
title: "Sells — Runbook da Nova devolução /sell-return/create (origem da devolução)"
type: runbook
module: Sells
tela: SellReturn/Create
status: ativo
owner: W
last_validated: "2026-11-24"
date: 2026-11-24
preconditions:
  - "Permissão access_sell_return OU access_own_sell_return"
  - "Escopo business_id da sessão em toda query (ADR 0093)"
steps:
  - "SellReturnController@create renderiza SellReturn/Create com a lista de vendas deferida"
  - "Botão + Nova devolução de SellReturn/Index aponta para /sell-return/create"
  - "Contrato Pest em tests/Feature/Sells/SellReturnCreateContratoTest.php na lane sells-pest"
---

# RUNBOOK — Nova devolução (`/sell-return/create`)

> **Tipo:** tela nova (não há Blade antes dela). **Protótipo:** `prototipo-ui/cowork/Wagner/devolucao-v3.html`,
> etapa 1 "Origem da devolução" no modo venda.
> **Refs:** ADR 0093 (multi-tenant) · [RUNBOOK-sell-return-add.md](RUNBOOK-sell-return-add.md) §6 (matriz do protótipo).

## 1. O que a tela faz

O botão "+ Nova devolução" da lista levava para `/sells`, e a pessoa tinha de achar a venda e
usar "Devolver". Agora ele abre a etapa 1 do protótipo: a lista de vendas finalizadas, com os
filtros Todas · Com NF-e · Sem nota e a busca por cliente, número da venda ou número da nota.
"Usar esta venda" abre o registro que já existe (`/sell-return/add/{venda}` → `SellReturn/Add`).

A tela só lê. Não grava, não calcula valor e não mexe em estoque — a regra mestre de valor e
estoque segue no registro (`store()`), que não mudou.

## 2. O que ficou fora, e por quê

| Do protótipo | Por que não está aqui |
|---|---|
| Filtro e linhas de **Consignação** ("Fazer acerto") | Não existe consignação no repo (matriz §6.1 linha 12 do RUNBOOK do registro) |
| **"Não é devolução?"** (garantia, conserto, comodato) | Não existe fluxo de destino para esses casos |
| Total da NF-e no painel **Fechamento fiscal** | Os totais nascem no registro, depois de escolher os itens; aqui o painel só mostra R$ 0,00 e explica o próximo passo |
| Modos compra e estoque | Devolução de compra é outra tela (`PurchaseReturn`) |

## 3. Escopo dos dados

O mesmo do `validateInvoiceToReturn` (o caminho Blade que acha a venda pelo número): business
da sessão, `type=sell`, `status=final`, locais permitidos, e só as próprias vendas para quem não
tem `direct_sell.access` mas tem `view_own_sell_only`. A nota da venda é a emissão
`nfe_emissoes` com `status=autorizada` da mesma empresa; sem a tabela (NfeBrasil ausente), toda
venda aparece como "Sem nota".

## 4. Rollback

Voltar o `href` do botão em `SellReturn/Index.tsx` para `/sells`. O método `create()` pode ficar:
antes dele, `GET /sell-return/create` respondia erro (o método estava comentado).
