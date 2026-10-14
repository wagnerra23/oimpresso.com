---
id: modules-crm-pages-crm-logins-index-charter
page: /crm/all-contacts-login
component: Modules/Crm/Resources/js/Pages/Crm/Logins/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Crm
related_adrs: [93, 104, 358]
tier: B
charter_version: 1
---

# Page Charter — /crm/all-contacts-login (DRAFT · login de contatos)

> **Status:** draft, atrás da flag `useV2CrmLogins` (OFF). Nasce na thread Crm/16
> (`ContactLoginController@allContactsLoginList`, Blade → Inertia; portal do contato = D3).
> Casos: [`Index.casos.md`](Index.casos.md) · contrato: `governance/design/contracts/crm-logins.contract.json`
> (derivado do alvo `crm--logins--index` da A2) · plano: `memory/requisitos/Crm/RUNBOOK-logins.md`.
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar quem do cliente entra no **portal do contato** e com qual usuário: contato, usuário, nome,
e-mail, departamento e designação.

## Goals — Features (faz, nesta fase)

- Lista paginada (25) com as 7 colunas do protótipo (Ação, Contato, Nome de usuário, Nome, E-mail,
  Departamento, Designação).
- Filtro "Contato" (a lista `contactsDropdownForLogin` da Blade) e busca por nome ou usuário.
- Escopo **igual ao da Blade**: usuário do negócio da sessão com contato vinculado; a tela nova
  exige também o contato no mesmo negócio.

## Non-Goals (nesta fase)

- ❌ Escrever. Adicionar, editar e excluir login seguem nos modais da Blade (`?classico=1`).
- ❌ "Redefinir senha" do protótipo: o ERP não envia link de senha a contato.
- ❌ Comissões (`/crm/commissions`): outra tela, sem alvo medido.

## Anti-hooks

- ❌ Mandar `password` ou `remember_token` às props, mesmo mascarados. Guarda: UC-CRMLOG-03.
- ❌ Listar usuário da equipe (sem `crm_contact_id`) ou de outro negócio. Guarda: UC-CRMLOG-02.
