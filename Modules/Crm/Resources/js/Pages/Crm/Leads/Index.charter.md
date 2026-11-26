---
id: modules-crm-pages-crm-leads-index-charter
page: /crm/leads
component: Modules/Crm/Resources/js/Pages/Crm/Leads/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade.jsx
owner: wagner
status: draft
last_validated: "2026-11-26"
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
- Kanban por estágio de vida (todos os estágios do negócio, contador, arrastar muda o estágio) e
  janela de conversão para cliente com o estágio depois da conversão (pendências CRM-23/25).
- Escopo de visibilidade **igual ao da Blade**: a Inertia recebe a mesma consulta do DataTables
  (`business_id` + `type = lead` + filtros + "só os meus" para quem tem só `crm.access_own_leads`),
  e o drawer sai dessa mesma consulta.

## Non-Goals (nesta fase)

- ~~❌ Formulário de lead~~ — entregue na thread Crm/06 (2026-10-05): "Adicionar" abre
  `/crm/leads/create`, que é o `Cliente/Create` parametrizado (D2) com fonte, estágio de vida e
  atribuído a, gravando pelo `LeadController@store`. Guardas: UC-CRMLD-08..10. A edição do lead
  (`edit()` → `contact.edit`) seguia na Blade até a thread Crm/09 (2026-10-06): agora é o
  `Cliente/Edit` parametrizado, gravando pelo `LeadController@update`. Guardas: UC-CRMLD-11..13.
- ~~❌ Kanban e conversão para cliente~~ — entregues em 2026-11-26 (pendências CRM-23/25, pedido [W]
  2026-11-26): o kanban é a vista `?vista=kanban` desta tela, sobre a mesma consulta da lista, e a
  conversão é uma janela que grava o estágio escolhido e converte pelas rotas da Blade, agora presas
  ao escopo da lista. Guardas: UC-CRMLD-15..17. O `?lead_view=kanban` da Blade segue servindo a
  tela antiga (UC-CRMLD-07).
- ❌ "Adicionar/Remover do local": seguem apontando para a Blade. Lá os botões não têm ação (nenhum
  script trata o clique, e contato não tem "local" no banco); o que eles devem fazer é decisão [W].

## Anti-hooks

- ❌ Decidir Inertia × DataTables só por `request()->ajax()`: o Inertia manda `X-Requested-With`
  junto do `X-Inertia` (§5 2026-09-08). Guarda: UC-CRMLD-02.
- ❌ Montar consulta própria para a tela ou para o drawer: os dois herdam a do DataTables, para não
  divergir de escopo. Guardas: UC-CRMLD-03, UC-CRMLD-04 e UC-CRMLD-06.
