---
page: /project-mgmt/activity
component: resources/js/Pages/ProjectMgmt/Activity/Index.tsx
related_prototype: n/a (feed cronologico de atividade bespoke, agrupado por dia — nao segue um dos 5 Padroes de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ProjectMgmt
related_us: [US-TR-205]
related_adrs: [114, 101, 93, 70]
tier: B
charter_version: 1
---

# Page Charter — /project-mgmt/activity (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ProjectMgmt/Http/Controllers/ActivityController@index` (rota `project-mgmt.activity.index`, permissão `copiloto.mcp.usage.all`). Feed cronológico de eventos append-only. **Silenciosa:** o corpo dominante é um feed/timeline bespoke — há um `KpiGrid` de header, mas a tela não é um dos 5 Padrões de Tela; declarar um PT seria ruído. Honestidade > cobertura.

---

## Mission
Dar uma linha do tempo cronológica de tudo que aconteceu no projeto: eventos de `mcp_task_events` (append-only) agrupados por dia — quem criou, mudou status, atribuiu, comentou ou atualizou qual task. É o "log vivo" do time: útil pra auditar o dia, ver o ritmo recente e rastrear uma task específica. Filtros por tipo, autor, task e período (1–90 dias).

---

## Goals — Features (faz)
- Feed de eventos agrupado por dia (Hoje/Ontem/N dias atrás), com ícone e label por tipo de evento (criou/mudou status/atribuiu/comentou/atualizou).
- Destaque especial para `status_changed` (de → para) e nota do evento.
- KPIs de header (`KpiGrid`/`KpiCard`): últimas 24h, últimos 7d, criadas, concluídas.
- Filtros: tipo, autor, task (uppercase), período (1/7/14/30/90 dias) + botão "Limpar".
- Auto-refresh do feed a cada 30s.
- Escopo por projeto (default `config('projectmgmt.default_project_key')`), limite 300 eventos.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não é multi-tenant por `business_id` — opera sobre `mcp_task_events` (log interno do time), gated por `copiloto.mcp.usage.all`. (inferência pendente de Wagner)
- ❌ Não edita nem apaga eventos — `mcp_task_events` é append-only. (inferência pendente de Wagner)
- ❌ Não abre o Detail Sheet da task a partir do evento. (inferência pendente de Wagner)
- ❌ Não segue um dos 5 Padrões de Tela: é um feed bespoke, deliberadamente silenciosa quanto a PT. (inferência pendente de Wagner)

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb Project Mgmt / Activity).

---

## Automation hooks (faz)
- Auto-reload do feed a cada 30s (`only: ['events','kpis']`).
- Filtros fazem partial reload que pula as closures de dropdown (`authors`/`event_types` só rodam na carga inicial — padrão D-14).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Tela read-only: nenhuma escrita, nenhuma mutação em GET.
- ❌ Não notifica ninguém a partir do feed (é só leitura do log).
- ❌ Não expande histórico além de 90 dias / 300 eventos.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Wagner confirma o silêncio de PT (feed bespoke, não força um dos 5 Padrões)
- [ ] Smoke visual 1280/1440 (screenshot)
