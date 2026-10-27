---
id: modules-woocommerce-resources-js-pages-woocommerce-painel-index-charter
page: /woocommerce
component: Modules/Woocommerce/Resources/js/Pages/Woocommerce/Painel/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/integra-extras.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Woocommerce
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/integra-extras.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Painel]
  divergence_from_blueprint: "sem preço, estoque enviado e última sincronização por produto; sem aba Pedidos; sincronizar e mapear impostos na tela clássica"
related_runbook: memory/requisitos/Woocommerce/RUNBOOK-painel.md
---

# Page Charter — /woocommerce (DRAFT)

> **Status:** draft. Thread `woocommerce/playbook/01` (Painel). `GET /woocommerce` responde Inertia só com a flag
> `useV2WoocommercePainel` ligada para o negócio; desligada, a Blade `woocommerce::woocommerce.index` segue. Vira `live`
> no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md). Alvo de design:
> `governance/design/targets/woocommerce--index.alvo.json` (thread A1).

## Mission

Mostrar o que falta sincronizar com a loja e a situação de cada produto, sem chamar a loja.

## Goals

- KPIs: produtos pendentes de vínculo, alterados desde a última sincronização, categorias a sincronizar, produtos na fila.
- Lista paginada (50) dos produtos simples e variáveis do local configurado, com SKU, ID na loja e situação.
- Contagens pela mesma função dos alertas da Blade (`contagensDoPainel`), e situação pelos mesmos predicados.
- Abas para Log de sincronização e Configuração (esta só para quem pode configurar).
- Sincronizar categorias, produtos (só novos ou todos) e pedidos, e reiniciar categorias ou produtos sincronizados,
  pelos mesmos endpoints JSON da Blade, cada bloco com a permissão do bloco da Blade (F3-2, 2026-10-27).
- Mapear impostos (taxa do ERP → taxa da loja) num painel lateral, gravando pelo `map-taxrates` de sempre (F3-2, 2026-10-27).

## Non-Goals

- ❌ Chamar a API do WooCommerce ao abrir a tela.
- ❌ Receber consumer key, secret ou qualquer campo de `woocommerce_api_settings` além do booleano `configurado`.
- ❌ Mostrar preço ou estoque enviado (regra mestre de valor e estoque — decisão [W]).
- ❌ Ler as taxas da loja ao abrir a tela: só quando o mapa de impostos abre (`/woocommerce/tax-rates-map`). (Sincronizar,
  reiniciar e mapear impostos entraram na F3-2, 2026-10-27.)

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só produtos e categorias do negócio da sessão | `contagensDoPainel` · `produtosDoPainel` |
| R2 | Com a flag desligada, ou com `?classico=1`, a rota devolve a Blade | `WoocommerceController::index` (`FLAG_V2`) |
| R3 | Uma fonte para os alertas da Blade e os KPIs da tela | `contagensDoPainel` |
| R4 | Acesso: `superadmin` ou assinatura `woocommerce_module` | `WoocommerceAuthorizationService::ensureModulo` |

## Refs

RUNBOOK: [`RUNBOOK-painel.md`](../../../../../../../memory/requisitos/Woocommerce/RUNBOOK-painel.md) · ADR 0093 · ADR 0104 · ADR 0358.
