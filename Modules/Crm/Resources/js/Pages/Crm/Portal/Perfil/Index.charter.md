---
id: modules-crm-pages-crm-portal-perfil-index-charter
page: /contact/contact-profile
component: Modules/Crm/Resources/js/Pages/Crm/Portal/Perfil/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-portal.jsx
runbook: memory/requisitos/Crm/RUNBOOK-portal-perfil.md
owner: wagner
status: draft
last_validated: "2026-10-27"
parent_module: Crm
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
---

# Page Charter — /contact/contact-profile (DRAFT · Perfil do portal do contato)

> **Status:** draft, atrás da flag `useV2CrmPortalPerfil` (OFF). Nasce na thread Crm/19
> (`ManageProfileController`, Blade → Inertia). Casos: [`Index.casos.md`](Index.casos.md) ·
> plano: `memory/requisitos/Crm/RUNBOOK-portal-perfil.md` · lei do portal inteiro:
> `prototipo-ui/cowork/Wagner/handoff-crm/Portal.charter.md`.
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Deixar o contato corrigir os próprios dados de contato e trocar a senha, sem tocar no cadastro fiscal.

## Goals — Features (faz, nesta fase)

- "Meus dados": nome do contato e CNPJ/CPF só leitura; pessoa de contato, telefone, e-mail e endereço editáveis (lei do portal).
- "Trocar senha": atual, nova e confirmação, nas rotas POST de hoje.

## Non-Goals (nesta fase)

- ❌ Não altera cadastro fiscal (nome, CNPJ/CPF, tipo) pelo portal (lei do portal).
- ❌ Idioma e foto do usuário: ficam na Blade (`?classico=1`); o protótipo não os tem.
- ❌ Cidade, UF e CEP: o endereço é um campo só, como no protótipo.

## UX Targets

- Alvos ≥ 44px; legível sem zoom em 390px (lei do portal).

## Automation Anti-hooks

- ❌ Toda query escopada por `contact_id` **e** `business_id` (lei do portal).
