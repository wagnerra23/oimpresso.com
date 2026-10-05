---
id: resources-js-pages-cliente-grupos-index-charter
page: /customer-group
component: resources/js/Pages/Cliente/Grupos/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/cliente-grupos.jsx
owner: wagner
status: draft
last_validated: "2026-10-05"
parent_module: Cliente
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/cliente-grupos.jsx"
  blueprint_screenshot_approval: "pendente [W2]"
  derived_screens: [Grupos]
  divergence_from_blueprint: "o percentual é ajuste com sinal (protótipo diz Desconto e só aceita dígitos); sem coluna-ação Ver cadastros nem grupo padrão"
related_runbook: memory/requisitos/Cliente/RUNBOOK-grupos.md
---

# Page Charter — /customer-group (DRAFT)

> **Status:** draft. Thread `cliente/playbook/03` (D2 de 2026-10-01: tela própria `Cliente/Grupos`). `GET /customer-group`
> (`CustomerGroupController::index`) responde Inertia; as Blades `resources/views/customer_group/*` ficam no repo
> até o [W2] aprovar o screenshot. Vira `live` com essa aprovação.
> Casos: [`Index.casos.md`](./Index.casos.md) · Contrato: `governance/design/contracts/cliente-grupos.contract.json`.

## Mission

Manter os grupos de cliente do negócio — o grupo diz como o preço do cliente é calculado na venda: um percentual
sobre o preço de venda **ou** uma tabela de preço própria.

## Goals

- Lista dos grupos do negócio com cálculo, ajuste, tabela e quantos cadastros usam cada um.
- Criar, editar e excluir em diálogo, sem sair da tela, gravando pelos endpoints de sempre.

## Non-Goals

- ❌ Mudar o parser do valor: o percentual vai como texto pt-BR e o `num_uf` do `store/update` converte, como na Blade.
- ❌ Tratar o percentual como desconto: positivo aumenta e negativo diminui o preço de venda.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só grupos do negócio da sessão; a contagem de cadastros também é do negócio | `CustomerGroupController::gruposDoNegocio` |
| R2 | A tabela de preço do grupo tem de ser do negócio da sessão | `CustomerGroupController::tabelaDoNegocio` |
| R3 | Ações seguem as permissões `customer.create/update/delete`; ver exige `customer.view` | `index()` (`pode`) |

## UX Targets

- `/` foca a busca; `n` abre "Novo grupo".
- Excluir confirma e diz quantos cadastros usam o grupo.

## Automation Anti-hooks

- Não gravar o percentual como número JS no corpo: o texto pt-BR é o contrato do endpoint.

## Refs

RUNBOOK: [`RUNBOOK-grupos.md`](../../../../../memory/requisitos/Cliente/RUNBOOK-grupos.md) · ADR 0093 · ADR 0104 · ADR 0358.
