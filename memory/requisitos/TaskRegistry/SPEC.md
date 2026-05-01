# TaskRegistry — SPEC

> **Status**: Fase 0 em implementação (2026-04-30)
> **Owner**: Wagner [W]
> **Goal**: substituir TASKS.md ASCII por sistema de tasks queryable via MCP, sem perder git como SoT

## Visão

Sistema de tasks "Jira-like" nativo do MCP server oimpresso:
- Source-of-truth = git (SPECs canônicas em `memory/requisitos/<Mod>/SPEC.md`)
- Cache governado = `mcp_tasks` table (via webhook GitHub, idêntico ao `mcp_memory_documents`)
- Acesso = MCP tools (`tasks-list`, `tasks-detail`, futuro `tasks-create/update`)
- UI = `/copiloto/admin/tasks` Kanban (Fase 2)

## Format canônico de US no SPEC.md

```markdown
### US-NFSE-001 · Pesquisa fiscal Tubarão

> owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: todo
> blocked_by: —

- [ ] Confirmar SN-NFSe vs ABRASF
- [ ] Cadastrar conta provider
- [ ] Documentar resultado em PESQUISA_TUBARAO.md
```

Regras parser:
- Heading `### US-NNN-MMM` (sem case sensitive — UPPER preferido)
- Linha após heading começa com `> ` (blockquote) e tem chaves `key: value` separadas por ` · `
- Description = bullets/texto entre frontmatter e próximo `### `
- Status default = `todo` se ausente
- Priority default = `p2`

## Fase 0 — read-only (Esta entrega)

### User stories

### US-TR-001 · Tool tasks-list com filtros

> owner: wagner · sprint: F0 · priority: p0 · estimate: 4h · status: done
> blocked_by: —

Como Eliana, quero rodar `tasks-list --owner=eliana --module=NFSe` no Claude e ver meu backlog em <2s sem ler SPEC à mão.

### US-TR-002 · Tool tasks-detail por ID

> owner: wagner · sprint: F0 · priority: p0 · estimate: 2h · status: done
> blocked_by: —

Como Wagner, quero `tasks-detail US-NFSE-001` mostrar título + descrição + status + dependencies + path do source.

### US-TR-003 · Parser SPEC idempotente

> owner: wagner · sprint: F0 · priority: p0 · estimate: 4h · status: done
> blocked_by: —

Como sistema, quero parser idempotente que sync ATUAL (não acumula registros velhos quando US é deletada do SPEC).

### US-TR-004 · Webhook GitHub dispara mcp:tasks:sync

> owner: wagner · sprint: F0 · priority: p1 · estimate: 2h · status: done
> blocked_by: US-TR-003

Hoje webhook só sincroniza `mcp_memory_documents`. Adicionar gatilho pra `mcp:tasks:sync` após push em `memory/requisitos/*/SPEC.md`.

### US-TR-005 · Tools CRUD (tasks-create/update/comment)

> owner: wagner · sprint: F1 · priority: p1 · estimate: 8h · status: done
> blocked_by: US-TR-004

CRUD via MCP: criar US (gera entry no SPEC + commita), update status, comentários (DB-only).

### US-TR-006 · Tabelas comments + events (audit)

> owner: wagner · sprint: F1 · priority: p1 · estimate: 4h · status: done
> blocked_by: US-TR-003

Migrations `mcp_task_comments` + `mcp_task_events` pra timeline e audit completo.

### US-TR-007 · UI Kanban /copiloto/admin/tasks

> owner: wagner · sprint: F2 · priority: p2 · estimate: 12h · status: todo
> blocked_by: US-TR-005

Tela Inertia com Kanban (todo/doing/review/done) drag-drop + Backlog filtros + Burndown.

### Acceptance criteria

- [ ] Migration `mcp_tasks` com colunas conforme ADR §Schema
- [ ] Model `McpTask` com casts (`blocked_by` JSON array, enums status/priority)
- [ ] `TaskParserService::parseSpec(string $path): Collection<TaskCandidate>`
- [ ] `TaskParserService::syncAll(): SyncReport` percorre `memory/requisitos/*/SPEC.md`
- [ ] Comando `php artisan mcp:tasks:sync [--module=X] [--dry-run]`
- [ ] Tool `tasks-list` com filtros: `owner`, `module`, `sprint`, `status`, `priority`, `limit`
- [ ] Tool `tasks-detail` aceita `task_id` exato
- [ ] Idempotente: rodar 2x sem mudança = 0 inserts/updates
- [ ] Idempotente: US deletada do SPEC vira `status=cancelled` (soft) — não DELETE físico
- [ ] Pest test cobertura: parser regex + sync idempotência + tool list + tool detail
- [ ] Auth: tools requerem scope `cc.read.team` (mesma do cc-search)
- [ ] Audit: chamadas tool gravadas em `mcp_audit_log` (já automático via middleware)

### Schema Fase 0

```sql
CREATE TABLE mcp_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id VARCHAR(40) NOT NULL UNIQUE,           -- US-NFSE-001
  module VARCHAR(60) NOT NULL,                   -- NFSe
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('todo','doing','review','done','blocked','cancelled') NOT NULL DEFAULT 'todo',
  owner VARCHAR(60) NULL,
  sprint VARCHAR(40) NULL,
  priority ENUM('p0','p1','p2','p3') NULL DEFAULT 'p2',
  estimate_h DECIMAL(5,1) NULL,
  blocked_by JSON NULL,
  source_path VARCHAR(500) NOT NULL,
  source_git_sha VARCHAR(40) NULL,
  parsed_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX idx_module_status (module, status),
  INDEX idx_owner_status (owner, status),
  INDEX idx_sprint (sprint),
  INDEX idx_priority (priority)
);
```

## Fase 1 (futuro)

CRUD via MCP tools:
- `tasks-create` (gera US no SPEC + commita)
- `tasks-update --status=doing`
- `tasks-comment "iniciei pesquisa"` (DB-only)
- `tasks-block --by=US-NFSE-001`

Tabelas adicionais: `mcp_task_comments`, `mcp_task_events`.

## Fase 2 (futuro)

UI `/copiloto/admin/tasks`:
- Kanban view (todo/doing/review/done) com drag-drop
- Backlog com filtros
- Burndown chart por sprint

## Refs

- [ADR ARQ-0001](adr/arq/0001-mcp-native-vs-plane.md)
- ADR 0053 (MCP server governança)
- `mcp_memory_documents` (template padrão sync git→DB)
- `OimpressoMcpServer.php` (registrar nova tool)
