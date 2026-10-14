---
slug: restaurante-runbook-mesas
title: "Restaurante — Runbook da tela Mesas"
type: runbook
module: Restaurante
tela: Restaurante/Mesas/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Mesas (`/modules/tables`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `restaurante/playbook/01`.
> **Fonte de design:** alvo `governance/design/targets/restaurante--mesas--index.alvo.json` (thread A1), medido sobre `prototipo-ui/cowork/Wagner/integra-extras.jsx` → `RestauranteExtrasPage view="cfg-mesas"`.
> **Decisão de origem:** [W] 07/10 no formulário do SINCRONIZAR: *"Usamos — migrar para React"*.

## Estado final esperado

`GET /modules/tables` (`TableController::index`) responde Inertia `Restaurante/Mesas/Index` **quando a flag
`useV2RestauranteMesas` está ligada** para o negócio. Desligada (default), ou com `?classico=1`, segue a Blade
`restaurant.table.index`, que hospeda os modais de cadastrar, editar e excluir. A DataTable da Blade continua
respondendo o `ajax()` sem `X-Inertia`.

## 1. Objetivo

Listar as mesas do negócio (mesa · local · descrição) com a situação de cada uma, no desenho do alvo da A1, sem
mudar o que a Blade grava.

## 2. Pré-condições

- Permissão `access_tables` (a única que a tela de papéis oferece para Mesas; o controller a exige em toda ação).
- Módulo `tables` ligado no negócio (`enabled_modules`) para o item aparecer no menu.
- Lane `acessos-pest.yml` (MySQL) roda `tests/Feature/Restaurante/`.

## 3. Passo-a-passo

1. F2 — `MesasBaselineTest` trava a Blade hoje: DataTable só com mesas do negócio (98 × 99), 403 sem `access_tables`,
   botões de editar e excluir só para o papel `Admin#<negócio>`.
2. F3 — `TableController::index` ganha o ramo Inertia atrás da flag; a lista sai de `mesasDoNegocio()` (deferida).
3. F3 — `Restaurante/Mesas/Index.tsx` + charter + casos + `MesasContratoTest` (UC-MESA-01..04).
4. F4 — smoke em biz=1 com a flag ligada (`flag:set useV2RestauranteMesas --biz=1`).
5. F5 — cutover (flag default ligada) é decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Herdados do PT-01 e do `AppShellV2`. Estados: carregando (prop deferida), vazio (sem mesa — link para cadastrar na
clássica), busca sem resultado. Atalho `/` foca a busca.

## 9. DoD checklist

- [ ] `MesasBaselineTest` e `MesasContratoTest` verdes na lane `acessos-pest.yml`, com assertions > 0.
- [ ] `secao-check --tela restaurante--mesas--index --servir-espelho` conforme (o alvo é do protótipo).
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. Pegadinhas e divergências do protótipo

- **Situação da mesa.** O protótipo deriva "ocupada" de um atendente *em atendimento* no local — estado que o ERP não
  tem. A tela usa a regra que o ERP já tem para pedido aberto: `RestaurantUtil::getAllOrders()` (venda `final`, pedido
  ainda não `served`). Mesa ocupada = tem pedido aberto; atendente = `res_waiter_id` do pedido aberto mais recente.
- **Pedidos hoje.** Vendas `final` do dia com mesa (`res_table_id`), do negócio. O protótipo soma "o pessoal de
  atendimento"; no ERP não existe contador por atendente fora das vendas.
- **Editar e excluir** só aparecem para `Admin#<negócio>`, como na DataTable (`@role`). Ficam na tela clássica.
- **Inertia v3 manda `X-Requested-With`** em toda visita: o ramo da DataTable exige `ajax() && ! inertia()`
  (§5 2026-09-08, molde `AccountController::index`).
- A aba **Atendentes** do protótipo não vira link: o índice não lhe deu dono (`00-INDICE.md:238`).

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
