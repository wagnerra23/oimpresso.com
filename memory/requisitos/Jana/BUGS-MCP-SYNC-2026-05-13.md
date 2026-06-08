# MCP server — bugs de sincronização catalogados (2026-05-13)

> **Contexto:** Sessão `nervous-mayer-3ff0da` 2026-05-13 madrugada — Wagner reportou "MCP desincronizado, muitos bugs". Sincronizamos 9 US done manualmente via `tasks-update`, descobrimos várias outras já marcadas (assignment notifications da inbox enganaram). Investigação revelou 4 bugs root cause.

## Resumo executivo

| # | Bug | Severidade | Impacto | Fix proposto |
|---|---|---|---|---|
| 1 | Auto-close via commit nunca dispara | 🔴 P0 | 100% US ficam `todo` pós-merge | Atualizar regex `GitTaskLinkerService` pra aceitar `(US-X)` parentético OU enforce padrão via skill `commit-discipline` |
| 2 | `tasks-update` é DB-only, SPEC.md sobrescreve | 🟡 P1 | Mudança via MCP some no próximo webhook | Bi-directional sync OU disclaimer explícito |
| 3 | Inbox notifications acumulam sem TTL/cleanup | 🟡 P1 | 33+ stale assignment notifications (1 sem lidas) | Auto-mark_read após X dias OU mark_read default true |
| 4 | Sem auto-rollover/inactive detection | 🟢 P2 | 30 tasks ativas mas só 2 doing real | Job daily reclassifica `todo` >30d sem update pra `cancelled`/`blocked` |

## Bug #1 — Auto-close via commit nunca dispara (CRÍTICO)

### Evidência

