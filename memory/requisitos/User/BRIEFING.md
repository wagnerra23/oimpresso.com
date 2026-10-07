---
id: requisitos-user-briefing
module: User
status: parcial
status_nota: "telas de usuário no núcleo (ManageUserController, UserController), sem módulo próprio"
updated_at: "2026-10-07"
owner: W
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
lifecycle: ativo
---

# BRIEFING — User (usuários do ERP)

> Porta do núcleo de usuários. Não há módulo próprio: o código mora em `app/`.

- **Usuários** (`/users`, `App\Http\Controllers\ManageUserController`): lista, cadastro, edição, ficha e
  exclusão dos usuários do negócio. Migração Blade → Inertia em curso pela thread `sistema/playbook/01`,
  passo a passo em [`RUNBOOK-usuarios.md`](RUNBOOK-usuarios.md). A tela React fica atrás da chave
  `mwart.sistema_usuarios_index`, desligada.
- **Meu perfil** (`/perfil`, `App\Http\Controllers\UserController`): já em React. A paridade campo a
  campo com a Blade está em [`perfil-parity.md`](perfil-parity.md).
- **Comissionados** são usuários com `is_cmmsn_agnt = 1` e têm tela própria (`Comissionados/Index`).
- **Tier 0:** toda consulta filtra pelo `business_id` da sessão. Função, contato e local escolhidos no
  formulário têm de ser do mesmo negócio ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
