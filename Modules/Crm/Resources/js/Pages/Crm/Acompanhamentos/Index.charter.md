---
id: modules-crm-pages-crm-acompanhamentos-index-charter
page: /crm/follow-ups
component: Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade.jsx
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 1
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

## Non-Goals (nesta fase)

- ❌ Escrever. Adicionar, recorrente, antecipado, editar, log e excluir seguem nos modais da tela
  Blade (`?classico=1`); os três botões da toolbar levam pra lá.
- ❌ Contagem por status/tipo no rodapé, densidade e drawer de detalhe do protótipo — pendentes
  registrados no `_saida-03`.

## Anti-hooks

- ❌ Decidir Inertia × DataTables só por `request()->ajax()`: o Inertia manda `X-Requested-With`
  junto do `X-Inertia` (§5 2026-09-08). Guarda: UC-CRMACO-02.
- ❌ Montar consulta própria para a tela nova: ela herda a do DataTables, para não divergir de
  escopo. Guardas: UC-CRMACO-03 (outro negócio) e UC-CRMACO-04 (só os meus).
