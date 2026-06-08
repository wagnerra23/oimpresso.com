---
name: MCP server — 5 PRs consolidados 2026-05-13 (62% → roadmap 88%)
description: Sessão nervous-mayer-3ff0da entregou 5 PRs em main fixing 4 bugs sync MCP + 2 auditorias estado-da-arte. ADR 0144 aceita. 6 agents paralelos coordenados.
type: project
---
## 5 PRs mergeados 2026-05-13 (manhã, 1 sessão)

| PR | Título | Branch |
|---|---|---|
| [#745](https://github.com/wagnerra23/oimpresso.com/pull/745) | docs(jana): 4 bugs MCP + 2 auditorias estado-da-arte | claude/jana-mcp-audit-docs |
| [#746](https://github.com/wagnerra23/oimpresso.com/pull/746) | fix(jana-mcp): regex GitTaskLinker aceita `(US-X)` parentético — Bug #1 | claude/jana-mcp-bug-1-regex |
| [#747](https://github.com/wagnerra23/oimpresso.com/pull/747) | feat(jana-mcp): DB canon + SPEC template + ADR 0144 — Bug #2 | claude/jana-mcp-bug-2-db-canon |
| [#748](https://github.com/wagnerra23/oimpresso.com/pull/748) | feat(jana-mcp): inbox mark_read default + TTL 7d job — Bug #3 | claude/jana-mcp-bug-3-inbox-ttl |
| [#749](https://github.com/wagnerra23/oimpresso.com/pull/749) | feat(jana-mcp): stale task detection command + tool — Bug #4 | claude/jana-mcp-bug-4-tasks-health |

## O que muda em prod

- **Auto-close de tasks via commit:** `(US-WA-042)` parentético agora dispara `closes` em branch main + status `done`. Convenção real do projeto finalmente casa com regex (até agora era 0 hits histórico).
- **`tasks-update` durável:** mudanças via MCP não são mais sobrescritas por webhook. SPEC.md = template; DB = estado vivo.
- **Inbox limpa automática:** `my-inbox` default `mark_read=true` (consume on read). Job daily 04:00 BRT marca read tudo unread >7d.
- **Stale detection daily 06:20 BRT:** command + tool MCP `tasks-health` flagga `stale_todo >21d`, `stale_blocked >30d`, `stale_doing >7d sem commit`, `stale_review >5d`.
- **ADR 0144** aceita por Wagner 2026-05-13. Supersedes_partially `0070`.

## Agents paralelos coordenados

6 agents disparados em paralelo via `Agent` tool background. Áreas isoladas → sem conflito. Wagner mergeou todos 5 PRs com admin merge sem CI esperar.

| Agent | Foco | Output |
|---|---|---|
| mcp-quality-expert | Comparativo PM/MCP (Linear/Jira/Plane/...) | COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md — 62% maturidade |
| knowledge-architecture-expert | PKM/RAG/Second Brain (Notion/Obsidian/Mem0/Letta) | AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md — 73%, recomenda CONSOLIDAR |
| Bug #1 fix regex | GitTaskLinkerService + 2 Pest | 8/8 regex-puros passed |
| Bug #2 DB canon + ADR | TaskParserService + TasksUpdateTool + ADR 0144 | 30/30 smoke standalone (Pest live bloqueado junction) |
| Bug #3 inbox TTL | MyInboxTool + InboxAutoCleanupJob + Kernel | 11/11 Pest passed |
| Bug #4 stale detection | McpTasksHealthCheckCommand + TasksHealthTool + Kernel | 10/10 Pest passed (33 assertions) |

## Pegadinhas aprendidas

1. **Agent pode alucinar Write** — `knowledge-architecture-expert` 1ª tentativa reportou criar artefato mas `find -newer` mostrou que não existia. Re-spawn com instrução irrevogável: rodar `ls -la` + `wc -l` no path EXATO após Write e incluir output como prova.
2. **Junction `vendor/` worktree** quebra autoload em alguns casos — Pest live falhou pra Bug #2 + dump-autoload deu fail. Validação Pest pós-merge precisa rodar em D:\oimpresso.com\ branch main (com vendor real) ou CT 100.
3. **Kernel.php compartilhado entre 2 PRs** — Bug #3 e Bug #4 adicionam schedules em regiões próximas. Solução: incluir cada chunk em PR separado via Edit manual (`git add -p` interactive não funciona em script). Mergeei #748 antes de #749 pra evitar conflict, mas chunks eram independentes.
4. **`gh pr merge --admin` sequência** — Wagner mergeou 4 em paralelo, 1 sequencial (que dependia outro). Funcionou.

## Pendência pós-merge

- Rodar Pest do Bug #2 contra vendor real (D:\oimpresso.com\ checkout main OU CT 100)
- 1º PR mergeado pós-fix Bug #1 deve auto-fechar suas próprias US (validação live do regex)
- `my-inbox` deve ficar limpo em 24h após cron 04:00 rodar pela 1ª vez
- ADR 0144 status `accepted` em main commit `chore(adr-0144): status accepted após aprovação Wagner 2026-05-13`

## Refs

- BUGS-MCP-SYNC-2026-05-13.md — catálogo 4 bugs
- COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md — 62% vs Linear/Jira/etc
- AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md — 73% vs Notion/Obsidian/Mem0/etc
- ADR 0144 (decisions/0144-tasks-db-canonico-spec-template.md)
- Agents: `.claude/agents/mcp-quality-expert.md` + `.claude/agents/knowledge-architecture-expert.md`
