---
id: resources-js-pages-configuracoes-esquemasfatura-index-charter
page: /invoice-schemes
component: resources/js/Pages/Configuracoes/EsquemasFatura/Index.tsx
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
  derived_screens: [EsquemasFatura]
  divergence_from_blueprint: "layouts só listados, com link para o editor da Blade (InvoiceLayoutController fora do prefixo); próximo número só no sequencial"
related_runbook: memory/requisitos/Configuracoes/RUNBOOK-esquemas-fatura.md
---

# Page Charter — /invoice-schemes (DRAFT)

> **Status:** draft. Thread `sistema/playbook/05`, tela 3 de 3. `GET /invoice-schemes` responde Inertia só com a flag
> `useV2ConfiguracoesEsquemasFatura` ligada para o negócio; desligada, a Blade `invoice_scheme/index` segue. Vira `live`
> no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar como as notas de cada local são numeradas e com que layout saem.

## Goals

- Lista dos esquemas do negócio: padrão marcado, prefixo (com o ano no anual), tipo de numeração, emitidas e o próximo
  número sequencial pela mesma conta do `TransactionUtil`.
- Tornar padrão e excluir pelos endpoints de sempre; o padrão não se exclui.
- Layouts do negócio com os locais que usam cada um, e link para o editor de layout da Blade.
- Abas de Configurações (D1, [W] 2026-10-06), derivadas do `shell.menu`.

## Non-Goals

- ❌ Editar o contador de notas emitidas (`invoice_count`): é a venda que incrementa.
- ❌ Criar/editar layout aqui: é o `InvoiceLayoutController`, fora do prefixo.
- ❌ Corrigir a numeração aleatória que não limpa o número inicial (quirk do RUNBOOK §10, decisão [W]).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só esquemas, layouts e locais do negócio da sessão | `InvoiceSchemeController::faturaDoNegocio` |
| R2 | Com a flag desligada a rota devolve a Blade | `InvoiceSchemeController::index` (`FLAG_V2`) |
| R3 | Tornar padrão e excluir só alcançam o negócio; o padrão não se exclui | `setDefault()` · `destroy()` (#8979) |
| R4 | Sem `invoice_settings.access`, 403 | todas as ações |

## Refs

RUNBOOK: [`RUNBOOK-esquemas-fatura.md`](../../../../../memory/requisitos/Configuracoes/RUNBOOK-esquemas-fatura.md) · paridade: [`esquemas-fatura-parity.md`](../../../../../memory/requisitos/Configuracoes/esquemas-fatura-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
