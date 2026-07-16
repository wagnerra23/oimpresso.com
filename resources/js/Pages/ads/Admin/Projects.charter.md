---
page: /ads/admin/projects
component: resources/js/Pages/ads/Admin/Projects.tsx
related_prototype: n/a (lista bespoke com banda de KPIs — usa <ul> em vez da assinatura tabular/grid dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ProjectMgmt
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/projects (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ProjectMgmt/Http/Controllers/Admin/ProjectsController@index` (rota `ads.admin.projects.index`; controller em ProjectMgmt, URL sob `/ads`). Lista os Projects estratégicos (`mcp_projects`) e cria novos; multi-tenant Tier 0 via `businessId` resolvido da sessão.

---

## Mission
Ser o portfólio dos Projects — unidade estratégica que agrupa decisões + ADRs + decomposição. O admin vê o estado de cada project (status, viability, custo, prazo, progresso das parts) e cria um novo informando nome + objetivo macro, que depois é decomposto pelo Project Decomposer Agent (no detalhe). É a entrada da esteira de decomposição estratégica do ADS/ProjectMgmt.

---

## Goals — Features (faz)
- KPIs: total de projects, ativos, draft, concluídos.
- Lista (`<ul>`) de projects: código, nome (linka pro detalhe), status, viability, objetivo, progresso de parts (barra), custo e prazo estimados.
- Form inline "Novo Project" (`useForm`): nome + objetivo macro → `POST /ads/admin/projects` (status=draft).
- EmptyState quando não há projects.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não decompõe o project aqui — decompose fica no detalhe (`ProjectShow`).
- ❌ Não edita/mata project pela lista — só cria e navega.
- ❌ Não pagina/filtra server-side — carrega a lista do business inteira.
- ❌ Não mostra projects de outro business — scopado por `businessId` da sessão (Tier 0). [inferência confirmada no controller]

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 com breadcrumb ADS › Projects.

---

## Automation hooks (faz)
- `store` grava o project e registra audit LGPD via `ProjectMgmtAuditService` (EVENT_PROJECT_CREATED), com objetivo_macro redacted pelo Service.
- Listagem/KPIs delegadas a `ProjectService` com `businessId` explícito (D4 SoC).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Criar project NÃO dispara a decomposição por IA — é ação separada e cara (Sonnet), no detalhe, com confirm.
- ❌ Não faz mutação em GET — só o submit do form cria.
- ❌ Não faz polling do progresso das parts.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Decidir se lista precisa de filtro/paginação quando o volume crescer
