---
slug: comissao-runbook-comissionados
title: "Comissão — Runbook da tela Comissionados"
type: runbook
module: Comissao
tela: Comissionados/Index
owner: W
status: ativo
last_validated: "2026-10-06"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0151-modules-comissao-feature-wish'
---

# RUNBOOK — Comissionados (`/sales-commission-agents`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/03` (o playbook `comissoes` aponta para ela como thread 01)
> **Fonte de design:** `prototipo-ui/cowork/Wagner/comissionados-page.jsx` (`ComissionadosPage`). Sem alvo medido ainda — o contrato vem depois do alvo.
> **Fronteira:** [ADR 0151](../../decisions/0151-modules-comissao-feature-wish.md) — nada de `Modules/Comissao`; a tela fica no legado (`users.is_cmmsn_agnt`, `users.cmmsn_percent`).

## Estado final esperado

`GET /sales-commission-agents` (`SalesCommissionAgentController::index`) responde Inertia `Comissionados/Index`
com os comissionados do negócio da sessão, o percentual de cada um e quantas vendas apontam para ele.
Cadastrar e editar abrem um drawer na própria tela; remover confirma em diálogo. Os três gravam pelos
endpoints de sempre (`store`, `update`, `destroy`), que devolvem JSON `{success, msg}`.

## 1. Objetivo

Trocar a Blade (DataTable + modal Bootstrap + HTML de botão montado no controller) pela tela React, sem
mudar como o percentual é gravado nem as permissões.

## 2. Pré-condições

- Permissões: `commission_agent.view` (ver) e `commission_agent.manage` (cadastrar, editar, remover) — as de
  sempre desde 2026-08-20 (`2026_08_20_120000_add_commission_agent_permissions`).
- Tabelas: `users` (`is_cmmsn_agnt`, `cmmsn_percent`) e `transactions.commission_agent` (sem FK).

## 3. Passo-a-passo

1. `index()` monta `agentes` (deferido: id, prefixo, nomes, e-mail, contato, endereço, percentual, vendas) e `pode`.
2. A Page lista, filtra por nome ou e-mail e abre o drawer de cadastrar/editar.
3. O drawer manda `cmmsn_percent` **como texto pt-BR** (`"2,50"`), igual ao `input_number` da Blade; o
   `num_uf` do `store/update` continua sendo o único parser.
4. Remover chama `DELETE /sales-commission-agents/{id}`, que **desmarca** o papel (não apaga o usuário). Com
   venda vinculada o servidor devolve 422; a tela já avisa antes, pela mesma contagem.

## 4. Tokens CSS

Só tokens do DS (`text-muted-foreground`, `bg-muted`, `border`, `text-destructive`). Sem cor crua — o avatar
colorido por hash do protótipo fica neutro.

## 5. Estados visuais

Lista · vazio (nenhum comissionado) · sem resultado na busca · drawer novo/editar · confirmação de remover
(livre ou bloqueada por venda vinculada) · aviso da última ação.

## 6. Responsividade

Tabela rola na horizontal abaixo de 768px; drawer ocupa a largura em telas estreitas.

## 7. Atalhos

`/` foca a busca · `n` abre "Novo comissionado" (só com `commission_agent.manage`).

## 8. Component contract

Seções `data-contract`: `page-header`, `toolbar`, `comissionados-table`, `vazio`, `comissionado-form`,
`confirm-remover`. Contrato de forma: depois do alvo (thread do playbook).

## 9. DoD checklist

- [x] Inertia em `index()`, sem o ramo DataTable
- [x] `edit()` só alcança comissionado do negócio da sessão
- [x] Pest de contrato (tenant 98 × 99) na lane `acessos-pest`
- [ ] Smoke em produção depois do deploy (R1)
- [ ] Remoção das Blades `sales_commission_agent/*` (cutover, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- O protótipo mostra KPIs (vendas, comissão apurada, a pagar), período, meta, situação de pagamento e regra
  por faixa/margem. **Nada disso tem fonte no legado**: a apuração é a thread `comissoes/02` (base paga ×
  faturada ainda em D-COM-2) e regras novas são `Modules/Comissao` (ADR 0151). Ficam fora em vez de
  renderizar número inventado.
- "Excluir" no protótipo; aqui **"Remover dos comissionados"**, porque é o que o `destroy()` faz desde
  #5970: desmarca `is_cmmsn_agnt`, o usuário continua.
- O `store()` **marca** um usuário existente quando o e-mail identifica exatamente uma pessoa do negócio
  (#6069) — o drawer avisa isso no cadastro.
- O Inertia manda `X-Requested-With`: um ramo `request()->ajax()` em `index()` engole a visita.
- `edit()` fazia `User::findOrFail($id)` sem negócio até esta thread (Blade de edição abria dado de outro tenant).

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0151](../../decisions/0151-modules-comissao-feature-wish.md)
