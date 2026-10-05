---
slug: cliente-runbook-grupos
title: "Cliente — Runbook da tela Grupos de cliente"
type: runbook
module: Cliente
tela: Cliente/Grupos/Index
owner: W
status: ativo
last_validated: "2026-10-05"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# RUNBOOK — Grupos de cliente (`/customer-group`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `cliente/playbook/03`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/cliente-grupos.jsx` (`ClienteGruposPage`) · alvo medido `governance/design/targets/cliente--grupos--index.*` (#8663)
> **Decisão [W]:** D2 (2026-10-01) — tela própria `Cliente/Grupos`; D1 — aposentar dual-render no Cliente (sem flag).

## Estado final esperado

`GET /customer-group` (`CustomerGroupController::index`) responde Inertia `Cliente/Grupos/Index` com a lista
dos grupos do negócio da sessão, o ajuste de preço de cada um e quantos cadastros usam o grupo. Criar,
editar e excluir abrem diálogo na própria tela e gravam pelos endpoints de sempre (`store`, `update`,
`destroy`), que devolvem JSON `{success, msg}`.

## 1. Objetivo

Trocar a Blade (DataTable + modal Bootstrap + HTML de botão montado no controller) pela tela React, sem
mudar como o valor é gravado.

## 2. Pré-condições

- Permissões: `customer.view` (ver), `customer.create`, `customer.update`, `customer.delete`.
- Tabelas: `customer_groups`, `selling_price_groups`, `contacts.customer_group_id`.

## 3. Passo-a-passo

1. `index()` monta `grupos` (id, nome, cálculo, percentual, tabela, cadastros), `tabelas` e `pode`.
2. A Page lista, filtra por nome e abre o diálogo de criar/editar.
3. O diálogo manda `amount` **como texto pt-BR** (`"10,50"`, `"-5,25"`), igual ao `input_number` da
   Blade; o `num_uf` do `store/update` continua sendo o único parser.
4. Excluir confirma em diálogo e chama `DELETE /customer-group/{id}`.

## 4. Tokens CSS

Só tokens do DS (`text-muted-foreground`, `border`, `text-success`, `text-destructive`). Sem cor crua.

## 5. Estados visuais

Lista com grupos · vazio (nenhum grupo) · sem resultado na busca · diálogo novo/editar · confirmação de
excluir (com o número de cadastros afetados).

## 6. Responsividade

Tabela rola na horizontal abaixo de 768px; diálogo ocupa a largura em telas estreitas.

## 7. Atalhos

`/` foca a busca · `n` abre "Novo grupo" (só com `customer.create`).

## 8. Component contract

Seções `data-contract`: `page-header`, `toolbar`, `grupos-table`, `vazio`, `grupo-form`, `confirm-excluir`.
Contrato: `governance/design/contracts/cliente-grupos.contract.json`.

## 9. DoD checklist

- [x] Inertia em `index()`, sem o ramo DataTable
- [x] Pest de contrato (tenant 98 × 99) na lane `cliente-pest`
- [ ] Smoke em produção depois do deploy (R1)
- [ ] Aprovação do screenshot [W2] e remoção das Blades `customer_group/*` (cutover, decisão [W])

## 10. Pegadinhas

- **O percentual é um AJUSTE com sinal**, não um desconto: a própria dica da Blade
  (`lang_v1.tooltip_calculation_percentage`) diz "positiva para aumentar e negativa para diminuir". O
  protótipo mostra "Desconto −X%" e só aceita dígitos; aqui o sinal e os decimais ficam.
- O Inertia manda `X-Requested-With`: um ramo `request()->ajax()` em `index()` engole a visita.
- `store/update` aceitavam `selling_price_group_id` de outro negócio até esta thread.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
