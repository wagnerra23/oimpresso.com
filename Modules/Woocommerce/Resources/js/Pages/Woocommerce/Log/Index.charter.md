---
id: modules-woocommerce-resources-js-pages-woocommerce-log-index-charter
page: /woocommerce/view-sync-log
component: Modules/Woocommerce/Resources/js/Pages/Woocommerce/Log/Index.tsx
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
  derived_screens: [Log]
  divergence_from_blueprint: "busca filtra só a página; situação por execução derivada dos erros registrados"
related_runbook: memory/requisitos/Woocommerce/RUNBOOK-log.md
---

# Page Charter — /woocommerce/view-sync-log (DRAFT)

> **Status:** draft. Thread `woocommerce/playbook/02`. `GET /woocommerce/view-sync-log` responde Inertia só com a flag
> `useV2WoocommercePainel` ligada para o negócio (a mesma do painel); desligada, ou com `?classico=1`, a Blade
> `woocommerce::woocommerce.sync_log` segue, e o DataTables dela continua no mesmo endpoint por AJAX. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md). Alvo de design:
> `governance/design/targets/woocommerce--log--index.alvo.json` (thread A1).

## Mission

Mostrar cada execução de sincronização com a loja e o que deu errado nela.

## Goals

- Lista paginada (25, mais recentes primeiro): data, tipo, operação, quem sincronizou, registros, situação.
- Linha expande com os erros (os do partial `log_details`) e os registros.
- Mesma query e mesma regra de tipos por permissão do DataTables (`tiposDeLogPermitidos`).

## Non-Goals

- ❌ Mostrar log de outro negócio.
- ❌ Mostrar tipo que a permissão do usuário não deixa ver.
- ❌ Sincronizar daqui (fica no painel / tela clássica).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só logs do negócio da sessão | `WoocommerceSyncLogRepository::paraDatatable` |
| R2 | Tipos pela permissão; superadmin vê todos; sem permissão nenhuma não filtra (de antes) | `tiposDeLogPermitidos` |
| R3 | Ramo React decidido por `X-Inertia` antes do `ajax()`; DataTables segue no AJAX sem `X-Inertia` | `viewSyncLog` |
| R4 | Com a flag desligada, ou com `?classico=1`, a rota devolve a Blade | `viewSyncLog` (`usaTelaNova`) |

## Refs

RUNBOOK: [`RUNBOOK-log.md`](../../../../../../../memory/requisitos/Woocommerce/RUNBOOK-log.md) · ADR 0093 · ADR 0104 · ADR 0358.
