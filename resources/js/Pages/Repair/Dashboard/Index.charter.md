---
page: /repair/dashboard
component: resources/js/Pages/Repair/Dashboard/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-07"
parent_module: Repair
parent_capterra: memory/requisitos/Repair/CAPTERRA-FICHA.md
related_adrs: [101]
tier: A
---

# Page Charter — /repair/dashboard

> **Status:** rascunho exemplo (gerado junto a [ADR 0101](../../../../../memory/decisions/0101-sistema-charter-capterra-governanca-escopo.md) §Implementação). NÃO enforced ainda — vira contrato vivo após Sprint S6 F1.
>
> Esse charter foi extraído da tela atual em prod (`Index.tsx` 100 linhas, sprint 2.5/MWART-0002, 5 painéis em cockpit pattern).

---

## Mission (1 frase)

Visão consolidada de OS Repair (KPIs + status + equipe + tendências) num único panorama, **sem ações de mutação**.

---

## Goals — Features (faz)

- KPI `total_repairs` (status únicos)
- KPI `service_staff_count` (equipe ativa)
- Lista "OS por status" (`job_sheets_by_status`)
- Lista "OS por service staff" (`job_sheets_by_service_staff`)
- Lista "Top marcas trending" (`trending_brand_chart`)
- Lista "Top modelos trending" (`trending_dm_chart`)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ CRUD de OS (vai pra `/repair/jobsheet`)
- ❌ Filtro por período/data (M2 — backlog `US-REPAIR-DASH-2`)
- ❌ Painel próprio pra `trending_devices_chart` (FIXME `US-REPAIR-DASH-1`)
- ❌ Export PDF/Excel
- ❌ Drilldown clicável em qualquer KPI/lista
- ❌ Push notifications (assistant pattern)
- ❌ Comparação ano-anterior (M3)
- ❌ Edição inline em qualquer card

---

## UX Targets

- p95 first-paint < 800ms (cockpit pattern; props vêm prontas do Controller, não async)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (cliente ROTA LIVRE — quirk crítico)
- AppShellV2 layout (ADR 0039 superseded)
- Grid responsivo 1col / 2col / 4col (mobile/tablet/desktop)
- Empty state em cada `SimpleListCard` com mensagem PT-BR ("Sem dados de status", etc.)
- Ícones de cada KPI (`wrench`, `users`) consistentes com sidebar do módulo

---

## UX Anti-patterns

- ❌ Modal de qualquer tipo (read-only, dashboard)
- ❌ Confirmação dupla (sem ações destrutivas)
- ❌ Loading skeleton infinito (props vêm do controller, não async)
- ❌ Toast/snackbar (sem mutações pra notificar)
- ❌ Paginação em qualquer lista (top N fixo)
- ❌ Tabelas com sort/filter client-side (sem complexidade)

---

## Automation Hooks

- Endpoint `/repair/dashboard` chama `RepairDashboardController` que agrega 5 queries
- Recalcula on-demand (sem cache hoje — F1 charter pode adicionar `Cache::remember`)
- Multi-tenant: queries scopadas por `business_id` global scope (sem `withoutGlobalScopes`)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails
- ❌ Não dispara SMS
- ❌ Não muda status de OS (read-only puro)
- ❌ Não roda jobs em fila ao abrir
- ❌ Não escreve no banco
- ❌ Não chama Brain B/Sonnet (sem IA)
- ❌ Não acessa dados de outro `business_id` (multi-tenant Tier 0)

---

## Métricas vivas (Pest GUARD — a escrever em F1)

```php
// Modules/Repair/Tests/Charters/RepairDashboardCharterTest.php

it('renders under 800ms p95', ...);
it('does not emit emails', ...);
it('does not dispatch jobs', ...);
it('does not mutate state', ...);
it('isolates by business_id', ...);
it('renders at 1280px without horizontal scroll', ...);
it('shows PT-BR empty state on every list', ...);
it('contains exactly 2 KPIs and 4 lists', ...);
```

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-07 | Opus + Wagner | Charter exemplo criado em sessão diagnóstico (Onda C+ do plano de organização). Não enforced ainda. |
