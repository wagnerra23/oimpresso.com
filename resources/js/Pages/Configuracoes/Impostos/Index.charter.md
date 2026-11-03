---
id: resources-js-pages-configuracoes-impostos-index-charter
page: /tax-rates
component: resources/js/Pages/Configuracoes/Impostos/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx
owner: wagner
status: draft
last_validated: "2026-10-07"
parent_module: Configuracoes
related_adrs: [93, 104, 358]
related_us: [US-MWART-010]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Impostos]
  divergence_from_blueprint: "ações por menu (…) em vez de botões na linha; aviso de NF-e da Blade mantido"
related_runbook: memory/requisitos/Configuracoes/RUNBOOK-impostos.md
---

# Page Charter — /tax-rates (DRAFT)

> **Status:** draft. Thread `sistema/playbook/05`, tela 1 de 3. `GET /tax-rates` responde Inertia só com a flag
> `useV2ConfiguracoesImpostos` ligada para o negócio; desligada, a Blade `tax_rate/index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Manter as alíquotas que a venda aplica e os grupos de imposto que as somam.

## Goals

- Lista das alíquotas do negócio (percentual, "só em grupo", se compõe grupo) e dos grupos (percentual, composição).
- Cadastrar e editar alíquota num drawer, mandando o percentual em texto pt-BR sem separador de milhar.
- Cadastrar, editar e excluir grupo pelo `GroupTaxController`: a tela manda só os ids (`taxes[]`) e o servidor soma.
- Excluir alíquota pelo endpoint de sempre; a que compõe grupo avisa antes, porque o `destroy()` recusa.
- Aviso de configuração fiscal avançada quando o negócio tem NF-e Brasil configurada, como a Blade.
- Abas de Configurações (D1, [W] 2026-10-06), derivadas do `shell.menu`.

## Non-Goals

- ❌ Mudar o parser da alíquota: a tela manda texto pt-BR e o `num_uf` do `store`/`update` converte (regra mestre de valor).
- ❌ Calcular o percentual do grupo na tela: o drawer só MOSTRA a soma; quem grava é o `GroupTaxController` (UC-IMPOS-05).
  _(Até 2026-11-03 este item proibia editar grupos aqui, porque o controller aceitava alíquota de outro negócio — achado do
  `_saida-05`. O conserto `subImpostosDaEmpresa` está defendido por UC-GIMP-02..05.)_

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só alíquotas e grupos do negócio da sessão | `TaxRateController::impostosDoNegocio` |
| R2 | Com a flag desligada a rota devolve a Blade | `TaxRateController::index` (`FLAG_V2`) |
| R3 | Excluir só alcança alíquota do negócio e é recusado se ela compõe grupo | `destroy()` |
| R4 | Ações por permissão `tax_rate.create/.update/.delete` | `index()` (`pode`) |
| R5 | Grupo só soma alíquotas do negócio; alheia é recusada e nada grava | `GroupTaxController::subImpostosDaEmpresa` |

## Refs

RUNBOOK: [`RUNBOOK-impostos.md`](../../../../../memory/requisitos/Configuracoes/RUNBOOK-impostos.md) · paridade: [`impostos-parity.md`](../../../../../memory/requisitos/Configuracoes/impostos-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
