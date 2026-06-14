---
project: PMG
default_component: UI
parent_spec: SPEC.md
---

# ProjectMgmt — SPEC complementar (US-PROJ-001..008)

> **Status**: backlog gaps detectados por audit 2026-05-16 (Wave Massive — 12 agents).
> **Owner**: Wagner [W]
> **Origem**: nota 32/100 — D1 10/30, D2 8/20, D3 6/15. Esta SPEC cobre os gaps
> NÃO cobertos pelo `SPEC.md` original (Fase 1+2 PMG-001..007 já done).
> **Nota alvo pós-bateria**: ≥ 50/100 (Saudável).

## Visão complementar

`SPEC.md` cobriu o redesign UI 4 fases (PMG-* Fase 1+2 mergeadas). Esta SPEC
complementar formaliza as **8 user stories de governança + cobertura Pest +
backlog Fase 3/4** que faltam pro módulo sair de "Crítico" pra "Saudável".

Estado vivo de cada US: tools MCP `tasks-list module:ProjectMgmt`.

---

## User Stories

### US-PROJ-001 · Tests Pest scaffold + multi-tenant + smoke routes

> owner: wagner · priority: p0 · estimate: 1h · status: doing · type: chore

Cobertura Pest base do scaffold do módulo (validada em Modules/Auditoria
+ Modules/ComunicacaoVisual).

- [x] `Modules/ProjectMgmt/Tests/Feature/ScaffoldProjectMgmtTest.php` — Module::find + Route::has (8 rotas) + ServiceProvider carregado (9 cenários)
- [x] `Modules/ProjectMgmt/Tests/Feature/MultiTenantProjectTest.php` — Project biz=1 vs biz=99, Task biz=1 não vaza, bulk anti-vazamento, cross-tenant coexistência (5 cenários)
- [x] `Modules/ProjectMgmt/Tests/Feature/SmokeRoutesProjectMgmtTest.php` — 7 rotas GET <500 + middleware auth bloqueia anônimo (8 cenários)
- [ ] CI: roda em `phpunit.xml` testsuite `Feature` (não tocar phpunit.xml — auto-discover já em vigor)

**Refs**: ADR 0093 (multi-tenant Tier 0) · ADR 0101 (biz=1 nunca biz=4) · ADR 0011 (padrão Jana/Repair)

---

### US-PROJ-002 · Kanban — Saved views backend

> owner: wagner · priority: p1 · estimate: 4-6h · status: backlog · type: feature
> blocked_by: —

Presets de filtros (módulo, owner, priority, status) persistidos por user/business —
hoje os 6 filtros do Board são state local React, perdidos no refresh.

- [ ] Migration `mcp_saved_views` (id, user_id, business_id, module='ProjectMgmt', name, filters JSON, is_default bool, created_at)
- [ ] `BoardController::saveView(Request)` POST `/board/views` + `BoardController::listViews()` GET `/board/views`
- [ ] Frontend: dropdown "Visualizações" no Board com Save/Load/Set as default
- [ ] Pest: saved view biz=1 não aparece pra biz=99 (multi-tenant)

**Refs**: CAPTERRA-FICHA capacidade C-08 (Linear ✅ Jira ✅ — gap atual ❌)

---

### US-PROJ-003 · Sprint planning UI (cycle create/close)

> owner: wagner · priority: p1 · estimate: 6-8h · status: backlog · type: feature
> blocked_by: —

UI sobre tools MCP `cycles-create` + `cycles-close --rollover` já existentes.

- [ ] `Modules/ProjectMgmt/Http/Controllers/CycleController.php` — index + create + close
- [ ] Rotas: `GET /cycles`, `POST /cycles`, `POST /cycles/{id}/close`
- [ ] Frontend: drawer "Novo Cycle" com goals trackables (mcp_cycle_goals)
- [ ] Frontend: "Fechar Cycle" com rollover de tasks incompletas + retro inline
- [ ] Pest: cycle scope biz=1 + close rollover preserva business_id

**Refs**: ADR 0070 (Jira-style tasks) · `mcp_cycles.retro` JSON

---

### US-PROJ-004 · Atalhos avançados de produtividade

> owner: wagner · priority: p1 · estimate: 3-4h · status: backlog · type: feature
> blocked_by: —

Atalhos teclado nível Linear pra trabalhar no Board sem mouse.

- [ ] `j` / `k` — navegar card por card vertical
- [ ] `c` — abrir form "Criar task" rápido
- [ ] `e` — editar inline o título do card focado
- [ ] `enter` — abrir Detail Sheet do card focado
- [ ] `shift+enter` — promover card pra epic
- [ ] `?` — modal de ajuda listando todos atalhos
- [ ] Pest playwright: smoke navegação por atalho (`j` move foco)

