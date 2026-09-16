---
id: resources-js-pages-crm-leads-charter
page: /crm/leads
component: resources/js/Pages/Crm/Leads.tsx
related_prototype: prototipo-ui/cowork/crm-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-24"
parent_module: Crm
related_adrs: [93, 179, 264, 286]
tier: A
charter_version: 1
related_blade: Modules/Crm/Resources/views/lead/index.blade.php, lead/show.blade.php
related_controller: Modules/Crm/Http/Controllers/LeadController.php
---

# Page Charter — /crm/leads (DRAFT)

> **Status:** draft — Blade legado com DataTable server-side (`lead_view=list_view`) **ou** kanban jQuery (`lead_view=kanban`), 23 colunas incluindo 10 custom fields. Protótipo Cowork reduz para as colunas com uso real e mantém as duas vistas.

## Mission

A lista de quem ainda não é cliente, com o suficiente na linha para decidir o próximo contato sem abrir a ficha: fonte, estágio de vida, último e próximo acompanhamento, responsável.

## Goals

- Duas vistas da mesma lista: tabela densa e kanban por estágio de vida (o `lead_view` do Blade)
- Filtros do Blade: fonte, estágio de vida (só na tabela), atribuído a
- Frescor do último acompanhamento como pílula (recente/fresc/frio/distante) — recência é o sinal que o vendedor lê primeiro
- Ação de linha: exibir lead, adicionar acompanhamento, converter para cliente
- Conversão pede o **estágio de vida pós-conversão** (`postLifeStage`) antes de mandar pra Clientes
- Seleção múltipla → adicionar/remover do local do negócio (o rodapé `update_contact_location`)
- Kanban move o lead entre estágios por arrasto, com o mesmo efeito da edição do campo

## Non-Goals

- ❌ Não cadastra contato aqui: "adicionar" abre o form de contato do módulo Cliente (não duplicar cadastro)
- ❌ Não é o funil de negócios: estágio de vida (Contact) ≠ stage do Deal (`crm_deals`) — telas diferentes, vocabulários diferentes
- ❌ Sem as 10 colunas de custom field por padrão: entram por preferência de coluna, não no default
- ❌ Não edita valor de oportunidade (isso é Deal)

## UX Targets

- Tabela densa: 1280px (balcão da Larissa) sem scroll horizontal nas colunas essenciais
- Arrasto no kanban com feedback de coluna-alvo; sem animação de mola
- Conversão em 2 cliques (ação → confirmar estágio)

## Automation Anti-hooks

- ❌ Converter não cria venda, não emite documento, não manda mensagem — só muda o tipo do contato
- ❌ Arrastar no kanban não dispara notificação ao responsável
- ❌ Nunca lista lead de outro `business_id`

## Refs

- Blade: `lead/index.blade.php` (+ `partial/lead_info`, `partial/lead_schedule`) · `LeadController::{index,show,convertToCustomer,postLifeStage}`
- Permissões: `crm.access_all_leads` / `crm.access_own_leads`
