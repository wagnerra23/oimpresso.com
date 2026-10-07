---
id: resources-js-pages-configuracoes-locais-index-charter
page: /business-location
component: resources/js/Pages/Configuracoes/Locais/Index.tsx
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
  derived_screens: [Locais]
  divergence_from_blueprint: "sem 'Configurações de recibo' (outra tela, location_settings); sem exportar seleção; sem excluir (o destroy() é vazio)"
related_runbook: memory/requisitos/Configuracoes/RUNBOOK-locais.md
---

# Page Charter — /business-location (DRAFT)

> **Status:** draft. Thread `sistema/playbook/04`, tela 3 de 3. `GET /business-location` responde Inertia só com a flag
> `useV2ConfiguracoesLocais` ligada para o negócio; desligada, a Blade `business_location/index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Manter os locais comerciais (filiais) do negócio — cada um com CNPJ, tabela de preço e fatura próprios.

## Goals

- Lista dos locais que o usuário pode ver, ativos primeiro, com referência, cidade, CNPJ, tabela de preço, esquema e layouts.
- Ativar e desativar pelo endpoint de sempre.

## Non-Goals

- ❌ Excluir local: o legado não tem (`destroy()` vazio); desativar é o caminho.
- ❌ Mostrar local que o usuário não pode acessar: sem `access_all_locations`, só os com permissão direta `location.<id>`.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só locais do negócio da sessão, no recorte do `permitted_locations()` | `BusinessLocationController::locaisDoNegocio` |
| R2 | Com a flag desligada a rota devolve a Blade | `BusinessLocationController::index` (`FLAG_V2`) |
| R3 | Ativar/desativar só alcança local do negócio | `activateDeactivateLocation()` |
| R4 | Sem `business_settings.access`, 403 | todas as ações |

## Refs

RUNBOOK: [`RUNBOOK-locais.md`](../../../../../memory/requisitos/Configuracoes/RUNBOOK-locais.md) · paridade: [`locais-parity.md`](../../../../../memory/requisitos/Configuracoes/locais-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
