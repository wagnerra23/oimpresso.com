---
id: modules-crm-pages-crm-painel-index-charter
page: /crm/dashboard
component: Modules/Crm/Resources/js/Pages/Crm/Painel/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade.jsx
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — /crm/dashboard (DRAFT · painel do CRM, PT-04)

> **Status:** draft. Nasce na thread Crm/04 (`CrmDashboardController@index`, Blade → Inertia).
> Vai a `live` com o sinal de produção. Casos: [`Index.casos.md`](Index.casos.md) · contrato:
> `governance/design/contracts/crm-painel.contract.json` (derivado na thread 01).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Dar ao vendedor o **quadro do dia** (acompanhamentos de hoje, meus leads, minhas conversões,
minhas chamadas) e ao Admin o **quadro do negócio** (clientes, leads, fontes, estágios de vida,
aniversários, acompanhamentos por usuário, conversões e chamadas de todos). É o PT-04 Dashboard.

## Goals — Features (faz, nesta fase)

- KPIs e seções da `TelaPainel` do protótipo, na ordem do contrato `crm-painel`.
- Mesmo gate da Blade, seção a seção: acompanhamentos por `crm.access_all_schedule` /
  `crm.access_own_schedule`; leads por `crm.access_all_leads` / `crm.access_own_leads`;
  chamadas pela config `constants.enable_crm_call_log`; quadro do negócio só para `Admin#<biz>`.
- Números caros em `Inertia::defer` (`pessoal`, `negocio`).
- "Enviar desejos": marca os aniversariantes e abre a criação de campanha com os contatos.

## Non-Goals (nesta fase)

- ❌ Filtro de intervalo e de categoria em "Acompanhamentos por usuário" (a Blade tem, via
  `ReportController`): a tabela nova mostra o total geral. Pendente registrado no `_saida-04`.
- ❌ Detalhe das conversões por usuário (modal da Blade): segue em `?classico=1`.
- ❌ Portal do contato (`DashboardController`, `/contact/contact-dashboard`): é cliente-facing
  (D3) e o Cowork ainda não o fatiou nem derivou contrato.

## Anti-hooks

- ❌ Agregado sem `business_id`. Toda contagem do painel filtra pelo negócio, inclusive as do
  usuário (conversões, acompanhamentos). Guardas: UC-CRMPAI-02 e UC-CRMPAI-03.
- ❌ Mandar o quadro do negócio para quem não é Admin e só esconder na tela. Guarda: UC-CRMPAI-04.
