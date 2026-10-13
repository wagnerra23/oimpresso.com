---
slug: restaurante-runbook-modificadores
title: "Restaurante — Runbook da tela Modificadores"
type: runbook
module: Restaurante
tela: Restaurante/Modificadores/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Modificadores (`/modules/modifiers`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `restaurante/playbook/02`.
> **Fonte de design:** alvo `governance/design/targets/restaurante--modificadores--index.alvo.json` (thread A1), medido sobre `prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx` → `Modificadores` (rota `cfg-modificadores`, aba de Configurações).
> **Decisão de origem:** [W] 07/10 no formulário do SINCRONIZAR: *"Usamos — migrar para React"*.

## Estado final esperado

`GET /modules/modifiers` (`ModifierSetsController::index`) responde Inertia `Restaurante/Modificadores/Index` **quando
a flag `useV2RestauranteModificadores` está ligada** para o negócio. Desligada (default), ou com `?classico=1`, segue a
Blade `restaurant.modifier_sets.index`, que hospeda os modais de cadastrar, editar, ligar produtos e excluir. A
DataTable da Blade continua respondendo o `ajax()` sem `X-Inertia`.

## 1. Objetivo

Listar os conjuntos de modificadores do negócio (conjunto · modificadores · produtos) no desenho do alvo da A1, sem
mudar o que a Blade grava.

## 2. Pré-condições

- `product.view` **ou** `product.create` — o mesmo predicado do `ProductController::index` e do menu (#5432).
- Módulo `modifiers` ligado no negócio para o item aparecer no menu (dropdown de Configurações).
- Lane `acessos-pest.yml` (MySQL) roda `tests/Feature/Restaurante/` (ligada na thread 01).

## 3. Passo-a-passo

1. F2 — `ModificadoresBaselineTest` trava a Blade hoje: DataTable só com conjuntos do negócio (98 × 99), 403 sem as
   duas permissões, editar só com `product.update` e excluir só com `product.delete`.
2. F3 — `ModifierSetsController::index` ganha o ramo Inertia atrás da flag; a lista sai de `conjuntosDoNegocio()`
   (deferida).
3. F3 — `Restaurante/Modificadores/Index.tsx` + charter + casos + `ModificadoresContratoTest` (UC-MODIF-01..04).
4. F4 — smoke em biz=1 com a flag ligada (`flag:set useV2RestauranteModificadores --biz=1`).
5. F5 — cutover (flag default ligada) é decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Herdados do PT-01 e do `AppShellV2`. Abas de Configurações pelo `ConfiguracoesSubNav` (derivadas do menu). Estados:
carregando (prop deferida), vazio, busca sem resultado. Atalho `/` foca a busca.

## 9. DoD checklist

- [ ] `ModificadoresBaselineTest` e `ModificadoresContratoTest` verdes na lane `acessos-pest.yml`, com assertions > 0.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. Pegadinhas e divergências do protótipo

- **Preço na lista.** O protótipo mostra cada modificador com o preço ("Bastão (R$ 12,00)"). A Blade mostra só os
  nomes. Exibir preço é valor (regra mestre): fica fora até dupla prova e decisão [W]; a tela mostra os nomes.
- **Produtos ligados de outro negócio.** O pivô `res_product_modifier_sets` não tem `business_id`. A Blade lista o
  que o pivô tiver; a tela nova só lista produtos do mesmo negócio (Tier 0).
- **Inertia v3 manda `X-Requested-With`** em toda visita: o ramo da DataTable exige `ajax() && ! inertia()`
  (§5 2026-09-08, molde `AccountController::index`).

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
