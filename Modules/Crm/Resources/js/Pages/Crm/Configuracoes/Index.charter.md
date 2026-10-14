---
id: modules-crm-pages-crm-configuracoes-index-charter
page: /crm/settings
component: Modules/Crm/Resources/js/Pages/Crm/Configuracoes/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 2
---

# Page Charter — /crm/settings (DRAFT · configurações do CRM)

> **Status:** draft. Nasce na thread Crm/15 (`CrmSettingsController@index`, Blade → Inertia), atrás da
> flag `useV2CrmConfiguracoes` (default OFF). Vai a `live` com o cutover [W]. Casos:
> [`Index.casos.md`](Index.casos.md) · contrato: `governance/design/contracts/crm-config.contract.json`
> (derivado do alvo `crm--config--index`, A2 #9255) · RUNBOOK:
> `memory/requisitos/Crm/RUNBOOK-configuracoes.md`. Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Deixar o dono do negócio ligar a **solicitação de pedido pelo portal do contato** e escolher o
**prefixo** desses pedidos, sem sair do CRM.

## Goals — Features (faz, nesta fase)

- Checkbox "Ativar solicitação de pedido" (com o texto de ajuda do protótipo) e campo "Prefixo da
  solicitação de pedido", abertos com o que está gravado em `business.crm_settings`.
- "Atualizar" grava pelo MESMO `POST /crm/update-settings` da Blade; o resultado aparece no toast
  global (flash `status`).
- Mesma trava da Blade para abrir e gravar: `superadmin`, ou `Admin#` do negócio com `crm_module`.

## Non-Goals (nesta fase)

- ❌ Endpoint de gravação novo ou merge do JSON: a tela usa o `updateSettings` de hoje, que grava só
  as duas chaves (RUNBOOK §10).

## Anti-hooks

- ❌ Mandar `enable_order_request` desmarcado como `0`: o backend lê `has()`, então qualquer valor
  liga. O campo só vai quando marcado. Guarda: UC-CRMCFG-04.
- ❌ Aceitar `business_id` vindo do formulário. Guarda: UC-CRMCFG-03.
- ❌ Abrir a tela nova para quem não é `Admin#`. Guarda: UC-CRMCFG-02.
- ❌ Servir a tela nova com a flag desligada, ou tirar a saída `?classico=1`. Guarda: UC-CRMCFG-05.
