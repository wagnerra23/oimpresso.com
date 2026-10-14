---
id: modules-crm-pages-crm-campanhas-index-charter
page: /crm/campaigns
component: Modules/Crm/Resources/js/Pages/Crm/Campanhas/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — /crm/campaigns (DRAFT · lista de campanhas + detalhe em drawer, atrás da flag)

> **Status:** draft. Nasce na thread Crm/11 (`CampaignController@index`, Blade → Inertia), atrás da
> flag `useV2CrmCampanhas` (default OFF). Casos: [`Index.casos.md`](Index.casos.md) · contrato:
> `governance/design/contracts/crm-campanhas.contract.json` · RUNBOOK:
> `memory/requisitos/Crm/RUNBOOK-campanhas.md`. Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Mostrar ao time comercial **quais campanhas de SMS e e-mail existem**, para quantos contatos, quem
criou e se já foram enviadas. Um clique na linha abre o detalhe num drawer, sem sair da lista.

## Goals — Features (faz, nesta fase)

- Lista paginada (25): Nome da campanha (com selo "Enviada"), Tipo de campanha, Contatos, Criado por,
  Criado em — as colunas da `Campanhas()` do protótipo.
- Filtro de tipo (o único da Blade) + busca por nome. Densidade Confortável / Compacto.
- Drawer (`?campanha=ID`): assunto, contatos, criada por/em, situação de envio e o corpo como texto.
- Escopo **igual ao da Blade**: a lista e o drawer saem da mesma consulta do DataTables
  (`business_id` + "só as minhas" para quem tem só `crm.access_own_campaigns` + tipo).

## Non-Goals (nesta fase)

- ❌ Criar, editar, excluir e **enviar notificação**: Adicionar e Editar levam às páginas da Blade;
  excluir e enviar ficam na tela clássica (`?classico=1`).
- ❌ Ligar a flag em produção: é cutover do [W] (RUNBOOK-campanhas §3, F5).

## Anti-hooks

- ❌ Disparar `send-campaign-notification` desta tela ou de qualquer teste dela. Guarda: Non-Goal
  acima + UC-CRMCAM-07 (o envio mora na Blade).
- ❌ Decidir Inertia × DataTables só por `request()->ajax()` (§5 2026-09-08). Guarda: UC-CRMCAM-02.
- ❌ Renderizar o corpo como HTML (`dangerouslySetInnerHTML`): a Blade imprime cru, esta tela não.
  Guarda: UC-CRMCAM-06.
- ❌ Montar consulta própria para a tela ou o drawer. Guardas: UC-CRMCAM-03, 04 e 06.
