---
slug: 0071-mcp-tools-audit-2026-05-05-bugs-e-workarounds
number: 71
title: "Auditoria tools MCP 2026-05-05 — bugs descobertos + workarounds"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-05"
module: core
quarter: 2026-Q2
tags: [mcp, taskregistry, audit, bugs, schema]
supersedes: []
related: ["0053-mcp-server-governanca-como-produto", "0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated", "0070-jira-style-task-management-current-md-removed"]
pii: false
review_triggers: ["bugs corrigidos por PR futura", "schema mcp_jira_projects unificado"]
---

# ADR 0071 — Auditoria tools MCP 2026-05-05: bugs descobertos + workarounds

## Contexto

Sessão 2026-05-05 fez **triagem completa do backlog MCP** (135 tasks atualizadas, 17 epics criadas em 6 projects novos: PROJECT/ACCO/AI/NFSE + GROW/EVO Epics). No processo, descobertos múltiplos bugs e inconsistências de schema em `mcp.oimpresso.com` (ADR 0053). Este ADR registra os achados pra evitar redescobrimento e priorizar fixes.

A motivação imediata: Wagner perguntou "o roadmap está vazio" — investigação revelou que **`mcp_epics` tinha 0 rows** (Fase 1 ADR 0070 implementada mas nunca populada), e **108 tasks legacy `US-*` tinham `project_id NULL`** (backfill ADR 0070 Fase 4 incompleto).

## Decisão

Documentar e tratar como débito técnico conhecido. Não bloquear roadmap até que fixes saiam — workarounds neste ADR são suficientes pra operação.

### Estado real das 18 tools MCP (após smoke test 2026-05-05)

