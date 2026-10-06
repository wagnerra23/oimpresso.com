---
id: modules-crm-pages-crm-leads-index-charter
page: /crm/leads
component: Modules/Crm/Resources/js/Pages/Crm/Leads/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade.jsx
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — /crm/leads (DRAFT · lista de leads + detalhe em drawer)

> **Status:** draft. Nasce na thread Crm/02 (`LeadController@index`, Blade → Inertia). Vai a
> `live` com o sinal de produção. Casos: [`Index.casos.md`](Index.casos.md) · contrato:
> `governance/design/contracts/crm-leads.contract.json` (derivado na thread 01).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar ao time comercial **quem está em prospecção**: de onde o lead veio, em que estágio de
vida está, quem acompanha e quando foi o último e o próximo acompanhamento. Um clique na linha
abre o detalhe num drawer, sem sair da lista.

## Goals — Features (faz, nesta fase)

- Lista paginada (25) com ID do contato, Nome, Celular, E-mail, Fonte, Último e Próximo
  acompanhamento, Estágio de vida, Atribuído a, Endereço, CNPJ / CPF e Adicionado em — as colunas
  da `TelaLeads` do protótipo, sem os campos personalizados.
- Filtros da tela Blade, com os mesmos parâmetros do backend: fonte, estágio de vida, atribuído a.
  Mais busca por nome, ID ou celular. Densidade Confortável / Compacto.
- Drawer de detalhe (`?lead=ID`): informação do lead e acompanhamento, com "Abrir ficha completa"
  (a tela `/crm/leads/{id}`).
- Escopo de visibilidade **igual ao da Blade**: a Inertia recebe a mesma consulta do DataTables
  (`business_id` + `type = lead` + filtros + "só os meus" para quem tem só `crm.access_own_leads`),
  e o drawer sai dessa mesma consulta.

## Non-Goals (nesta fase)

- ~~❌ Formulário de lead~~ — entregue na thread Crm/06 (2026-10-05): "Adicionar" abre
  `/crm/leads/create`, que é o `Cliente/Create` parametrizado (D2) com fonte, estágio de vida e
  atribuído a, gravando pelo `LeadController@store`. Guardas: UC-CRMLD-08..10. A edição do lead
  (`edit()` → `contact.edit`) seguia na Blade até a thread Crm/09 (2026-10-06): agora é o
  `Cliente/Edit` parametrizado, gravando pelo `LeadController@update`. Guardas: UC-CRMLD-11..13.
- ❌ Kanban, conversão para cliente e "Adicionar/Remover do local": seguem na Blade.

## Anti-hooks

- ❌ Decidir Inertia × DataTables só por `request()->ajax()`: o Inertia manda `X-Requested-With`
  junto do `X-Inertia` (§5 2026-09-08). Guarda: UC-CRMLD-02.
- ❌ Montar consulta própria para a tela ou para o drawer: os dois herdam a do DataTables, para não
  divergir de escopo. Guardas: UC-CRMLD-03, UC-CRMLD-04 e UC-CRMLD-06.
