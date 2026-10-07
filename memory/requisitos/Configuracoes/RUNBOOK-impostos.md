---
slug: configuracoes-runbook-impostos
title: "Configurações — Runbook da tela Impostos"
type: runbook
module: Configuracoes
tela: Configuracoes/Impostos/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Impostos (`/tax-rates`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/05`, tela 1 de 3 (Impostos → Tipos de serviço → Esquemas de fatura)
> **Fonte de design:** `prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx` → `Impostos()` (rota `cfg-impostos`).
> **Decisão D1 ([W] 2026-10-06):** Configurações com abas; a aba entra no `ConfiguracoesSubNav` pelo menu.
> **Regra mestre de valor:** `amount` é a alíquota que entra no cálculo do imposto da venda. A tela nova manda o mesmo
> texto pt-BR que a Blade (`"18,00"`) e o `num_uf` do `store`/`update` continua sendo o único parser.

## Estado final esperado

`GET /tax-rates` (`TaxRateController::index`) responde Inertia `Configuracoes/Impostos/Index` **quando a flag
`useV2ConfiguracoesImpostos` está ligada** para o negócio; desligada, segue a Blade `tax_rate/index`. A página mostra as
alíquotas e os grupos de imposto, como a Blade.

## 1. Objetivo

Trocar a Blade (duas DataTables + modais) pela aba React, sem mudar o parser da alíquota, o recálculo dos grupos nem as
permissões.

## 2. Pré-condições

- Permissões: `tax_rate.view` ou `tax_rate.create` (ver), `tax_rate.create`/`.update`/`.delete` (ações).
- Tabela `tax_rates` (soft delete). Grupo = linha com `is_tax_group = 1` + pivô `group_sub_taxes`.

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`impostos-parity.md`](./impostos-parity.md) + `ImpostosBaselineTest`.
2. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). Page + charter + casos.
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: alíquotas · grupos · vazio · drawer novo/editar · confirmação de exclusão (recusada se a
alíquota está num grupo) · aviso de NF-e. Tabela rola abaixo de 768px. `/` busca · `n` nova alíquota.
`data-contract`: `page-header`, `aliquotas-table`, `grupos-table`, `aliquota-form`, `confirm-excluir`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99, valor por dois caminhos)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos (F3)
- [ ] Smoke biz=1 (F4) · cutover e remoção das Blades `tax_rate/*` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- **Alíquota em texto pt-BR.** A tela manda vírgula (`"7,60"`), como o `input_number` da Blade, e o `num_uf` segue
  único parser. A heurística dele (`Util::num_uf`, desde 2026-05-28): vírgula é decimal; ponto seguido de **3** dígitos
  é milhar (`"7.600"` → 7600); ponto com até 2 dígitos é decimal (`"7.6"` → 7,6). Por isso a tela nunca manda ponto.
- Editar uma alíquota **recalcula a alíquota de todo grupo** que a usa (`TaxUtil::updateGroupTaxAmount`).
- Excluir é recusado se a alíquota está em algum grupo ("can_not_be_deleted").
- **Grupos de imposto** são do `GroupTaxController` (fora do prefixo da thread). A tela nova chama os endpoints dele como
  estão. ⚠️ O `store()`/`update()` dele aceita alíquotas de **outro negócio** como sub-impostos e o `store()` não confere
  permissão — achado registrado no `_saida-05`, conserto fora desta thread (mexe em valor de imposto, decisão [W]).
- A Blade mostra o aviso "Configuração Fiscal Avançada" com link para `/nfe-brasil/tributacao` quando o negócio tem
  `nfe_business_configs`. A tela nova mantém.
- "Só em grupo" (`for_tax_group`): alíquota que só existe para compor grupo; o protótipo mostra como coluna.
- Inertia v3 manda `X-Requested-With`: sem `! $request->inertia()` o ramo DataTable engole a visita.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
