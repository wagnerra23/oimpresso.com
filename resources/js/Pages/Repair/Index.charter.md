---
page: /repair/repair
component: resources/js/Pages/Repair/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Repair
related_adrs: [114, 101, 104]
tier: B
charter_version: 1
---

# Page Charter — /repair/repair (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Repair/Http/Controllers/RepairController@index` (`Route::resource('/repair')`, daí a URL `/repair/repair`). Port 1:1 da listagem Blade legacy (DataTables) → Inertia/React (Sprint 2 / MWART-0001) — não muda UX, troca o motor de render.

---

## Mission

Listagem das Ordens de Serviço (Repair) — a fila operacional de OS com filtros e KPIs de topo, pra o time acompanhar e abrir cada OS. É a porta do módulo Repair (infra compartilhada entre verticais); o detalhe/kanban vivem em outras telas.

---

## Goals — Features (faz)

- Tabela (`DataTable`) das OS: cliente, status, datas e colunas operacionais
- KPIs de topo (`KpiCard`) resumindo a fila
- Filtros (com estado de "nenhuma OS no filtro" vs "sem ordens de serviço")
- `EmptyState` distinguindo filtro-vazio de base-vazia
- AppShellV2 + PageHeader shared ("Ordens de Serviço")

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO edita a OS inline — abertura/edição é a tela de detalhe/status
- ❌ NÃO muda de estágio a OS aqui (transição de estado é fluxo próprio)
- ❌ NÃO cruza tenants — `business_id` scope (Tier 0)
- ❌ NÃO reimplementa a UX do Blade legacy — é port 1:1 (mesma UX, motor React)

---

## UX targets

- p95 < 1500ms (tela admin) com paginação
- Cabe em 1280px (ROTA LIVRE)
- Paridade com a listagem Blade legacy (sem regressão de colunas/ações)

---

## Automation hooks (faz)

- Filtros disparam navegação Inertia preservando querystring

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO faz polling automático da fila
- ❌ NÃO dispara transição de estágio ao carregar
- ❌ NÃO grava nada em GET

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Confirmar paridade de colunas/ações vs Blade legacy (checklist MWART)
- [ ] Smoke visual 1280/1440 (screenshot) — com e sem filtro
