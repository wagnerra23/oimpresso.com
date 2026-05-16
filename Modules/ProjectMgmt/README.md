# Modules/ProjectMgmt

> Jira-style Project Management interno do oimpresso. Board (Kanban) + Backlog + MyWork + Roadmap + Activity feed + Burndown sobre tabelas canônicas `mcp_projects` / `mcp_tasks` / `mcp_task_*` (entities canônicas em `Modules/Jana/Entities/Mcp/`).
>
> Promovido a módulo próprio em 2026-05-04 ([ADR 0070](../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)). UI redesign PMG-001..007 ([ADR 0100](../../memory/decisions/0100-project-mgmt-ui-redesign-roadmap.md)).

## Status
- **Versão:** 0.1 (em construção)
- **Stack:** Laravel 13.6 + Inertia v3 + React 19 + Tailwind 4
- **Permissão:** `copiloto.mcp.usage.all` (herdada do TeamMcp legacy)
- **Tenant:** Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

## Como o cliente usa

> "Cliente" aqui = operador interno do oimpresso (Wagner/Felipe/Maiara/Luiz/Eliana). Time MCP entrando em breve aumenta uso N pessoas (regra Tier 0 "mexeu, registra" — `memory/proibicoes.md`).

### Jornada típica diária

1. **Manhã — checar minha caixa** → `/project-mgmt/my-work`
   - Vê tasks ativas (todo/doing/review/blocked) agrupadas por Cycle ativo
   - Lê inbox de notifications: @mentions, assigneds, review_requested, status_changed, due_soon
   - Marca lidas (clica notification → `markRead` → some da lista)

2. **Operação — Kanban Board** → `/project-mgmt/board`
   - Default filtra pelo project COPI (config `projectmgmt.default_project_key`)
   - Drag-drop card entre colunas (backlog/todo/doing/review/done) — PATCH `/board/{taskId}/status` com optimistic-lock (409 Conflict se outro user atualizou)
   - Atalhos teclado J/K navegam cards (charter Board live exige initial render eager — RUNBOOK-inertia-defer)
   - Filtros: cycle, epic, owner, component

3. **Drawer DetailSheet** (PMG-004) — clica card abre slide-over com:
   - description (markdown)
   - comments thread + @mentions (autocomplete via `/board/users/suggest`)
   - events audit trail (50 últimos)
   - subtasks (parent_task_id) + adicionar nova
   - dependencies (blocks/relates-to) com target render
   - watchers (subscribe/unsubscribe pra receber notif)

4. **Backlog grooming** → `/project-mgmt/backlog`
   - Lista densa todas tasks (inclui done/cancelled quando filtro="all")
   - Bulk-edit múltiplas selecionadas: status/priority/owner/sprint
   - Search global via Cmd+K (`/project-mgmt/search`) — PMG-002

5. **Roadmap planejamento** → `/project-mgmt/roadmap` — visão epic + cycles

6. **Burndown sprint** → `/project-mgmt/burndown` — gráfico done vs total no cycle

### Admin operacional

Wagner usa `/ads/admin/projects` (ProjectsController refatorado D4 Wave 16) pra:
- Criar projects estratégicos (PROJ-YYYYMM-NNN auto-gerado)
- Decompor em parts via ProjectDecomposerService (ADS)
- Acompanhar viability_score + decision (pending/approved/rejected)
- Arquivar projects concluídos (preserva audit trail)

## Arquitetura (Wave 16 D4 refatorado)

```
Modules/ProjectMgmt/
├── Http/Controllers/
│   ├── BoardController.php          (thin — usa Modules/Jana/Services/TaskRegistry/TaskCrudService)
│   ├── BacklogController.php        (mesmo padrão)
│   ├── MyWorkController.php         (mesmo padrão)
│   ├── RoadmapController.php
│   ├── ActivityController.php
│   ├── BurndownController.php
│   ├── SearchController.php
│   ├── InstallController.php
│   └── Admin/
│       └── ProjectsController.php   ← REFATORADO D4 Wave 16: usa ProjectService + ProjectMgmtAuditService
├── Services/                         ← NOVO Wave 16
│   ├── ProjectService.php           (CRUD mcp_projects scoped por business_id — D4 SoC)
│   └── ProjectMgmtAuditService.php  (Spatie activity_log "project-mgmt" + PiiRedactor — D7 LGPD)
├── Config/
│   ├── config.php
│   └── retention.php                ← NOVO Wave 16: política LGPD declarativa (D7.c)
└── Tests/Feature/
    ├── BoardControllerTest.php
    ├── ScaffoldProjectMgmtTest.php
    ├── SearchControllerTest.php
    ├── SmokeRoutesProjectMgmtTest.php
    ├── MultiTenantProjectTest.php       (Tier 0 isolation — pre-existente)
    ├── LgpdComplianceTest.php           ← NOVO Wave 16 (D7)
    └── CustomerJourneyTest.php          ← NOVO Wave 16 (D5 smoke E2E)
```

