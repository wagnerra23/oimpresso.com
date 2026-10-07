---
id: resources-js-pages-usuarios-index-charter
page: /users
component: resources/js/Pages/Usuarios/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/usuarios-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-07"
parent_module: User
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/usuarios-page.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Usuarios]
  divergence_from_blueprint: "sem convite por e-mail, link de redefinição, último acesso, 2 etapas, ativar/desativar na lista, atividade e contagem de vendas/OS no nome (sem fonte no legado); cadastrar e editar abrem a Blade; cores por função e avatar neutros"
related_runbook: memory/requisitos/User/RUNBOOK-usuarios.md
---

# Page Charter — /users (DRAFT)

> **Status:** draft. Thread `sistema/playbook/01`. `GET /users` (`ManageUserController::index`) responde
> Inertia **só** com `X-Inertia` ou com a chave `mwart.sistema_usuarios_index` ligada para a empresa —
> ela nasce desligada e ligar é a F5, decisão [W]. A Blade `manage_user/*` segue como padrão.
> Vira `live` com a aprovação do screenshot por [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Ver quem acessa o ERP do negócio, com que função e se pode entrar, e chegar ao cadastro de cada um.

## Goals

- Lista dos usuários do negócio (sem os comissionados) com função, e-mail, situação e se tem login.
- Buscar por nome, usuário, e-mail ou função; filtrar por função e por situação.
- Detalhe em drawer; editar, ver a ficha e cadastrar levam às telas de sempre; excluir com confirmação.

## Non-Goals

- ❌ Formulário de cadastro/edição dentro da tela nesta migração: o `update()` zera login e contatos permitidos que não chegam no corpo.
- ❌ Mostrar dado que o legado não tem (último acesso, 2 etapas, convite, atividade, vendas/OS no nome).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só usuários do negócio da sessão, `user_type = user`, sem comissionados | `ManageUserController::usuariosDoNegocio` |
| R2 | Ver exige `user.view` ou `user.create`; cada ação exige a sua permissão (`pode`) | `index()` / `telaInertia()` |
| R3 | Excluir usa o `destroy()` de sempre, por AJAX; a própria conta não oferece excluir | `destroy()` / Page |
| R4 | Com a chave desligada, GET comum fica na Blade e AJAX sem `X-Inertia` fica na DataTable | `Mwart::telaReact` |

## UX Targets

- `/` foca a busca.
- Clicar na linha abre o detalhe; o menu da linha não abre o drawer junto.
- Excluir quem tem login pede para digitar o usuário.

## Automation Anti-hooks

- Não excluir sem confirmação digitada quando o usuário tem login.

## Refs

RUNBOOK: [`RUNBOOK-usuarios.md`](../../../../memory/requisitos/User/RUNBOOK-usuarios.md) · ADR 0093 · ADR 0104 · ADR 0358.