[Modules/Jana/Services/TaskRegistry/GitTaskLinkerService.php:38](../../../../../Modules/Jana/Services/TaskRegistry/GitTaskLinkerService.php#L38) define regex:

```php
public const REF_PATTERN = '/(refs|fixes|closes|resolves|fix|close|resolve):?\s+([A-Z]{2,8})-(\d+)/i';
```

Mas convenção real de commits oimpresso (verificado em `git log --grep`):

- ✅ `feat(whatsapp): mídia outbound (US-WA-042) [W] (#707)` — **parentético**, regex NÃO casa
- ❌ Quase nenhum commit usa `Closes US-WA-042` ou `Fixes US-WA-042`

`grep "closes US-"` em todo histórico: 1 hit (US-NFE-061). `grep "fixes US-"`: 0 hits.

### Impacto

- 100% dos PRs mergeados não fecham task automaticamente
- Wagner precisa rodar `tasks-update task_id:X status:done` manualmente pra cada uma
- US ficam acumulando em status `todo`/`review` enquanto na real estão done

### Fix

**Opção A (preferida):** Aceitar padrão parentético no regex:
```php
public const REF_PATTERN = '/(?:(refs|fixes|closes|resolves|fix|close|resolve):?\s+|[\(\[])([A-Z]{2,8})-(\d+)(?:[\)\]])?/i';
```

Inferir action por contexto:
- `(US-XXX)` em commit message: action=`closes` se branch=main, `pr_merged` se PR
- Manter `closes/fixes` explícito como override

**Opção B:** Enforce padrão `Closes US-XXX` via hook pre-commit + skill `commit-discipline`. Mais conservador mas exige mudança de hábito.

**Recomendação:** A — regex é fix de 1 linha em código, escala pra todo histórico futuro sem custo humano.

## Bug #2 — `tasks-update` DB-only sobrescreve no próximo webhook

### Evidência

[Modules/Jana/Mcp/Tools/TasksUpdateTool.php:26](../../../../../Modules/Jana/Mcp/Tools/TasksUpdateTool.php#L26) descrição:

> "DB-only, NÃO modifica o SPEC.md. (...) O próximo sync do SPEC sobrescreve — para mudança permanente, edite o SPEC.md."

### Impacto

- Fluxo natural "marquei done via MCP" tem TTL imprevisível (próximo webhook GitHub→MCP sync)
- Auditoria fica inconsistente (audit log diz `done`, mas SPEC.md diz `todo` → sync devolve pra `todo`)
- Tasks "fantasmas" reaparecem após webhook

### Fix

**Opção A:** Bi-directional sync — `tasks-update status:done` faz commit no SPEC.md via API GitHub (autor: `wagner-via-mcp`) + push automático.

**Opção B:** Source-of-truth dual — `mcp_tasks.status` é canon, SPEC.md é doc descritivo. Webhook só cria tasks novas (SPEC tem US-XXX que DB não tem), nunca sobrescreve status.

**Opção C:** Status do DB tem `manual_override` flag. Webhook só sobrescreve se `manual_override=false`.

**Recomendação:** B — mais alinhado com ADR 0070 (Jira-style task management). SPEC.md vira **template** de US (descrição/acceptance), DB é **estado vivo**.

## Bug #3 — Inbox notifications acumulam sem TTL

### Evidência

`my-inbox` Wagner: 44 notifications, 33 do tipo "Atribuiu US-X pra você" com `1 week ago` — vieram do bootstrap inicial quando ADR 0070 foi rodado, nunca foram lidas.

[Modules/Jana/Mcp/Tools/MyInboxTool.php:64](../../../../../Modules/Jana/Mcp/Tools/MyInboxTool.php#L64): filtro `where('created_at', '>', now()->subDays(30))` — TTL 30d existe mas notificações de 1 semana ainda passam, e ficam acumulando até virarem stale.

### Impacto

- `my-inbox` retorna 44 items, 90% são noise antigo
- Difícil ver assignments reais novos no meio
- Wagner pediu "skill `my-inbox`" mas resultado é inutilizável sem `mark_read=true` toda vez

### Fix

**Opção A:** Auto-mark_read após N dias (ex: 7d). Job daily varre `unread AND created_at < 7d ago` → marca read.

**Opção B:** `MyInboxTool` default `mark_read=true` — lê = consome, igual email inbox tradicional.

**Opção C:** Brief diário (`brief-fetch`) já consome notifications automaticamente.

**Recomendação:** A + B combinadas — UX inbox tem que ser "consumível", senão fica abandono garantido.

## Bug #4 — Sem auto-rollover/inactive detection

### Evidência

`my-work` Wagner: 30 tasks ativas, mas só **2 em DOING** real. As outras 28:
- 9 BLOCKED (6 dormentes Trilha Gold sem sinal cliente há semanas)
- 19 TODO (vários sem update há 2+ semanas)

Comportamento Linear/Jira moderno: tasks `todo` sem update por X semanas → auto-`cancelled` ou alerta "está sem dono real?"

### Impacto

- Backlog visualmente inflado: 30 ≠ trabalho real em voo
- Sinal "muito a fazer" vira ruído quando 80% é dormente
- Métrica `dashboard-velocity` distorcida

### Fix

**Opção A:** Job daily `mcp:tasks:health-check`:
- `todo` >21d sem update → comment "está sem update há 21d. Cancela ou prioriza?"
- `blocked` >30d → propor `tasks-update status:cancelled` (cleanup)
- `doing` sem commit linkado >7d → flag stale

**Opção B:** Painel `/copiloto/admin/tasks/health` com 4 colunas: vivas (committed recente) · paradas · dormentes · cancelar.

**Recomendação:** A — job + alerta, sem precisar UI extra.

## Próximos passos

- **Imediato (Wagner aprova):** Bug #1 fix regex — 1 commit, 1 linha, testado com Pest sobre últimos 50 commits
- **Curto (esta semana):** Bug #3 fix mark_read default true ou TTL 7d
- **Médio (após CYCLE-05):** Bug #2 e #4 — exigem ADR amend do 0070 ou nova ADR

## Tasks MCP propostas pra criar

1. **US-MCP-001** `p0` — Fix regex `GitTaskLinkerService` aceitar `(US-XXX)` parentético + Pest sobre últimos 50 commits (≈3h)
2. **US-MCP-002** `p1` — `my-inbox` default `mark_read=true` + TTL 7d auto-cleanup job (≈4h)
3. **US-MCP-003** `p1` — ADR amend 0070: SPEC.md = template, DB = estado vivo. Sync webhook só cria, não sobrescreve status (≈6h + Wagner aprova ADR)
4. **US-MCP-004** `p2` — Job `mcp:tasks:health-check` daily flagga stale (todo >21d, blocked >30d, doing >7d sem commit) (≈3h)

---

**Próximo agente:** spawnar `mcp-quality-expert` pra comparativo MCP oimpresso vs estado-da-arte (Linear, Jira Cloud, GitHub Projects, Plane, Tegon, Vikunja) + % maturidade + roadmap.