### Padrão Service

Controllers `Admin/ProjectsController` e (em ondas futuras) `BoardController`/`BacklogController` injetam Service via:

```php
private function makeService(Request $request): ProjectService
{
    $businessId = (int) $request->session()->get('user.business_id', 1);
    return app()->makeWith(ProjectService::class, ['businessId' => $businessId]);
}
```

**Por quê `makeWith` em vez de `app()`/auto-resolve?** Service exige `$businessId` no constructor (Tier 0 defense-in-depth — ADR 0093). Container não consegue resolver scalar `int $businessId` sozinho; `makeWith` injeta explícito. Service NUNCA lê `session()` internamente — funciona em fila/job/CLI.

## D7 LGPD compliance (Wave 16)

- **PiiRedactor:** Service de audit aplica `Modules\Jana\Services\Privacy\PiiRedactor::redact()` em toda string livre (description, body de comment, note de event) antes de persistir no `activity_log`. CPF/CNPJ/email/telefone/CEP brasileiro substituídos por `[REDACTED:TIPO]`.
- **Audit trail:** Eventos canônicos no `activity_log` com `log_name='project-mgmt'`. ProjectMgmt **não usa LogsActivity trait** porque depende de Entities Jana (área proibida cross-módulo). Ao invés, `ProjectMgmtAuditService` registra direto na API Spatie.
- **Retention policy:** [`Config/retention.php`](Config/retention.php) declara TTL por entidade (5y projects, 3y tasks, 2y comments, 5y events append-only, 1y inbox notifications). Strategy default `anonymize`.

## Multi-tenant Tier 0 IRREVOGÁVEL

- Todo `ProjectService::*` exige `businessId > 0` no constructor (lança `InvalidArgumentException`)
- Toda query DB usa `where('business_id', $this->businessId)` explícito (defense-in-depth — não confia em global scope)
- Smoke biz=1 vs biz=99 cruzado em [`Tests/Feature/MultiTenantProjectTest.php`](Tests/Feature/MultiTenantProjectTest.php) + [`Tests/Feature/CustomerJourneyTest.php`](Tests/Feature/CustomerJourneyTest.php)
- NUNCA usar biz=4 em test ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) — ROTA LIVRE é cliente prod Larissa)

## Testes

```bash
# Todo o módulo
./vendor/bin/pest Modules/ProjectMgmt/Tests/

# Por dimensão Wave 16
./vendor/bin/pest Modules/ProjectMgmt/Tests/Feature/LgpdComplianceTest.php       # D7
./vendor/bin/pest Modules/ProjectMgmt/Tests/Feature/CustomerJourneyTest.php       # D5
./vendor/bin/pest Modules/ProjectMgmt/Tests/Feature/MultiTenantProjectTest.php   # Tier 0
```

Tests envolvendo `mcp_*` tables são automaticamente skipped em SQLite (env minimal) — exigem MySQL UltimatePOS pra rodar (skip explícito documentado em cada `beforeEach`).

## ADRs relacionadas

- [ADR 0070](../../memory/decisions/0070-jira-style-task-management-current-md-removed.md) — Jira-style tasks (origem do módulo)
- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (§5 SoC brutal informa o refactor D4)
- [ADR 0100](../../memory/decisions/0100-project-mgmt-ui-redesign-roadmap.md) — UI redesign PMG-001..007
- [ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests usam biz=1, NUNCA biz=cliente real

## Histórico
- 2026-05-04: scaffold inicial (promoção de TeamMcp)
- 2026-05-13: UI redesign PMG-001..007
- 2026-05-16 (Wave 16): refactor D4 (ProjectService) + LGPD D7 (retention + audit Service) + D5 smoke E2E (CustomerJourneyTest) + README "Como o cliente usa"
