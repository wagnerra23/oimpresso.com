---
slug: 0144-tasks-db-canonico-spec-template
number: 144
title: "TaskRegistry — DB é canon de estado vivo, SPEC.md é template descritivo"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: "2026-05-13"
accepted_at: 2026-05-13
decided_by: [W]
module: governance
supersedes_partially: [0070-jira-style-task-management-current-md-removed]
superseded_by: []
related: [0053-mcp-server-governanca-como-produto, 0070-jira-style-task-management-current-md-removed, 0093-multi-tenant-isolation-tier-0]
tags: [governance, tasks, mcp, taskregistry, sync, webhook]
---

# ADR 0144 — TaskRegistry: DB é canon de estado vivo, SPEC.md é template descritivo

## Status

Proposto — 2026-05-13. Aguardando aprovação Wagner.

Amend parcial de [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — preserva
hierarquia Jira/Linear e tools MCP, mas inverte a regra "SPEC.md é source-of-truth do status".

## Contexto

[ADR 0070](0070-jira-style-task-management-current-md-removed.md) (2026-05-04) declarou que o source-of-truth da existência
e do estado inicial de cada US é `memory/requisitos/<Mod>/SPEC.md`, sincronizado pelo `TaskParserService`
via webhook GitHub → MCP server. Tools MCP (`tasks-update`, `tasks-comment`, etc) operam sobre o DB
`mcp_tasks` mas — pela política original — o DB era cache derivado: qualquer divergência era resolvida
re-sincronizando do SPEC.

A descrição da tool `tasks-update` deixava isso explícito:

> "DB-only, NÃO modifica o SPEC.md. (...) O próximo sync do SPEC sobrescreve — para mudança permanente,
> edite o SPEC.md."

Em uso real (sessão `nervous-mayer-3ff0da` 2026-05-13, catalogada em
[BUGS-MCP-SYNC-2026-05-13.md](../requisitos/Jana/BUGS-MCP-SYNC-2026-05-13.md) — Bug #2), descobrimos
que esse contrato gera duas patologias:

1. **TTL imprevisível de mudanças via MCP.** Wagner roda `tasks-update task_id:US-XXX-NNN status:done`
   após mergear um PR. Funciona — mas só até o próximo push em `main` que toque qualquer SPEC.md.
   O webhook dispara `mcp:tasks:sync` → `TaskParserService::syncAll()` → re-lê o SPEC → faz
   `$existente->update($cand)` com **todos os campos**, incluindo status. A US volta pra `todo`.

2. **Auditoria fica inconsistente.** `mcp_task_events` registra `status: todo → done` (evento real,
   author=wagner, timestamp). Próximo sync registra `status: done → todo` (author=system, source=parser).
   "Task fantasma" reaparece sem que alguém tenha pedido.

Workaround atual: editar manualmente o SPEC.md também a cada mudança via MCP. Custo: duplica fricção
("já não bastava editar a tabela markdown? Para que serve a tool então?"), confunde a separação que
[ADR 0070](0070-jira-style-task-management-current-md-removed.md) tinha justamente tentado criar (Jira-style vs markdown).

O fluxo natural do time — descoberto na prática — é:
- SPEC.md = **documentação**: descrição, acceptance criteria, contexto da US, labels, dependências
- DB `mcp_tasks` = **kanban vivo**: status, owner, sprint, priority, blocked, started_at, completed_at

Linear, Jira Cloud, GitHub Projects v2 funcionam exatamente assim: existe um doc que define o que a
issue É (descrição estática), e existe um issue tracker que captura o que a issue ESTÁ FAZENDO
(estado dinâmico). Misturar as duas no mesmo source-of-truth foi o erro.

## Decisão

**`mcp_tasks` é canônico para estado vivo. SPEC.md é template descritivo.**

`TaskParserService::syncAll()` muda o comportamento de UPDATE em tasks já existentes no DB:

| Campo | Origem após webhook |
|---|---|
| `title` | SPEC.md (refletido) |
| `description` | SPEC.md (refletido) |
| `labels` | SPEC.md (refletido) |
| `type` | SPEC.md (refletido) |
| `module` | SPEC.md (refletido) |
| `project_id`, `epic_id`, `cycle_id`, `component_id` | SPEC.md (refletido) |
| `blocked_by`, `due_date`, `estimate_h`, `story_points`, `estimate_unit`, `estimate_value` | SPEC.md (refletido) |
| `identifier`, `custom_fields`, `source_path`, `source_git_sha`, `parsed_at` | SPEC.md / sistema |
| **`status`** | **DB (preservado)** |
| **`owner`** | **DB (preservado)** |
| **`sprint`** | **DB (preservado)** |
| **`priority`** | **DB (preservado)** |

Tasks que ainda **não existem** no DB no momento do sync continuam recebendo todos os valores
diretamente do SPEC.md, incluindo `status` inicial (default: `todo`). Isso preserva a UX de criar
US nova editando o SPEC.

Adicionalmente, a lógica de cancelamento órfão (`whereNotIn('task_id', $reportadasNoSync)`) deixa
de regredir tasks `done` pra `cancelled`. Remover a entrada do SPEC pós-merge é fluxo de limpeza
normal — não deve apagar o histórico de uma US concluída.

### Auditoria

Toda vez que o sync detecta divergência entre estado vivo no DB e no SPEC, registra log estruturado
no canal `copiloto-ai`:

```
TaskParser preservou estado vivo DB (ADR 0144)
  task_id: US-XXX-NNN
  preservados:
    status: { db: 'done', spec: 'todo' }
  fonte: webhook-sync
```

Permite detectar drift sistemático (ex: SPEC nunca atualizado pós-merge) sem ruído de queries.

### Tool description ajustada

`Modules/Jana/Mcp/Tools/TasksUpdateTool.php` passa a anunciar que a mudança é **durável**, não DB-only-com-TTL.
Remove a recomendação "edite também o SPEC.md".

## Consequências

### Positivas

- **`tasks-update` virou durável** — encerra o ciclo de surpresa "marquei done mas ressuscitou".
- **SPEC.md vira menos hot** — não precisa editar a cada `status: doing → done`. Só edita pra
  mudar descrição/acceptance/labels — coisas que merecem revisão em PR.
- **Auditoria limpa** — `mcp_task_events` deixa de receber eventos "system: done → todo" que
  poluíam o histórico.
- **Convergência com tooling profissional** — Linear/Jira/GitHub Projects todos operam assim.
- **Custo de implementação trivial** — uma função (`extrairCamposDescritivos`) + uma constante
  (`LIVE_STATE_FIELDS`) + ajuste em `precisaAtualizar`.

### Negativas / trade-offs

- **SPEC.md pode ficar visualmente desatualizado** — alguém lendo o markdown vê `status: todo` numa
  US que já está `done` no DB. Mitigação: o brief diário e as tools MCP (`tasks-list`, `my-work`)
  são a fonte humana primária de estado — SPEC.md é lido geralmente pra entender a US, não pra ver
  status.
- **PR review não pega mudança de status** — se Wagner muda status via MCP, não passa por code review.
  Mitigação: já era o caso antes (tool MCP sempre foi out-of-band); agora só fica honesto.
- **Drift entre dev e prod** — se alguém roda Pest local rodando `mcp:tasks:sync` sobre um DB com
  estado promovido, o sync respeita o DB local (correto, mas surpreendente em onboarding). Doc
  no `tasks-update` description cobre.

### Edge cases tratados

- Task `done` removida do SPEC → permanece `done` (não regride pra `cancelled`).
- Task `cancelled` no DB → continua `cancelled` mesmo se SPEC re-introduz com `status: todo` (precisa
  intervenção humana via `tasks-update` pra "reabrir").
- US nova com status inicial não-`todo` (ex: SPEC diz `status: doing` direto) → respeitado na criação.

## Implementação

Diff em `Modules/Jana/Services/TaskRegistry/TaskParserService.php`:
- Nova constante `LIVE_STATE_FIELDS = ['status', 'owner', 'sprint', 'priority']`
- `syncAll()` separa caminho create (SPEC integral) vs update (só campos descritivos)
- `precisaAtualizar()` remove status/owner/sprint/priority da lista de comparação
- Nova função `extrairCamposDescritivos()` filtra o payload
- Nova função `logarSkipsDeEstadoVivo()` audita divergências preservadas
- Lógica de cancelamento órfão exclui `status: done`

Diff em `Modules/Jana/Mcp/Tools/TasksUpdateTool.php`:
- Description e docblock atualizados
- Linha final do `handle()` deixa de pedir edição manual do SPEC

Testes: `Modules/Jana/Tests/Feature/Mcp/TaskParserPreservaEstadoVivoTest.php` (5 cenários).

## References

- [BUGS-MCP-SYNC-2026-05-13.md](../requisitos/Jana/BUGS-MCP-SYNC-2026-05-13.md) — Bug #2
- [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — Jira-style task management (parcialmente superseded)
- [ADR 0053](0053-mcp-server-governanca-como-produto.md) — MCP server governança
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (não afetado)