**Refs**: CAPTERRA-FICHA capacidade C-12 (Linear ✅ Jira ⚠️ — gap atual ❌)

---

### US-PROJ-005 · Real-time presence via Centrifugo

> owner: wagner · priority: p2 · estimate: 4-6h · status: backlog · type: feature
> blocked_by: Modules/Jana Centrifugo SDK em prod

Mostrar avatares de quem está com o Detail Sheet aberto na mesma task (ADR 0058).

- [ ] CentrifugoPresenceService::publish(task_id, user_id) ao montar Detail Sheet
- [ ] Frontend: `<PresenceAvatars taskId={...} />` componente — subscribe channel `task:{id}`
- [ ] Cleanup: unmount → unsubscribe + Centrifugo expira presence em 30s
- [ ] Pest: integração Centrifugo mockada (Modules/Jana/Centrifugo fixture)

**Refs**: ADR 0058 (Centrifugo > Reverb) · ADR 0062 (CT 100 runtime)

---

### US-PROJ-006 · Time tracking — estimate vs actual

> owner: wagner · priority: p2 · estimate: 3-4h · status: backlog · type: feature
> blocked_by: —

Cada task já tem `estimate_hours` em `mcp_tasks`. Falta tracking real.

- [ ] Coluna `actual_hours` em `mcp_tasks` (migration nova)
- [ ] Botão "Iniciar timer" no Detail Sheet → store `started_at` em `mcp_task_time_entries`
- [ ] Botão "Parar" → calcula diff e soma `actual_hours`
- [ ] Burndown enriched: linha estimate vs actual + variance %
- [ ] Pest: entry biz=1 não vaza biz=99 (multi-tenant Tier 0)

**Refs**: CAPTERRA-FICHA capacidade C-15 (ClickUp ✅ Asana ⚠️ — gap atual ❌)

---

### US-PROJ-007 · Membros do projeto + permissions granulares

> owner: wagner · priority: p2 · estimate: 4-6h · status: backlog · type: feature
> blocked_by: —

Hoje permission é binária (`copiloto.mcp.usage.all`). Time MCP entra em breve
(Felipe/Maiara/Eliana/Luiz) — precisa scope por projeto.

- [ ] Migration `mcp_project_members` (project_id, user_id, role: viewer/contributor/admin)
- [ ] `ProjectMembersController` (Admin/ProjectsController estendido)
- [ ] Middleware `EnsureProjectMember` aplicado em rotas que tocam task de project específico
- [ ] Frontend: drawer "Membros" no Admin/Projects + adicionar/remover/mudar role
- [ ] Pest: user viewer não consegue PATCH status (403) + admin pode (200)

**Refs**: ADR 0093 (multi-tenant Tier 0) + skill `multi-tenant-patterns`

---

### US-PROJ-008 · Charter páginas restantes (Backlog/Roadmap/MyWork)

> owner: wagner · priority: p2 · estimate: 2-3h · status: backlog · type: docs
> blocked_by: —

Charter já existe pra Board ([CHARTER-board.md](CHARTER-board.md)). Falta pras
outras 5 telas — pré-req pra editar `.tsx` correspondente (skill `charter-first`).

- [ ] `resources/js/Pages/ProjectMgmt/Backlog/Index.charter.md` — Mission, Goals, Non-Goals, UX targets, Anti-hooks
- [ ] `resources/js/Pages/ProjectMgmt/Roadmap/Index.charter.md`
- [ ] `resources/js/Pages/ProjectMgmt/MyWork/Index.charter.md`
- [ ] `resources/js/Pages/ProjectMgmt/Activity/Index.charter.md`
- [ ] `resources/js/Pages/ProjectMgmt/Burndown/Index.charter.md`
- [ ] Skill `charter-write` (Tier B) gera draft → Wagner revisa Non-Goals + Anti-hooks

**Refs**: ADR 0099 (Charter > Spec — Constituição v2 princípio 3) · skill `charter-first` (S4+)

---

## Métricas de fechamento desta SPEC complementar

Quando US-PROJ-001..008 done → nota alvo:
- D1 Cobertura funcional: 10 → 22 / 30 (saved views + sprint UI + atalhos + presence + time tracking + members)
- D2 UX/UI: 8 → 15 / 20 (Linear-tier real com atalhos + presence)
- D3 Testes Pest: 6 → 13 / 15 (+3 testes Pest desta wave + Pest pra cada US 002..007)
- **Total: 32/100 → ~58/100 (Saudável)**

Validação: `php artisan module:grade ProjectMgmt --detail` (skill `avaliar-modulo`).
