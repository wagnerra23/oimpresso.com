---
date: "2026-07-20"
time: "11:38 BRT"
slug: kb-test-helper-fk-cycle-fix
tldr: "Bug pré-existente do helper de teste KB: drops kb_* falhavam no MySQL (CT 100) por ciclo/self-FK. Fix com Schema::withoutForeignKeyConstraints() levou KbAutoClassifierTest de 0/7 → 6/7. PR #4569. Segundo bug independente (kbCreateBusinessRow no-op na tabela business real) virou follow-up task."
prs: [4569]
decided_by: [W]
---

# Handoff — Fix FK-cycle no helper de teste do módulo KB

## Estado MCP no momento do fechamento

Snapshot leve (sessão curta <2h, 1 PR — heurística da skill `encerrar-sessao`):
- **Brief SessionStart:** `Cycle: —` (sem cycle ativo). HITL pending Wagner: 2 (FIN-004, runbook on-prem).
- Não re-consultei `cycles-active`/`my-work` full (economia de crédito; trabalho não estava atrelado a task de cycle — bug pré-existente reportado direto no chat).
- Follow-up criado como task de sessão local (`task_a8619f33`), rodando em sessão paralela iniciada por [W].

## O que aconteceu

Bug pré-existente (não regressão de PR recente): `Modules/KB/Tests/Feature/KbAutoClassifierTest.php` falhava **7/7** no CT 100 (`oimpresso-staging`, MySQL real biz=1) no `beforeEach` → `kbBootstrapSchema()` (`Helpers.php:66`).

Causa-raiz **provada nas migrations** (não inferida): `kb_decision_tree_steps` participa de dois laços que nenhuma ordem de drop resolve — (1) FK **auto-referencial** (`yes_next_step_id`/`no_next_step_id` → self, migration `100008`) e (2) **ciclo** com `kb_decision_trees` (`steps.tree_id → trees.id` × `trees.root_step_id → steps.id` via `fk_kb_dt_root_step`, `ALTER` delayed). No MySQL real o `DROP` do "pai" estoura 1451/3730; sqlite tem FK enforcement frouxo, e a suíte pula sqlite via `markTestSkipped` → CI (`:memory:`) nunca pegou.

**Fix:** envolver só os drops `kb_*` em `Schema::withoutForeignKeyConstraints()` (try/finally restaura o check) em `kbBootstrapSchema()` **e** `kbTeardownSchema()`. CORE compartilhada intocada. Estritamente mais seguro — não pode regredir teste que passava.

**Evidência CT 100 (MySQL, biz=1):** antes 7/7 FAIL no `beforeEach`; depois **6 passed, 1 failed** (0/7 → 6/7). Blast radius: **22 arquivos** de teste KB usam o helper → todos corrigidos de uma vez.

**Segundo bug (independente, fora de escopo por decisão [W] "1 PR = 1 intent"):** o 7º teste (cross-tenant biz=99) falha porque `kbCreateBusinessRow()` usa `insertOrIgnore` com 4 colunas, mas a tabela `business` **real** do staging tem NOT NULL → insert silenciosamente ignorado → biz=99 nunca criado → `fk_kb_nodes_business` estoura. Provado via tinker (biz=99 exists=no após kbCreateBusinessRow(99)). Virou follow-up.

## Artefatos gerados

- `Modules/KB/Tests/Helpers.php` — fix nos 2 blocos de drop (+~24 linhas de wrap+comentário). PR [#4569](https://github.com/wagnerra23/oimpresso.com/pull/4569).
- Follow-up task `task_a8619f33` (kbCreateBusinessRow MySQL-safe) — sessão paralela local.

## Persistência

- **git:** branch `claude/kb-test-helper-fk-cycle-fix` pushed → PR #4569 (CI queued no fechamento).
- **git (este handoff):** branch `claude/handoff-kb-fk-fix`.
- **MCP:** follow-up via chip de sessão (não `tasks-create` MCP — bug pré-existente, não item de cycle).

## Próximos passos pra retomar

1. `gh pr checks 4569` — confirmar CI verde antes de [W] mergear.
2. Follow-up `task_a8619f33` fecha o 7º teste (KbAutoClassifierTest 7/7).
3. Restaurar container: `Helpers.php` foi sobreposto no `oimpresso-staging` (branch `claude/kb-controller-indexv2`) pra validar — restaurar via `git checkout -- Modules/KB/Tests/Helpers.php` quando a run background terminar. **PENDENTE.**

## Lições catalogadas

- **Ordem de drop não resolve ciclo/self-FK** — só `disableForeignKeyConstraints`. Confirmar o grafo de FK nas migrations antes de propor "reordenar" (o próprio task sugeria as duas opções; as migrations decidiram por eliminação).
- **`insertOrIgnore` em tabela real com NOT NULL falha SILENCIOSO** — helper de teste que só "funcionava" pra biz que já existia no clone. Divergência sqlite-mínimo × MySQL-real é o vetor recorrente deste helper.
- **Precisão:** cada causa-raiz foi provada (migrations grep + tinker), não afirmada por leitura — conforme §5 proibições 2026-07-15/17.

## Pointers detalhados

- PR #4569 body — root-cause completa + evidência CT 100.
- `Modules/KB/Tests/Helpers.php` — `kbBootstrapSchema()` / `kbTeardownSchema()`.
- `Modules/KB/Database/Migrations/2026_05_15_100008_*` — a migration com o ciclo/self-FK.
