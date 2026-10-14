---
id: modules-crm-pages-crm-chamadas-index-charter
page: /crm/call-log
component: Modules/Crm/Resources/js/Pages/Crm/Chamadas/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — /crm/call-log (DRAFT · registro de chamadas, atrás da flag)

> **Status:** draft. Nasce na thread Crm/12 (`CallLogController@index`, Blade → Inertia), atrás da
> flag `useV2CrmChamadas` (default OFF). Casos: [`Index.casos.md`](Index.casos.md) · contrato:
> `governance/design/contracts/crm-chamadas.contract.json` · RUNBOOK:
> `memory/requisitos/Crm/RUNBOOK-chamadas.md`. Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Mostrar ao time **as ligações registradas pelo app**: quando começaram e terminaram, quanto duraram,
de que tipo foram, com qual contato e por quem — e deixar o admin limpar registros em massa.

## Goals — Features (faz, nesta fase)

- Lista paginada (25): Hora de início, Hora de término, Duração da chamada, Tipo de chamada (pílula
  Recebida / Efetuada / Perdida), Nº do contato (+ nome no celular), Contato, Usuário, Registro criado por.
- Filtros da Blade: contato, registro criado por, intervalo de datas. Mais busca por contato ou número.
- Seleção de linhas + "Excluir selecionados" só para o admin, pela mesma rota da Blade.
- Escopo **igual ao da Blade**: a lista sai da mesma consulta do DataTables (`business_id` + "só os
  meus" para quem não tem `crm.view_all_call_log` + filtros).

## Non-Goals (nesta fase)

- ❌ Criar registro de chamada: chega pela API do app.
- ❌ Ligar `enable_crm_call_log` nem a flag em produção: as duas são decisão do [W] (achado B1).

## Anti-hooks

- ❌ A flag furar o portão de config: com `enable_crm_call_log` desligado a rota segue 403.
  Guarda: UC-CRMCHA-06.
- ❌ Decidir Inertia × DataTables só por `request()->ajax()` (§5 2026-09-08). Guarda: UC-CRMCHA-02.
- ❌ Mostrar "Excluir selecionados" a quem a Blade não mostra. Guarda: UC-CRMCHA-06.
- ❌ Montar consulta própria para a tela. Guardas: UC-CRMCHA-03 e 04.
