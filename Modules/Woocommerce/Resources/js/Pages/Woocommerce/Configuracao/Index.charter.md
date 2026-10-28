---
id: modules-woocommerce-resources-js-pages-woocommerce-configuracao-index-charter
page: /woocommerce/api-settings
component: Modules/Woocommerce/Resources/js/Pages/Woocommerce/Configuracao/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/integra-extras.jsx
owner: wagner
status: draft
last_validated: "2026-10-14"
parent_module: Woocommerce
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/integra-extras.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Configuracao]
  divergence_from_blueprint: "aceita troca das chaves (só administrador); sincronização automática só liga/desliga; sem testar conexão"
related_runbook: memory/requisitos/Woocommerce/RUNBOOK-configuracao.md
---

# Page Charter — /woocommerce/api-settings (DRAFT)

> **Status:** draft. Thread `woocommerce/playbook/02`. `GET /woocommerce/api-settings` responde Inertia só com a flag
> `useV2WoocommercePainel` ligada para o negócio (a mesma do painel); desligada, ou com `?classico=1`, a Blade
> `woocommerce::woocommerce.api_settings` segue. Vira `live` no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).
> Alvo de design: `governance/design/targets/woocommerce--api--index.alvo.json` (thread A1).

## Mission

Trocar a configuração da loja sem que as chaves dela cheguem à página.

## Goals

- Mesmos campos da Blade: loja (endereço, local do estoque, sincronização automática), chaves, produtos (classe de
  imposto, preço com/sem imposto, grupo de preço, descrição, campos enviados ao criar e ao atualizar, estoque),
  equivalência de status dos pedidos e segredos dos webhooks com o endereço de entrega.
- Salvar no mesmo `POST /woocommerce/update-api-settings`.

## Non-Goals

- ❌ Receber o valor de qualquer segredo (consumer key, consumer secret, 4 de webhook) — nem parte dele.
- ❌ Apagar segredo pela tela: campo vazio mantém o salvo (`manter_segredos`).
- ❌ Chamar a API da loja (testar conexão do protótipo).
- ❌ Mapa de impostos e redefinir produtos/categorias — ficam no painel (`/woocommerce`).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Segredos viram booleanos; o resto da configuração vai como está salvo | `WoocommerceController::propsDaConfiguracao` |
| R2 | Com `manter_segredos=1`, segredo vazio mantém o salvo; sem ele (Blade), apaga | `updateSettings` · `manterSegredosVazios` |
| R3 | Locais e grupos de preço só do negócio da sessão; salvar grava só nele | `propsDaConfiguracao` · `updateSettings` |
| R4 | Com a flag desligada, ou com `?classico=1`, a rota devolve a Blade | `apiSettings` (`usaTelaNova`) |
| R5 | Acesso: `woocommerce.access_woocommerce_api_settings` | `WoocommerceAuthorizationService::ensureAcao` |

## Divergência da Blade

- Os campos "descrição" ao atualizar: a Blade marca o checkbox pela lista de **criação**; a tela usa a de atualização.

## Refs

RUNBOOK: [`RUNBOOK-configuracao.md`](../../../../../../../memory/requisitos/Woocommerce/RUNBOOK-configuracao.md) · ADR 0093 · ADR 0104 · ADR 0358.
