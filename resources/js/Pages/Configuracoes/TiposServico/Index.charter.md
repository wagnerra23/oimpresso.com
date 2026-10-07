---
id: resources-js-pages-configuracoes-tiposservico-index-charter
page: /types-of-service
component: resources/js/Pages/Configuracoes/TiposServico/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx
owner: wagner
status: draft
last_validated: "2026-10-07"
parent_module: Configuracoes
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [TiposServico]
  divergence_from_blueprint: "mostra a tabela de preço por local (a Blade tem; o protótipo não)"
related_runbook: memory/requisitos/Configuracoes/RUNBOOK-tipos-servico.md
---

# Page Charter — /types-of-service (DRAFT)

> **Status:** draft. Thread `sistema/playbook/05`, tela 2 de 3. `GET /types-of-service` responde Inertia só com a flag
> `useV2ConfiguracoesTiposServico` ligada para o negócio; desligada, a Blade `types_of_service/index` segue. Vira `live`
> no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Manter as formas de atender a venda (balcão, entrega, montagem…), cada uma com taxa de embalagem e tabela de preço por local.

## Goals

- Lista dos tipos do negócio com a taxa (R$ ou %), a tabela de preço por local e se pede campos extras na venda.
- Cadastrar e editar num drawer: taxa em texto pt-BR, tabela de preço por local com as mesmas listas do `create()` da Blade.
- Excluir pelo endpoint de sempre.
- Abas de Configurações (D1, [W] 2026-10-06), derivadas do `shell.menu`.

## Non-Goals

- ❌ Mudar o parser da taxa: a tela manda texto pt-BR e o `num_uf` do `store`/`update` converte (regra mestre de valor).
- ❌ Mostrar local ou tabela de preço de outro negócio na tabela por local (o JSON não é conferido no banco; a tela filtra).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só tipos do negócio da sessão; locais e tabelas resolvidos só entre os do negócio | `TypesOfServiceController::tiposDoNegocio` |
| R2 | Com a flag desligada a rota devolve a Blade | `TypesOfServiceController::index` (`FLAG_V2`) |
| R3 | Excluir só alcança tipo do negócio | `destroy()` |
| R4 | Sem `access_types_of_service`, 403 | todas as ações |

## Refs

RUNBOOK: [`RUNBOOK-tipos-servico.md`](../../../../../memory/requisitos/Configuracoes/RUNBOOK-tipos-servico.md) · paridade: [`tipos-servico-parity.md`](../../../../../memory/requisitos/Configuracoes/tipos-servico-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
