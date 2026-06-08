# Sessão 2026-05-10 tarde — D1-D4 pre-fix + helper sessões paralelas + audit warming

> **Sessão Claude paralela à da manhã.** 5 PRs entregues, validação prática do problema multi-sessão + solução, audit pre-Sprint 1 fechado em zero crítico pendente.

## Linha do tempo (compacto)

1. Sub-agent C completou em background: charter Modules/Autopecas + plano migração Vargas → **PR #400**
2. Diagnosticado problema: 3 sessões Claude no mesmo `D:\oimpresso.com` se sabotando. Branches trocando, `git add` capturando arquivos vizinhos, commits caindo em branch errada
3. Construído helper `tools/new-claude-session.ps1` + runbook → **PR #403**
4. Decision sheet pra Felipe segunda em formato Opção A/B + recomendação → **PR #405**
5. Wagner autorizou pre-aplicar D1-D4 nos drafts → **PR #408** (2 commits, primeira validação real do worktree isolado)
6. Audit batch warming + verificação Pest Modules/Jana (já fixado PR #393) — sem novas correções
7. Session log → **PR #416 (este)**

## Decisões aplicadas pré-Felipe

- **D1** schema benchmark: `period_start/end` (date range) → `period` (string YYYY-MM)
- **D2** schema benchmark: `value_p25/p50/p75` (quartiles) → `value_p50/p90` (mediana + cauda)
- **D3** BackfillCommand: `tax_number` → `tax_number_1` (confirmado SSH `SHOW COLUMNS`)
- **D4** BackfillCommand: `--force` flag adicionado (default safe = idempotente)

Tudo continua em `memory/decisions/proposals/drafts/`. Felipe segunda só valida que concorda + roda Pest local + abre PR US-INFRA-012 movendo drafts pra `database/migrations/` real. Esforço Felipe: ~25min (era ~2h).

## Lições processuais

### 1. 3 sessões paralelas no mesmo working tree = receita pra desastre
PRs #400 e #405 tiveram que virar `-v2` porque branches trocaram durante commit. Sintomas: branch errada no `git branch --show-current`, `git add` capturando arquivos de sessão vizinha, commits incluindo trabalho que não era meu. Solução: **`tools/new-claude-session.ps1`** (PR #403) cria worktree isolado em `.claude/worktrees/<nome>` (já gitignored).

### 2. Worktree isolado funciona em produção real
PR #408 teve 2 commits separados, ambos a partir de `.claude/worktrees/d1-d2-benchmark-prefix`. Durante esses commits, sessões paralelas:
- Mudaram branch ativa do main worktree pra `claude/all-frentes-pr13-officeimpresso-ui-arquivo`
- Abriram PRs #406, #407, #409+
- **Esta worktree ficou totalmente isolada — branch intacta, ZERO interferência.**

### 3. `git commit --only <files>` é mais seguro que `git add` + `git commit`
Quando outras sessões podem mexer no index entre o `add` e o `commit`, `--only` força o git a usar apenas os caminhos especificados. Padrão pra adotar quando não tem worktree isolado.

### 4. Sub-agents fora-de-banda continuam rodando após `/compact`
Background Agent C (Opus 4.7, agentId `afc463f76faf52403`) terminou após o compact e o resultado apareceu em notificação de tarefa quando voltei. Lesson: monitor `agentId` antes de marcar como abandonado.

### 5. GraphQL rate limit ≠ REST rate limit
`gh pr edit` usa GraphQL (5000/h por user). Quando esgota, **`gh api -X PATCH repos/.../pulls/N`** funciona (REST core, conta separada com 4993 livres). Workaround pra updates de PR title/body.

### 6. Duas sessões Claude quase simultaneamente em mesma janela
Sessão da manhã ainda criava work artifacts (5 PRs com.visual + cartas + ADR 0125 + canon MCP) quando esta sessão começou. Worktree isolado também resolve isso — cada uma vê só sua arvore.

## Achados MCP estado (read-only)

- **Cycle drift 100%** — 16/16 commits/PRs últimos 7d **não** tocaram CYCLE-03. Brief flagging. Pivot ou rollover.
- **Goal CYCLE-03 bloqueado:** smoke fiscal SEFAZ biz=1 — só falta Wagner criar 1 venda em `/sells/create` (5min). Pipeline já configurado completamente (flag ON, cert válido, regime+CFOP+CSOSN setados).
- **35 inbox notifications** — todas auto-assign 5d ago (US-COPI-* + US-PONT-*). Marca como lido.
- **30 tasks em triage** — 15 US-AP-* + 15 US-AUTO-*. TODAS feature-wish. Status correto = backlog sem owner até sinal qualificado (ADR 0105). Não tocar.

## PRs desta sessão

| PR | Escopo | Worktree | Status |
|----|--------|----------|--------|
| [#400](https://github.com/wagnerra23/oimpresso.com/pull/400) | Autopecas charter + plano Vargas | main (v1 corrompida → v2) | aguarda review |
| [#403](https://github.com/wagnerra23/oimpresso.com/pull/403) | Helper sessões paralelas + runbook | main | aguarda review |
| [#405](https://github.com/wagnerra23/oimpresso.com/pull/405) | Decision sheet Felipe + handoff tarde | main | aguarda review |
| [#408](https://github.com/wagnerra23/oimpresso.com/pull/408) | D1+D2+D3+D4 pre-aplicados | ⭐ `.claude/worktrees/d1-d2-benchmark-prefix` | aguarda review |
| #416 (este) | Session log | ⭐ `.claude/worktrees/session-log-2026-05-10` | criando |

## Próximos passos imediatos

**Wagner:**
1. Mergear 5 PRs (recomendo ordem #403 → #408 → #405 → #400 → #416)
2. Após #403 mergear: usar `.\tools\new-claude-session.ps1 -Name <escopo>` em vez de abrir Claude direto na raiz
3. Smoke SEFAZ biz=1: criar 1 venda `/sells/create` (5min) — desbloqueia goal CYCLE-03
4. Decidir se CYCLE-03 fecha+rollover (drift 100%) ou se reorienta

**Felipe segunda:**
1. Abre [`_FELIPE_DECISIONS_PRE_SPRINT1.md`](decisions/proposals/drafts/_FELIPE_DECISIONS_PRE_SPRINT1.md) — 4 decisões D1-D4 marcadas APLICADO
2. Valida que concorda (5min)
3. `vendor\bin\pest tests/Feature/Insights` local
4. PR US-INFRA-012 movendo drafts pra `database/migrations/` real

**Próxima Claude:**
1. Lê `memory/08-handoff.md` (atualizado em PR #405)
2. Confirma quais PRs mergearam: `gh pr list --state merged --limit 10`
3. Se Felipe atacou US-INFRA-012, pula pra próximo Sprint 1
4. Se Vargas voltou Q4/26, ativa Modules/Autopecas via ADR de promoção
5. **Se for trabalho paralelo:** usa `tools/new-claude-session.ps1` (após #403 mergeado) pra evitar replay do incidente desta sessão

## Auto-mem updates relevantes

- Confirmado novamente: ROTA LIVRE = vestuário Termas do Gravatal/SC, NÃO gráfica/SP. Audit warming letters validou que as 5 cartas com.visual NÃO citam ROTA LIVRE como prova social (escolha correta).
- Pacote outbound (5 cartas + 06-vargas) está consistente com ADR 0105, sem PII real, com placeholders pra Wagner preencher.
- Wagner regra "tenancy/scope/Controller/Model/migration multi-tenant exigem Pest local Felipe-only" respeitada — todos os PRs desta sessão são docs ou drafts ou tooling, zero código produtivo modificado.

---

**Tempo da sessão:** ~3h. **PRs gerados:** 5. **Linhas docs:** ~2200. **Bug Pest Modules/Jana:** 0 (já fixado PR #393).