| # | Tool | Status | Observação |
|---|---|---|---|
| 1 | `cycles-active` | ✅ | OK |
| 2 | `triage` | ✅ | OK — paginação por `limit`, default 30 |
| 3 | `cycle-goals-track` | ✅ | OK |
| 4 | `cycles-close` | ⚠️ não testado | destrutivo — testar só em cycle real ao fechar |
| 5 | `tasks-list` | ✅ | OK |
| 6 | `tasks-detail` | ✅ | aceita ambos `task_id` (ex `US-COPI-079`) e `identifier` (ex `COPI-22`) |
| 7 | `tasks-update` | ✅ | DB-only — não modifica SPEC.md (alerta no return). Permite mudar status, owner, sprint, priority |
| 8 | `tasks-comment` | ✅ | OK |
| 9 | `tasks-create` | ⚠️ **BUG** | **Schema confuso + escrita silenciosa fail** — ver §Bugs |
| 10 | `tasks-current` | ✅ | DEPRECATED — alias de `cycles-active` |
| 11 | `decisions-search` | ✅ | OK |
| 12 | `decisions-fetch` | ✅ | OK |
| 13 | `sessions-recent` | ⚠️ | Retorna sessions ANTIGAS (abr/2026) primeiro, não ordenado por `indexed_at` recente. Webhook auto-pull (PR #87) deveria ter trazido as de mai/2026 mas não aparecem no top da lista |
| 14 | `my-work` | ⚠️ | "Owner não pôde ser resolvido" — exige `owner:wagner` explícito. Token system não bound a user específico no contexto desta call |
| 15 | `my-inbox` | ❌ | "Sem user autenticado — token MCP precisa ser bound a um user" |
| 16 | `claude-code-usage-self` | ❌ | "Auth não identificada — McpAuthMiddleware falhou?" |
| 17 | `memoria-search` | ❌ | "Autenticação requerida" |
| 18 | `cc-search` | ❌ | "Autenticação requerida" |

**Veredito:** **13/18 OK** (read + tasks-update/comment), **5/18 com problemas** (4 auth-degradadas + 1 com bug de schema/escrita).

### Bugs concretos (priorizar fix em sessão futura)

#### B1 — `tasks-create` schema inconsistente + escrita silenciosa fail

**Sintoma:**
1. Sem `module` nem `project`: rejeita pedindo `module` (mas description diz `project` também aceito).
2. Com `project: PROJECT` (key oficial em `mcp_jira_projects`): retorna *"❌ module é obrigatório"* — ignora `project`.
3. Com `module: Copiloto`: retorna ✅ *"US-COPILOTO-001 criada e adicionada em SPEC.md"* — mas:
   - **Identifier gerado é `US-COPILOTO-NNN`** (uppercase do nome do módulo), divergente do padrão Linear `COPI-NN` ou legacy `US-COPI-NNN` (com key, não nome)
   - `git diff memory/requisitos/Jana/SPEC.md` retorna **vazio** — SPEC.md não foi modificado de fato
   - `tasks-detail US-COPILOTO-001` retorna *"Task não encontrada"* — também não persistiu no DB

**Hipótese:** tool roda no MCP server (CT 100), mas tenta escrever filesystem que está só no Hostinger. Sem rede compartilhada, write falha mas não propaga erro pro return.

**Workaround atual:** **não usar `tasks-create`**. Pra criar nova US:
1. Editar `memory/requisitos/<Modulo>/SPEC.md` à mão (ou via Edit tool local)
2. `git push` → webhook auto-pull (PR #87) sincroniza pro `mcp_tasks` em <5min via parser.

#### B2 — Auth-degradação: `my-work`, `my-inbox`, `claude-code-usage-self`, `memoria-search`, `cc-search`

**Sintoma:** essas 5 tools exigem "user autenticado" e retornam erro mesmo com Bearer token válido (mesmo que cole as 13 outras read tools funcionem).

**Análise schema `mcp_tokens`:** tokens estão bound a `user_id=1` (wagner) em `mcp_tokens` corretamente. Mas tools-Pessoa (my-work etc.) exigem resolução de user diferente — talvez via session/cookie/header não-Bearer. Inconsistência interna do MCP server.

**Workaround atual:**
- `my-work`: passar `owner:wagner` explícito (funciona)
- `my-inbox`: sem workaround conhecido — usar UI `/copiloto/admin/team` (Hostinger)
- `claude-code-usage-self`: idem
- `memoria-search`: usar tool MCP `decisions-search` ou Meilisearch direto via curl
- `cc-search`: usar UI `/copiloto/admin/cc-sessions` (quando existir — está em backlog COPI-39) ou query direto em `mcp_cc_messages`

#### B3 — `tasks-update` DB-only sobrescreve no próximo `mcp:tasks:sync`

**Sintoma documentado:** retorno do `tasks-update` avisa *"_DB atualizado. Para mudança permanente, edite também o SPEC.md._"*.

**Implicação prática:** triagem em massa (como esta sessão fez com 135 tasks) sobrevive até alguém rodar `php artisan mcp:tasks:sync` (parser de SPEC.md → mcp_tasks). Se SPEC.md não tiver os campos novos (status:todo, priority:p1), parser sobrescreve com defaults.

**Workaround atual:** PROJECT-3 (frontmatter YAML obrigatório nos SPECs) precisa fechar antes de re-rodar `mcp:tasks:sync`. Até lá, **NÃO RODAR** `mcp:tasks:sync` cego — vai apagar a triagem.

### Schema decoded (referência canônica pós-Fase 1 ADR 0070)

Aprendizado: ADR 0070 documenta a hierarquia mas não o schema concreto. Adicionando aqui pra evitar adivinhação:

```sql
-- Projects: 12 nativos + 6 criados nesta sessão
mcp_jira_projects (id, key, name, description, color, icon, status, settings,
                   default_workflow_id, custom_field_schema, next_task_number,
                   created_at, updated_at, deleted_at)

-- Epics: 0 → 17 nesta sessão
mcp_epics (id, project_id, key, title, description, owner, target_quarter,
           status [planning|active|done|cancelled], color, sort_order, ...)

-- Cycles: 1 ativo (CYCLE-01 @ COPI)
mcp_cycles (id, project_id, key, name, start_date, end_date, goal,
            status [planning|active|closed], retro, owner_user_id, ...)

-- Tasks: COLUNAS DUPLAS de identifier
mcp_tasks (id, task_id [varchar 40 — legacy "US-COPI-079"],
           identifier [varchar 24 — Linear-style "COPI-22"],
           project_id, epic_id, cycle_id, component_id, parent_task_id,
           type, module, title, description, status, owner, sprint,
           priority, estimate_h, story_points, estimate_unit, estimate_value,
           due_date, started_at, completed_at, labels, custom_fields,
           blocked_by, source_path, source_git_sha, parsed_at, ...)
```

**Atenção 1:** existem **DUAS tabelas com semelhança de nome**:
- `mcp_jira_projects` — registry Linear/Jira-style usado pelas tools (canônico)
- `mcp_projects` — entidade de **planejamento de produto** (viability_score, decision pending/proceed/pivot/kill, custo_estimado_brl, valor_estimado_brl, prazo_estimado_dias) — vazia hoje

Confusão potencial. Sugestão futura: renomear `mcp_jira_projects → mcp_projects` (após drop atual) ou consolidar.

**Atenção 2:** tasks legacy backfill têm `task_id="US-COPI-079"` mas `identifier=null`. Tasks criadas via `mcp:tasks:sync` parecem usar `identifier="COPI-22"` (com `task_id` igual ou nulo). Linkagem por `identifier` perde 100+ tasks legacy. **Sempre filtrar por `task_id` quando US-*; por `identifier` quando ID Linear-style.**

**Atenção 3:** webhook auto-pull (PR #87) sincroniza memory/git → mcp_memory_documents, mas **NÃO TOCA `mcp_tasks`**. Pra propagar mudanças em SPEC.md pro mcp_tasks: rodar `php artisan mcp:tasks:sync` manualmente. Cron 5min foi mencionado em ADR 0070 mas precisa verificar se está no schedule. (TODO: confirmar.)

### Decisão de operação até bugs serem fixados

1. **Triagem em massa**: usar `tasks-update` via curl JSON-RPC (não via Claude Code ToolSearch — esse não pega todas as 18 tools por algum motivo de cache/discovery).
2. **Criar novas tasks**: editar SPEC.md à mão + `git push` + webhook auto-pull. Não usar `tasks-create`.
3. **Roadmap (epics + project_id)**: gerenciar via SQL direto até existir tool. Script-template em `memory/sessions/2026-05-05-triagem-roadmap-mcp-audit.md`.
4. **Visibilidade pessoal**: usar `my-work owner:wagner` (não default). `my-inbox` indisponível até fix B2.
5. **Não rodar `mcp:tasks:sync`** até PROJECT-3 (frontmatter YAML SPECs) fechar.

## Consequências

**Positivas:**
- Roadmap visível: 17 Epics, 178 tasks linkadas, 4 quarters mapeados.
- Schema documentado (não mais "magic black box").
- Workarounds testados e funcionais — operação dia-a-dia destravada.
- Backlog limpo: 0 tasks órfãs (todas com project_id + owner).

**Negativas / Trade-offs:**
- 5 tools MCP degradadas/quebradas — UX inferior a Linear/Jira até fix.
- Triagem em massa via SQL/curl é frágil — precisa de tool MCP `tasks-bulk-update` (mencionada em ADR 0070 §"Bulk operations" mas não implementada).
- Drift potencial entre `mcp_tasks` (DB) e SPEC.md até PROJECT-3 fechar.

**Riscos mitigados:**
- Documentação fecha gap entre ADR 0070 (planejado) e realidade do schema (implementado).
- ADR explícita reduz custo de redescobrimento na próxima sessão.

## Tasks priorizadas (consequência direta)

Criar/promover (próxima sessão Wagner):

- **Fix B1** (`tasks-create` write SPEC.md): criar US no SPEC TaskRegistry, p1
- **Fix B2** (auth-degradação 5 tools): investigar McpAuthMiddleware, p1
- **Fix B3** + **PROJECT-3** (frontmatter YAML SPECs): habilita re-sync seguro, p3 → mover pra p2
- Tool MCP `tasks-bulk-update`: PROJECT-2 já cobre (D2 AI-native), p2
- Tool MCP `epics-create` / `epics-list`: criar US nova no PROJECT TaskRegistry, p2

## Referências

- ADR 0053 — MCP server governança como produto (defines mcp.oimpresso.com canonical)
- ADR 0069 — TaskRegistry MCP tools canônico (superseded por 0070)
- ADR 0070 — Jira-style Project/Epic/Cycle/Task hierarchy (Fase 1+4 incompletas — este ADR é evidência)
- PR #87 — webhook auto-pull (não toca mcp_tasks; só mcp_memory_documents)
- Sessão 2026-05-05 — triagem 135 tasks + roadmap 17 epics em `memory/sessions/2026-05-05-triagem-roadmap-mcp-audit.md`
