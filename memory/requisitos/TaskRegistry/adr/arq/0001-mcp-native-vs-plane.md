# ADR ARQ-0001 (TaskRegistry) · Sistema de tasks MCP-native, não Plane self-host

- **Status**: accepted
- **Data**: 2026-04-30
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ADR 0053 (MCP server governança), ADR 0061 (zero auto-mem)

## Contexto

O projeto cresceu além do que `TASKS.md` + `CURRENT.md` + SPECs cobrem:
- 14 user stories US-NFSE-001..014 espalhadas em SPEC NFSe
- Cycle 01 com 5+ tasks ativas Wagner solo
- 5 devs (Wagner/Maíra/Felipe/Luiz/Eliana) precisam ver "o que é meu" sem ler 30 arquivos
- Eliana abre Claude Code amanhã pra atacar NFSe — quer `tasks-list owner=eliana` direto via MCP, não ler SPEC.md à mão
- Comments/timeline de progresso não cabem em git (atualiza alta frequência, polui diff)

Padrão atual: TASKS.md ASCII tables. Limites:
- Sem query (filtrar por owner exige ler tudo)
- Sem audit de mudança de status (só git blame)
- Sem dependencies expressas
- Sem sprints estruturados
- Sem visualização kanban

## Decisão

**Tabelas próprias no MCP server (`mcp_tasks` + futuras `mcp_task_comments`/`mcp_task_events`) + tools MCP nativas.** NÃO usar Plane.so / OpenProject / Tegon Cinder.

### Razão de NÃO escolher Plane self-host

| Critério | Plane self-host | MCP nativo |
|---|---|---|
| Setup | docker-compose + postgres + redis + 4h config + manutenção | 0 (usa MCP server existente) |
| Sync git ↔ tasks | construir adapter REST | webhook GitHub já existe (mesmo padrão `mcp_memory_documents`) |
| Tools MCP pros agentes | construir wrapper REST→MCP | nativo |
| Audit | logs Plane proprietários | git diff + `mcp_audit_log` (já existe) |
| Custom workflow | sim, complexo | trivial enum |
| UI | ⭐⭐⭐⭐⭐ pronto | construir simples (Cockpit AppShellV2 reusa) |
| LGPD/self-host | ✅ | ✅ |
| Vendor lock | médio | zero |
| Custo total 1 ano | 0 + dor de integração + 1 container 24/7 | 3.5 dias dev |

Decisão: paga-se 3.5 dias pra ficar integrado, sem dual SoT, com tools MCP nativas que agentes consomem direto.

### Source-of-truth = git (igual `mcp_memory_documents`)

`memory/requisitos/<Mod>/SPEC.md` continua sendo SoT. DB é cache governado.

User stories no SPEC vão ter formato padronizado (heading + frontmatter inline):

```markdown
### US-NFSE-001 · Pesquisa fiscal Tubarão

> owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: todo
> blocked_by: —

- [ ] Confirmar SN-NFSe vs ABRASF
- [ ] Cadastrar conta provider
...
```

Parser PHP extrai:
- `task_id` = US-NFSE-001
- `module` = NFSe (do path)
- `title` = "Pesquisa fiscal Tubarão"
- `owner/sprint/priority/estimate/status/blocked_by` = quote-line frontmatter
- `description` = bullets do checklist
- `source_path` = `memory/requisitos/NFSe/SPEC.md#US-NFSE-001`
- `source_git_sha` = HEAD do push

Sync: webhook GitHub → comando `mcp:tasks:sync` (similar ao já existente `IndexarMemoryGitParaDb`).

### Schema Fase 0 (read-only)

```sql
CREATE TABLE mcp_tasks (
  id            BIGINT PK AUTO,
  task_id       VARCHAR(40) UNIQUE,
  module        VARCHAR(60) INDEX,
  title         VARCHAR(255),
  description   TEXT,
  status        ENUM('todo','doing','review','done','blocked','cancelled') INDEX,
  owner         VARCHAR(60) INDEX NULLABLE,
  sprint        VARCHAR(40) NULLABLE,
  priority      ENUM('p0','p1','p2','p3') NULLABLE,
  estimate_h    DECIMAL(5,1) NULLABLE,
  blocked_by    JSON NULLABLE,         -- ["US-NFSE-001"]
  source_path   VARCHAR(500),
  source_git_sha VARCHAR(40) NULLABLE,
  parsed_at     TIMESTAMP,
  created_at, updated_at,
  INDEX (module, status), INDEX (owner, status)
);
```

Fase 1 (futuro): `mcp_task_comments` (autoria/source) + `mcp_task_events` (audit).

### Tools MCP Fase 0

| Tool | Função | Auth |
|---|---|---|
| `tasks-list` | Filtrar por owner/module/sprint/status/priority | `cc.read.team` (visível aos colegas) |
| `tasks-detail` | Detalhe de 1 task pelo ID | `cc.read.team` |

Fase 1: `tasks-create`, `tasks-update`, `tasks-comment`, `tasks-block`.

## Consequências

### Positivas
- Eliana abre Claude amanhã: `tasks-list --owner=eliana` → backlog dela direto, sem ler SPEC
- Audit total via git diff (status muda → commit no SPEC) + `mcp_task_events` (Fase 1)
- Reusa toda infra MCP existente: webhook, audit_log, governance
- Sem outro container, sem outra DB, sem outro auth
- Custo: 1 dia (Fase 0) + 1 dia (Fase 1) + 1.5 dia (UI Kanban) = 3.5 dias total stack completa

### Negativas
- Construir UI custa 1 dia (mas Cockpit AppShellV2 já tem peças)
- Parser SPEC precisa ser robusto (regex testada com Pest)
- Comments só DB (não git) = pequena área cinza de governança — mitigação: comments têm `actor` + timestamp, viraram parte do `mcp_task_events`

## Alternativas consideradas

- **Plane.so self-host** — REJEITADO: dual SoT, container extra, sem MCP nativo
- **OpenProject** — REJEITADO: pesado, UX inferior, mesmas issues do Plane
- **NocoDB** — REJEITADO: você constrói "Jira from scratch" sem ganho vs MCP nativo
- **Tegon Cinder** — REJEITADO: imaturo (mid-2025), tracker mudando rápido
- **Ficar com TASKS.md** — REJEITADO: já não escala, sem queries, sem dependencies

## Plano de implementação

**Fase 0 (esta ADR — 1 dia):**
1. Migration `mcp_tasks`
2. Model `McpTask`
3. Service `TaskParserService` + comando `mcp:tasks:sync`
4. Tools `TasksListTool` + `TasksDetailTool`
5. Pest tests golden
6. Atualizar webhook handler pra disparar sync após push

**Fase 1 (futuro — 1 dia):**
1. Migration `mcp_task_comments` + `mcp_task_events`
2. Tools `tasks-create/update/comment/block`
3. Sync inverso (DB → SPEC.md auto-commit)

**Fase 2 (futuro — 1.5 dia):**
1. UI `/copiloto/admin/tasks` Kanban + Backlog views
2. Drag-drop muda status
3. Filtros por owner/module/sprint
4. Burndown chart

## Refs

- ADR 0053 (MCP server governança)
- `mcp_memory_documents` migration (template canônico)
- `OimpressoMcpServer.php` (registro de tools)
- `IndexarMemoryGitParaDb` service (padrão de sync git→DB)
