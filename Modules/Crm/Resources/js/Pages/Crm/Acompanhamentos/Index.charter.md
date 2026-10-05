---
id: modules-crm-pages-crm-acompanhamentos-index-charter
page: /crm/follow-ups
component: Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade.jsx
owner: wagner
status: draft
last_validated: "2026-10-05"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 2
---

# Page Charter — /crm/follow-ups (DRAFT · lista de acompanhamentos)

> **Status:** draft. Nasce na thread Crm/03 (`ScheduleController@index`, Blade → Inertia). Vai a
> `live` com o sinal de produção. Casos: [`Index.casos.md`](Index.casos.md) · contrato:
> `governance/design/contracts/crm-acompanhamentos.contract.json` (derivado na thread 01).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar ao time comercial **o que está agendado com quem**: ligações, encontros, SMS e e-mails com
clientes e leads, com o responsável e o status. Termo na UI: "acompanhamentos", nunca "follow-ups"
(ficha da thread 03).

## Goals — Features (faz, nesta fase)

- Lista paginada (25) com Contato, Início, Fim, Status, Tipo, Categoria, Atribuído a, Descrição,
  Título, Adicionado por e Adicionado em — as colunas da `TelaAcompanhamentos` do protótipo.
- Filtros da tela Blade, com os mesmos parâmetros do backend: contato, atribuído, status, tipo,
  intervalo de datas, acompanhamento por, categoria. Mais busca por título ou contato.
- Abas "Acompanhamentos" e "Acompanhamento recorrente" (`is_recursive`).
- Escopo de visibilidade **igual ao da Blade**: a Inertia recebe a mesma consulta do DataTables
  (`business_id` + filtros + "só os atribuídos a mim" para quem tem só `crm.access_own_schedule`).
- Escrita (thread Crm/07, PR-a): "Adicionar" e a coluna "Ação" (Editar, Excluir) abrem o modal e
  a confirmação aqui, gravando pelas MESMAS rotas da Blade (`store`/`update`/`destroy`).
  Guardas: UC-CRMACO-08..13.
- Escrita (thread Crm/07, PR-b): "Recorrente" abre o modal de recorrente — adicionar, e na aba
  recorrente o "Editar" — gravando por `store`/`update` do `ScheduleController`, os mesmos da Blade.
  Guardas: UC-CRMACO-14 e 15.

## Non-Goals (nesta fase)

- ❌ Acompanhamento antecipado nesta fase: segue na tela Blade (`?classico=1`). Ele monta os grupos
  de clientes/faturas por `getFollowUpGroups`, que devolve HTML; trazê-lo exige um formato JSON
  daquela rota (PR-c da thread 07).
- ❌ Registro (log) de acompanhamento: segue na Blade até o PR seguinte da thread 07 (o
  "Adicionar registro"); ver os registros fica com o drawer de detalhe, porque
  `ScheduleLogController@index` devolve HTML.
- ❌ Contagem por status/tipo no rodapé, densidade e drawer de detalhe do protótipo — pendentes
  registrados no `_saida-03`.

## Anti-hooks

- ❌ Decidir Inertia × DataTables só por `request()->ajax()`: o Inertia manda `X-Requested-With`
  junto do `X-Inertia` (§5 2026-09-08). Guarda: UC-CRMACO-02.
- ❌ Montar consulta própria para a tela nova: ela herda a do DataTables, para não divergir de
  escopo. Guardas: UC-CRMACO-03 (outro negócio) e UC-CRMACO-04 (só os meus).
- ❌ Endpoint de gravação próprio da tela nova: o modal usa `store`/`update`/`destroy` do
  `ScheduleController`, os mesmos da Blade. Guardas: UC-CRMACO-08, 09 e 11.
- ❌ Aceitar `business_id`/`created_by` ou contato de outro negócio vindos do formulário.
  Guardas: UC-CRMACO-09, 10, 12 e 15.
