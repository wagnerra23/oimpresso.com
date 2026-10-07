---
id: resources-js-pages-comissionados-index-charter
page: /sales-commission-agents
component: resources/js/Pages/Comissionados/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/comissionados-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-06"
parent_module: Comissao
related_adrs: [93, 104, 151, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/comissionados-page.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Comissionados]
  divergence_from_blueprint: "sem KPIs, período, meta, pagamento e regra por faixa/margem (sem fonte no legado; comissoes/02 e ADR 0151); Remover em vez de Excluir; avatar neutro"
related_runbook: memory/requisitos/Comissao/RUNBOOK-comissionados.md
---

# Page Charter — /sales-commission-agents (DRAFT)

> **Status:** draft. Thread `sistema/playbook/03` (= `comissoes/playbook/01`). `GET /sales-commission-agents`
> (`SalesCommissionAgentController::index`) responde Inertia; as Blades `resources/views/sales_commission_agent/*`
> ficam no repo até o cutover. Vira `live` com a aprovação do screenshot por [W].
> Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Manter quem recebe comissão sobre a venda no negócio — nome, contato e o percentual de cada um.

## Goals

- Lista dos comissionados do negócio com contato, percentual e quantas vendas apontam para cada um.
- Cadastrar, editar e remover sem sair da tela, gravando pelos endpoints de sempre.

## Non-Goals

- ❌ Mudar o parser do valor: o percentual vai como texto pt-BR e o `num_uf` do `store/update` converte, como na Blade.
- ❌ Apurar comissão, período, meta ou pagamento nesta tela (relatório = `comissoes/02`; regras novas = ADR 0151).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só comissionados do negócio da sessão; a contagem de vendas também é do negócio | `SalesCommissionAgentController::agentesDoNegocio` |
| R2 | Editar só alcança comissionado do negócio da sessão | `edit()` / `update()` |
| R3 | Remover desmarca o papel; com venda vinculada é recusado (422) | `destroy()` |
| R4 | Ver exige `commission_agent.view` ou `.manage`; ações exigem `.manage` | `index()` (`pode`) |

## UX Targets

- `/` foca a busca; `n` abre "Novo comissionado".
- Remover avisa antes do clique quando há venda vinculada, com o número.

## Automation Anti-hooks

- Não gravar o percentual como número JS no corpo: o texto pt-BR é o contrato do endpoint.

## Refs

RUNBOOK: [`RUNBOOK-comissionados.md`](../../../../memory/requisitos/Comissao/RUNBOOK-comissionados.md) · ADR 0093 · ADR 0104 · ADR 0151 · ADR 0358.
