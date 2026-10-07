---
id: resources-js-pages-configuracoes-impressoras-index-charter
page: /printers
component: resources/js/Pages/Configuracoes/Impressoras/Index.tsx
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
  derived_screens: [Impressoras]
  divergence_from_blueprint: "sem 'Testar' (não há endpoint de cupom de teste); perfil com os rótulos do enum Printer::capability_profiles()"
related_runbook: memory/requisitos/Configuracoes/RUNBOOK-impressoras.md
---

# Page Charter — /printers (DRAFT)

> **Status:** draft. Thread `sistema/playbook/04`, tela 1 de 3. `GET /printers` responde Inertia só com a flag
> `useV2ConfiguracoesImpressoras` ligada para o negócio; desligada, a Blade `printer/index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Manter as impressoras de cupom do negócio — como cada caixa se conecta a uma delas.

## Goals

- Lista das impressoras do negócio com conexão, perfil, caracteres por linha e endereço (IP:porta ou caminho).
- Excluir com confirmação, pelo endpoint de sempre.

## Non-Goals

- ❌ Imprimir cupom de teste: o legado não tem endpoint.
- ❌ Mudar o que `store`/`update`/`destroy` gravam ou a permissão `access_printers`.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só impressoras do negócio da sessão | `PrinterController::impressorasDoNegocio` |
| R2 | Com a flag desligada a rota devolve a Blade | `PrinterController::index` (`FLAG_V2`) |
| R3 | Excluir só alcança impressora do negócio | `destroy()` |
| R4 | Sem `access_printers`, 403 | todas as ações |

## Refs

RUNBOOK: [`RUNBOOK-impressoras.md`](../../../../../memory/requisitos/Configuracoes/RUNBOOK-impressoras.md) · paridade: [`impressoras-parity.md`](../../../../../memory/requisitos/Configuracoes/impressoras-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
