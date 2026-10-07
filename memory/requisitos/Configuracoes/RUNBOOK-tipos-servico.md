---
slug: configuracoes-runbook-tipos-servico
title: "Configurações — Runbook da tela Tipos de serviço"
type: runbook
module: Configuracoes
tela: Configuracoes/TiposServico/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Tipos de serviço (`/types-of-service`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/05`, tela 2 de 3
> **Fonte de design:** `prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx` → `Servicos()` (rota `cfg-servicos`).
> **Decisão D1 ([W] 2026-10-06):** Configurações com abas; a aba entra no `ConfiguracoesSubNav` pelo menu.
> **Regra mestre de valor:** `packing_charge` (taxa de embalagem, fixa em R$ ou percentual) entra no total da venda.
> A tela manda o mesmo texto pt-BR que a Blade (`input_number`) e o `num_uf` segue único parser.

## Estado final esperado

`GET /types-of-service` (`TypesOfServiceController::index`) responde Inertia `Configuracoes/TiposServico/Index` **quando
a flag `useV2ConfiguracoesTiposServico` está ligada** para o negócio; desligada, segue a Blade `types_of_service/index`.
A rota só aparece com o módulo `types_of_service` ligado no negócio (menu do `AdminSidebarMenu`).

## 1. Objetivo

Trocar a Blade (DataTable + modal) pela aba React, sem mudar o parser da taxa, a tabela de preço por local nem a permissão.

## 2. Pré-condições

- Permissão: `access_types_of_service` (todas as ações). Módulo do negócio `types_of_service` (Camada 2).
- Tabela `types_of_services`; `location_price_group` é JSON `{location_id: price_group_id}` (cast `array` no model).

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`tipos-servico-parity.md`](./tipos-servico-parity.md) + `TiposServicoBaselineTest`.
2. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). Page + charter + casos.
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: lista · vazio · drawer novo/editar (tabela de preço por local) · confirmação de exclusão.
`/` busca · `n` novo. `data-contract`: `page-header`, `tipos-table`, `tipo-form`, `confirm-excluir`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99, valor por dois caminhos)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos (F3)
- [ ] Smoke biz=1 (F4) · cutover e remoção das Blades `types_of_service/*` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- **Taxa em texto pt-BR** (`"35,00"`, `"8,00"`), como o `input_number` da Blade; vazio grava 0.
- `store()` grava `location_price_group` pelo cast do model; `update()` faz `json_encode` à mão. Os dois resultam no
  mesmo JSON — o baseline prova. Medido em 2026-10-07: tirar aquele `json_encode` não muda o JSON gravado (mutante
  equivalente nesta versão do Laravel), então ele é redundante, não defesa.
- `update()`/`destroy()` de id de outro negócio não alteram nada mas respondem `success: true` (o `where` acha 0 linhas).
- Os ids de local e de tabela de preço do `location_price_group` não são conferidos contra o negócio. A tela nova só
  oferece os do negócio (as mesmas listas do `create()`).
- Inertia v3 manda `X-Requested-With`: sem `! $request->inertia()` o ramo DataTable engole a visita.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
