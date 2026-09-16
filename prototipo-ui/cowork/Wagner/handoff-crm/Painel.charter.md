---
id: resources-js-pages-crm-painel-charter
page: /crm/dashboard
component: resources/js/Pages/Crm/Painel.tsx
related_prototype: prototipo-ui/cowork/crm-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-24"
parent_module: Crm
related_adrs: [93, 264, 286]
tier: B
charter_version: 1
related_blade: Modules/Crm/Resources/views/crm_dashboard/index.blade.php
related_controller: Modules/Crm/Http/Controllers/CrmDashboardController.php
---

# Page Charter — /crm/dashboard (DRAFT)

> **Status:** draft — existe só como Blade legado (`crm_dashboard/index.blade.php`, 466 linhas) + protótipo Cowork. O Blade mistura visão pessoal ("meus acompanhamentos") com visão de admin no mesmo scroll, gated por `auth()->user()->can()` e por `config('constants.enable_crm_call_log')`.

## Mission

Dar ao operador a leitura do dia do CRM em uma tela: o que é MEU (acompanhamentos de hoje, meus leads, minha conversão) antes do que é da CASA (totais, fontes, estágios, aniversários, ranking).

## Goals

- 4 KPIs pessoais: acompanhamentos de hoje, meus leads, meus leads convertidos, chamadas hoje
- Meus acompanhamentos por status (agendado/aberto/concluído/cancelado) + meus registros de chamadas (hoje/ontem/mês)
- Bloco admin: clientes, leads, fontes, estágios de vida + tabela de fontes com conversão % + tabela de estágios
- Aniversários (hoje + próximos) com seleção múltipla → cria campanha de parabéns (`CampaignController::create?contact_ids=`)
- Acompanhamentos por usuário (matriz status × usuário) + leads convertidos por usuário + chamadas por usuário
- Multi-tenant: todo número escopado por `business_id` (ADR 0093)

## Non-Goals

- ❌ Não é o dashboard do negócio (`/home`) — nada de faturamento, caixa ou produção aqui
- ❌ Não substitui Relatórios: o painel mostra o agregado do dia, o relatório mostra o recorte com filtro de período
- ❌ Não edita nada: só o botão de aniversários sai da tela, e sai para o form de campanha
- ❌ Sem gráfico de funil (o funil é a tela de Deals)

## UX Targets

- p95 first-paint < 1200ms com 300 contatos
- Bloco admin só renderiza para quem é admin — não vem oculto no DOM
- Chamadas só aparecem se `enable_crm_call_log` (o Blade já gateia; a tela React deve receber a flag por prop)

## Automation Anti-hooks

- ❌ Não dispara os parabéns sozinho: seleção → form de campanha → o humano envia
- ❌ Não conta lead de outro `business_id`
- ❌ Não reapura em polling: reapuração é clique explícito no header

## Refs

- Blade: `crm_dashboard/index.blade.php` · Controller: `CrmDashboardController::index`
- Permissões: `crm.access_all_schedule`, `crm.access_own_schedule`, `crm.access_all_leads`, `crm.access_own_leads`
