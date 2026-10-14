---
id: modules-crm-pages-crm-propostas-index-charter
page: /crm/proposals
component: Modules/Crm/Resources/js/Pages/Crm/Propostas/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 2
---

# Page Charter — /crm/proposals (DRAFT · propostas enviadas)

> **Status:** draft. Nasce na thread Crm/14 (`ProposalController@index`, Blade → Inertia), atrás da
> flag `useV2CrmPropostas` (default OFF). Vai a `live` com o cutover [W]. Casos:
> [`Index.casos.md`](Index.casos.md) · contrato: `governance/design/contracts/crm-propostas.contract.json`
> (derivado do alvo `crm--propostas--index`, A2 #9255) · RUNBOOK:
> `memory/requisitos/Crm/RUNBOOK-propostas.md`. Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Mostrar ao time comercial **quais propostas saíram, para quem e por quem**, e abrir o conteúdo de
cada uma (assunto, cc, bcc, corpo, anexos) sem sair da lista.

## Goals — Features (faz, nesta fase)

- Lista paginada (25) com Contato, Assunto, Enviado por, Data, Anexos e Ação — as colunas da
  `Propostas()` do protótipo; mais recentes primeiro, como a Blade.
- Busca por contato, assunto ou quem enviou (os campos que a DataTable da Blade busca).
- Escopo **igual ao da Blade**: a tela recebe a mesma consulta da DataTable (`business_id` +
  "só as que enviei" para quem não é `Admin#`).
- Drawer PT-02 de detalhe (`?proposta=ID`) com cc, bcc, corpo e anexos, lido da mesma consulta.
- "Mandar" (só com modelo cadastrado e `crm.access_proposal`) e "Modelo de proposta" levam às telas
  Blade do `ProposalTemplateController`.

## Non-Goals (nesta fase)

- ❌ "Reenviar" e "Excluir anexo" do kebab do protótipo: não há endpoint no `ProposalController`
  (`update`/`destroy` vazios). Não se inventa ação sem backend.
- ❌ Modal "Enviar proposta" do protótipo nesta tela: o envio segue na Blade `proposal_template.send`.

## Anti-hooks

- ❌ Decidir Inertia × DataTables só por `request()->ajax()`: o Inertia manda `X-Requested-With`
  junto do `X-Inertia` (§5 2026-09-08). Guarda: UC-CRMPRO-02.
- ❌ Consulta própria para a tela nova: lista e drawer herdam a da DataTable. Guardas:
  UC-CRMPRO-03 (outro negócio) e UC-CRMPRO-04 (só as que enviei).
- ❌ Injetar o corpo da proposta como HTML (`dangerouslySetInnerHTML`): o drawer recebe texto.
  Guarda: UC-CRMPRO-06.
- ❌ Servir a tela nova com a flag desligada, ou tirar a saída `?classico=1`. Guarda: UC-CRMPRO-07.
