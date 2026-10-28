---
id: modules-crm-pages-crm-portal-painel-index-charter
page: /contact/contact-dashboard
component: Modules/Crm/Resources/js/Pages/Crm/Portal/Painel/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-portal.jsx
runbook: memory/requisitos/Crm/RUNBOOK-portal-painel.md
owner: wagner
status: draft
last_validated: "2026-10-27"
parent_module: Crm
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
---

# Page Charter — /contact/contact-dashboard (DRAFT · Início do portal do contato)

> **Status:** draft, atrás da flag `useV2CrmPortalPainel` (OFF). Nasce na thread Crm/18
> (`DashboardController@index`, Blade → Inertia). Casos: [`Index.casos.md`](Index.casos.md) ·
> plano: `memory/requisitos/Crm/RUNBOOK-portal-painel.md` · lei do portal inteiro:
> `prototipo-ui/cowork/Wagner/handoff-crm/Portal.charter.md`.
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar ao contato logado quanto ele deve e quanto já pagou — só dele — e os atalhos para o que ele faz em seguida.

## Goals — Features (faz, nesta fase)

- Saudação + nome do contato e da loja.
- Bloco `portal-saldo` (cliente): "Você tem a pagar", total em vendas e o já pago.
- KPIs de compras (fornecedor) e saldo de abertura (quando existe).
- Atalhos com rota para este contato: pedir de novo e acompanhar pedidos (com `enable_order_request`), agendar retirada (com o módulo `booking`).
- Casca do contato (D6): cabeçalho, abas no topo, barra inferior no celular, sem a sidebar do ERP.

## Non-Goals (nesta fase)

- ❌ Aviso de OS pronta ou com orçamento aguardando (UC-CRMPO-17): depende da derivação de fase da OS que a thread 25 cria.
- ❌ Aba Assistência na navegação: a rota nasce na thread 25.
- ❌ Escrever. O Início só lê.
- ❌ Não mostra custo, margem, nem dado de outro contato (lei do portal).

## UX Targets

- Alvos ≥ 44px; legível sem zoom em 390px (lei do portal).

## Automation Anti-hooks

- ❌ Toda query escopada por `contact_id` **e** `business_id` (lei do portal).
