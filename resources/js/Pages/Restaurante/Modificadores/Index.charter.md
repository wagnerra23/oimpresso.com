---
id: resources-js-pages-restaurante-modificadores-index-charter
page: /modules/modifiers
component: resources/js/Pages/Restaurante/Modificadores/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Restaurante
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Modificadores]
  divergence_from_blueprint: "sem preço ao lado do modificador (a Blade não mostra; valor é decisão [W]); cadastro, edição, produtos e exclusão na tela clássica até o drawer"
related_runbook: memory/requisitos/Restaurante/RUNBOOK-modificadores.md
---

# Page Charter — /modules/modifiers (DRAFT)

> **Status:** draft. Thread `restaurante/playbook/02` (Modificadores). `GET /modules/modifiers` responde Inertia só com a
> flag `useV2RestauranteModificadores` ligada para o negócio; desligada, a Blade `restaurant.modifier_sets.index` segue.
> Vira `live` no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md). Alvo de design:
> `governance/design/targets/restaurante--modificadores--index.alvo.json` (thread A1).

## Mission

Mostrar os conjuntos de modificadores do negócio, com os modificadores e os produtos de cada um.

## Goals

- Lista dos conjuntos (conjunto · modificadores · produtos) com busca por qualquer um dos três.
- Mesmo predicado de acesso da Blade e do menu: `product.view` ou `product.create`.
- Abas de Configurações derivadas do menu (`ConfiguracoesSubNav`).
- Tela clássica (`?classico=1`) alcançável para quem pode cadastrar, editar ou excluir.

## Non-Goals

- ❌ Mostrar ou calcular preço de modificador (regra mestre de valor; a Blade não mostra).
- ❌ Cadastrar, editar, ligar produtos ou excluir nesta tela antes do drawer.
- ❌ Listar produto ligado de outro negócio, mesmo que o pivô o tenha.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só conjuntos do negócio da sessão; produtos ligados filtrados pelo negócio | `ModifierSetsController::conjuntosDoNegocio` |
| R2 | Com a flag desligada, ou com `?classico=1`, a rota devolve a Blade | `ModifierSetsController::index` (`FLAG_V2`) |
| R3 | Acesso com `product.view` ou `product.create` (#5432) | `ModifierSetsController::index` |

## Refs

RUNBOOK: [`RUNBOOK-modificadores.md`](../../../../../memory/requisitos/Restaurante/RUNBOOK-modificadores.md) · ADR 0093 · ADR 0104 · ADR 0358.
