# Sprint 2 — Listagem de OS (piloto MWART)

> Primeira migração Blade → Inertia/React do módulo `Repair`. Valida o padrão MWART em produção.

> **OS = Ordem de Serviço** = entidade do `Modules/Repair/`. Internamente é `transactions` com `type='sell'` e `sub_type='repair'` (padrão UltimatePOS). Não confundir com `Modules/Officeimpresso/` (módulo de licenciamento, não tem OS).

## Objetivo

Migrar a tela **Listagem de OS** (`/repair/repair`, route name `repair.index`) de Blade para React/Inertia, mantendo paridade funcional 100% e zero re-aprendizado pelo cliente final.

Esta é a **prova do padrão MWART** (Module Web App React Transition). Se funcionar aqui, replica nas outras telas Repair (Job Sheet, Status, Device Models, Dashboard) e nos outros 20+ módulos nWidart.

## Não-objetivo

- ❌ Não mexer em criação/edição de OS (Sprint 2.5) — `Route::resource` exclui `create`/`edit` no módulo, edição é via modal/page Blade
- ❌ Não mexer em Job Sheet (Sprint 3)
- ❌ Não migrar outros módulos
- ❌ Não adicionar coluna `prioridade`/`arquivada_em` ao `transactions` (escopo de produto, não de migração)
- ❌ Não trocar `repair_statuses` por enum

## Deliverables (PR2)

1. `01-adr-mwart-contract.md` — contrato controller dual-mode + flag em `config/mwart.php`
2. `02-schema-repair-indices.sql` — índices novos em `transactions` para listagem rápida sob filtro `sub_type='repair'`
3. `03-spec-repair-controller.md` — spec do `RepairController@index` Inertia
4. `04-spec-repair-index-react.md` — spec do `Pages/Repair/Index.tsx`
5. `05-skill-mwart-migrate.md` — skill reusável pra próximas migrações
6. `06-checklist-wagner.md` — passos PR + soak 48h
7. `07-rollback-plan.md` — feature flag + plano de reversão

## Critério de aceite

- [ ] `MWART_REPAIR_INDEX=true` no `.env` faz `/repair/repair` renderizar React
- [ ] `MWART_REPAIR_INDEX=false` reverte pra Blade sem perda de estado
- [ ] Filtros (status/cliente/período/responsável/location) funcionam idênticos ao Blade
- [ ] Paginação preserva URL bookmarkável (`?page=3&repair_status_id=2`)
- [ ] Bulk actions (mudar status, change service staff) idênticas ao Blade
- [ ] Multi-tenant: queries sempre com `transactions.business_id = session('user.business_id')`
- [ ] Permissions Spatie respeitadas: `repair.view` (todas) vs `repair.view_own` (só `created_by = auth()->id()`)
- [ ] p95 < 400ms na listagem (medido via Telescope)
- [ ] Zero erros JS no Sentry em 48h soak

## Soak

48h em staging com 3 usuários internos antes de promover pra prod. Cliente piloto: ROTA LIVRE (`business_id=4`, Larissa) — concentra 99% do volume e tem histórico de feedback rápido.

## Dependências

- ✅ Sprint 1 mergeada (commit `dd1d8a4e`, dossier em `memory/sprints/s1-daily-brief/`)
- ✅ Tool MCP `brief-fetch` deployed em `mcp.oimpresso.com` (commit `c4fc2680`, PR #109) + skill `brief-first` Tier A always-on ([ADR 0091](../../decisions/0091-daily-brief.md))
- ✅ `AppShellV2.tsx` em `resources/js/Layouts/` (canônico, ver DESIGN.md)
- ✅ Sidebar via `DataController.modifyAdminMenu` + `SIDEBAR_GROUPS` (skill `sidebar-menu-arch`)
- ✅ Inertia v3 + React 19 + Tailwind 4
- ✅ Índices `repair_*` em `transactions` desde 2021 (migration `2021_02_16_190423_add_repair_module_indexing.php`) — só faltam índices de listagem por `sub_type` (vê 02)

## Out of scope (próximas sprints)

- Sprint 2.5: Detalhe OS + Nova OS (mesma tabela, mesmo controller)
- Sprint 3: Job Sheet, Status CRUD, Device Models
- Sprint 4: Dashboard Repair
- Sprint 5+: outros módulos (Project, Manufacturing, Financeiro)
