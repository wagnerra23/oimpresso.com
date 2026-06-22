---
page: /repair/job-sheet
component: resources/js/Pages/Repair/JobSheet/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-07"
parent_module: Repair
parent_capterra: memory/requisitos/Repair/CAPTERRA-FICHA.md
related_adrs: [101, 104, 149, 143, 93]
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/os/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Index]
  divergence_from_blueprint: "tabela ainda usa DataTables AJAX legacy (sprint 2.5) — Wave W3-B6 documenta path canônico mas preserva implementação atual"
---

# Page Charter — /repair/job-sheet

> **Status:** live (Sprint 2.5 / MWART-0002, Blade → Inertia shell). Tabela ainda DataTables AJAX legacy via Blade — migração TanStack Table em iteração futura.

---

## Mission

Listar e filtrar Ordens de Serviço por status, cliente, equipe e local — ponto de entrada pra ações de OS.

---

## Goals — Features (faz)

- Header com título + descrição + botão "Nova OS" → `/repair/job-sheet/create`
- 4 dropdowns de filtro (business_locations, customers, status_dropdown, service_staffs)
- Embed do DataTables AJAX legacy via container ref (paridade visual com Blade)
- Respeita 3 flags vindas do Controller: `is_user_service_staff`, `show_serial_no`, `enable_brand_in_job_sheet`
- Multi-tenant: dados scopados por `business_id` global scope no Controller

---

## Non-Goals — Features (NÃO faz)

- ❌ Criação/edição inline (vai pra `/repair/job-sheet/create` e `/edit`)
- ❌ Print direto (rota Blade separada `/repair/job-sheet/{id}/print`)
- ❌ Upload de arquivos (rota Blade separada)
- ❌ Mudança de status drag-and-drop (visualização-only, não kanban)
- ❌ Filtro client-side (DataTables faz server-side)
- ❌ Export PDF/Excel direto (DataTables legacy tem botão próprio)
- ❌ Notificação push de novas OS (não escuta evento real-time)

---

## UX Targets

- p95 first-paint < 1200ms (Blade DataTable é o gargalo aceito)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (cliente ROTA LIVRE)
- Filtros persistem em URL/state ao recarregar (preserve via DataTables)
- Empty state visível quando filtros zeram a lista
- DOM API (`document.createElement`) em vez de `dangerouslySetInnerHTML` pra `datatable_url` — guard XSS (R-OWASP)

---

## UX Anti-patterns

- ❌ Modal pra criar OS (rota dedicada existe — não duplicar fluxo)
- ❌ Confirmação dupla em filtros (são read-only)
- ❌ Tooltip explicando o que é OS (audiência conhece o domínio)
- ❌ Mudança de URL sem preserveScroll (perde posição da tabela)
- ❌ Loading skeleton no shell (DataTable controla próprio loading)

---

## Automation Hooks

- Endpoint controller chama `JobSheetController::index()` com filtros injetados
- DataTable AJAX legacy bate em `datatable_url` (mantém pipeline existente)
- Multi-tenant scoping via Eloquent global scope (`business_id`)

---

## Automation Anti-hooks

- ❌ Não dispara emails ao abrir
- ❌ Não dispara SMS ao listar
- ❌ Não muda status de OS (read-only)
- ❌ Não escreve no banco
- ❌ Não roda jobs em fila ao abrir
- ❌ Não chama Brain B/Sonnet
- ❌ Não acessa OS de outro `business_id` (multi-tenant Tier 0 — [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

---

## Métricas vivas (Pest GUARD)

- `RepairJobSheetCharterTest::it_renders_under_1200ms_p95()`
- `RepairJobSheetCharterTest::it_does_not_emit_emails()`
- `RepairJobSheetCharterTest::it_does_not_dispatch_jobs()`
- `RepairJobSheetCharterTest::it_does_not_mutate_state()`
- `RepairJobSheetCharterTest::it_isolates_by_business_id()`
- `RepairJobSheetCharterTest::it_uses_dom_api_for_datatable_url()` — anti-XSS guard
- `RepairJobSheetCharterTest::it_renders_at_1280px_without_horizontal_scroll()`

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-07 | Opus + Wagner | Charter criado em S6 F1 (Foundation). Não enforced ainda — workflow `charter-gate.yml` em modo soft (warn-only) até F2. |
