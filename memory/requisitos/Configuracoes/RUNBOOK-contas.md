---
slug: configuracoes-runbook-contas
title: "Configurações — Runbook da tela Contas de pagamento"
type: runbook
module: Configuracoes
tela: Configuracoes/Contas/Index
owner: W
status: historical
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Contas de pagamento (`/account/account`)

> **Histórico desde 2026-10-27.** Decisão [W] (opção A): a tela única de contas é `/financeiro/contas-bancarias`;
> `/account/account` redireciona e a Page `Configuracoes/Contas/Index` foi removida. O runbook vivo é
> [`RUNBOOK-contas-bancarias.md`](../Financeiro/RUNBOOK-contas-bancarias.md). O texto abaixo é o registro da thread 06.

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/06`, a parte de Contas (a de Modelos de notificação já está em Inertia: #9113, #9115, #9159)
> **Fonte de design:** `prototipo-ui/cowork/Wagner/financeiro-legado.jsx` → `telaContas()` (rota `fin-bancos`, "Contas de pagamento"). O `_saida-00` registra que Contas não está entre as 21 telas do menu Sistema do protótipo e manda ancorar aqui.
> **Regra mestre de valor:** o saldo é valor. A tela nova não recalcula saldo no navegador: o número vem do mesmo SQL da Blade (crédito − débito, sem lançamento apagado). Saldo de abertura, depósito e transferência seguem pelos endpoints e pelo `num_uf` de hoje.

## Estado final esperado

`GET /account/account` (`AccountController::index`) responde Inertia `Configuracoes/Contas/Index` **quando a flag
`useV2ConfiguracoesContas` está ligada** para o negócio; desligada, segue a Blade `account/index`. A página lista as
contas com tipo, subtipo, número, observação, saldo, detalhes e quem cadastrou, filtradas por situação (ativa/encerrada).

## 1. Objetivo

Trocar a Blade (DataTable + modais) pela tela React sem mudar o SQL do saldo, o filtro por local nem as permissões.

## 2. Pré-condições

- Permissão `account.access` (lista e todas as ações do controller).
- Tabelas `accounts` (soft delete) e `account_transactions` (soft delete, `type` credit/debit, `sub_type`
  opening_balance/fund_transfer/deposit). Tipos em `account_types` (pai → subtipos).

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`contas-parity.md`](./contas-parity.md) + `ContasBaselineTest`.
2. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). Page + charter + casos.
   Cadastrar/editar/depositar/transferir/encerrar ficam nos endpoints de hoje; o drawer é PR à parte.
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: lista ativa · lista encerrada · vazio · aviso de pagamentos sem conta. Tabela rola abaixo
de 768px. `/` busca. `data-contract`: `page-header`, `contas-table`, `filtro-situacao`, `aviso-sem-conta`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99, saldo e abertura por dois caminhos)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos (F3)
- [ ] Drawer de cadastro/edição e ações de movimento (F3, PR próprio, regra mestre de valor)
- [ ] Smoke biz=1 (F4) · cutover e remoção da Blade `account/index` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- **Saldo é SQL, não front.** `SUM(IF(type='credit', amount, -amount))` com `AT.deleted_at IS NULL` no JOIN. O total
  do rodapé da Blade é somado em JavaScript sobre o `data-orig-value` das linhas visíveis; na tela nova qualquer total
  (rodapé, KPIs "Disponível"/"Conta de capital" do protótipo) é cálculo de valor e entra só com dupla prova e
  aprovação [W].
- **Filtro por local.** Quem não tem `access_all_locations` e não é `Admin#<biz>` vê só as contas habilitadas em
  `default_payment_accounts` dos locais permitidos. `permitted_locations()` lê as permissões **diretas** do usuário
  (`$user->permissions`), não as herdadas do papel.
- **Situação.** `account_status=closed` mostra só encerradas; qualquer outro valor mostra só ativas.
- **Aviso da Blade:** pagamentos sem conta vinculada (`transaction_payments` sem `account_id`, fora `advance`) com link
  para o relatório de contas. A tela nova mantém.
- **Aba "Tipos de conta"** da Blade é do `AccountTypeController` (fora do prefixo da thread). A tela nova leva à Blade.
- Inertia v3 manda `X-Requested-With`: sem `! $request->inertia()` o ramo DataTable engole a visita.
- O protótipo põe Contas no Financeiro; a thread põe a Page em `Configuracoes/` (prefixo do índice). A rota não muda.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
