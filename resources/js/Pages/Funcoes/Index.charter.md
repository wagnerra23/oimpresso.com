---
id: resources-js-pages-funcoes-index-charter
page: /roles
component: resources/js/Pages/Funcoes/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/funcoes-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: User
related_adrs: [93, 104, 358]
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/funcoes-page.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Funcoes]
  divergence_from_blueprint: "lista em tabela, não cards; sem descrição e sem cor por função (sem fonte no banco); o editor (drawer 760) mostra os blocos e rótulos da Blade role/edit, não o catálogo resumido de funcoes-perms.jsx"
related_runbook: memory/requisitos/User/RUNBOOK-funcoes.md
---

# Page Charter — /roles (DRAFT)

> **Status:** draft. Thread `sistema/playbook/02`. `GET /roles` responde Inertia só com a flag `useV2SistemaFuncoes`
> ligada para o negócio; desligada, a Blade `role/index` segue. Vira `live` no cutover, decisão [W].
> Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Ver as funções do negócio, quantos usuários estão em cada uma, e chegar ao que cada uma pode fazer.

## Goals

- Lista das funções do negócio com nº de usuários e a marca de função padrão.
- Excluir com confirmação, pelo endpoint de sempre, mostrando a recusa "em uso".
- Cadastrar e editar num drawer 760 com os mesmos controles da Blade, gravando pelo `store`/`update` de sempre.

## Non-Goals

- ❌ Mudar o que `store`/`update`/`destroy` gravam ou as permissões `roles.*`.
- ❌ Catálogo de permissões escrito à mão no React ou num 2º arquivo: o drawer desenha o que a Blade renderiza.
- ❌ Tratar aqui o grupo de preço de outro negócio aceito em `spg_permissions[]` — decisão [W] (achado 2 da _saida-02).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só funções do negócio da sessão; usuários contados só sobre elas | `RoleController::funcoesDoNegocio` |
| R2 | Com a flag desligada a rota devolve a Blade | `RoleController::index` (`FLAG_V2`) |
| R3 | Função padrão (exceto `Cashier`) sem editar nem excluir | `funcoesDoNegocio` (`editavel`) |
| R4 | Sem `roles.view`, 403; ações conforme `roles.create|update|delete` | `index` (`pode`) |
| R5 | Editor só de função do negócio da sessão (404 fora dele); padrão não abre | `RoleController::editorDaFuncao` |
| R6 | O corpo salvo é o do `<form>` da Blade (`permissions[]`, `spg_permissions[]`, `radio_option[]`, `is_service_staff`) | `FuncaoDrawer::corpoDoFormulario` |

## Refs

RUNBOOK: [`RUNBOOK-funcoes.md`](../../../../memory/requisitos/User/RUNBOOK-funcoes.md) · paridade: [`funcoes-parity.md`](../../../../memory/requisitos/User/funcoes-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
