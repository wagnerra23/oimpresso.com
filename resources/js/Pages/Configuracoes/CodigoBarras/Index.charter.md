---
id: resources-js-pages-configuracoes-codigobarras-index-charter
page: /barcodes
component: resources/js/Pages/Configuracoes/CodigoBarras/Index.tsx
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
  derived_screens: [CodigoBarras]
  divergence_from_blueprint: "medidas em polegada (o protótipo pede mm; decisão [W]); sem 'Imprimir prova' (não há endpoint); a padrão não mostra Excluir"
related_runbook: memory/requisitos/Configuracoes/RUNBOOK-codigo-barras.md
---

# Page Charter — /barcodes (DRAFT)

> **Status:** draft. Thread `sistema/playbook/04`, tela 2 de 3. `GET /barcodes` responde Inertia só com a flag
> `useV2ConfiguracoesCodigoBarras` ligada para o negócio; desligada, a Blade `barcode/index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Manter as configurações de etiqueta (folha ou rolo) que a impressão de código de barras usa.

## Goals

- Lista das configurações do negócio, com a padrão marcada, o tipo de papel e quantas etiquetas cabem na folha.
- Tornar padrão e excluir pelos endpoints de sempre; a padrão não se exclui.

## Non-Goals

- ❌ Converter as medidas para mm: o banco e a impressão são em polegada (RUNBOOK §10, decisão [W]).
- ❌ Mostrar ou editar os modelos globais (`business_id` NULL): são de todos os negócios.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só configurações do negócio da sessão, sem as globais | `BarcodeController::etiquetasDoNegocio` |
| R2 | Com a flag desligada a rota devolve a Blade | `BarcodeController::index` (`FLAG_V2`) |
| R3 | Excluir e tornar padrão só alcançam o negócio; a padrão não se exclui | `destroy()` · `setDefault()` |
| R4 | Sem `barcode_settings.access`, 403 | todas as ações |

## Refs

RUNBOOK: [`RUNBOOK-codigo-barras.md`](../../../../../memory/requisitos/Configuracoes/RUNBOOK-codigo-barras.md) · paridade: [`codigo-barras-parity.md`](../../../../../memory/requisitos/Configuracoes/codigo-barras-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
