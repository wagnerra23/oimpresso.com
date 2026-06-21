---
date: 2026-06-20
time: "2130 BRT"
slug: "backlog-34-perdidos-sync-auditoria"
tldr: "22 US 'plano-perdido' (ADR-0105) criadas via tasks-create + sincronizadas ao DB via PR #3090 (8 SPEC.md, merged ad95f06a16); dedup vivo pulou 4 que já eram US. Auditoria adversarial YELLOW: sync funciona; 1 P1 isolado — US-RB-052 colidiu com row órfã do wagner (2026-05-16) e o sync sobrescreveu (21 limpas). Aberto: SELECT mcp_task_events US-RB-052 + fix ID-collision (chip task_be16e770)."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3090]
---

# Handoff — 22 US "plano-perdido" criadas, sincronizadas e auditadas

## Estado MCP no momento
- Cycle **CYCLE-08** "Receita — Onda A" · 7d restantes · 75% decorrido.
- my-work @wagner: 30 ativas. As 22 desta sessão são `unowned/todo` (não entram em my-work até atribuídas).
- **PR #3090 MERGED** (`ad95f06a16`); 22 US vivas no DB (verificado via tasks-detail).

## O que aconteceu
Objetivo: criar no backlog MCP as ~27 iniciativas "perdidas" com sinal ADR-0105, deduplicadas. Fonte: `BATCH-BACKLOG-34-2026-06-20.md` (triagem run wf_1bfbefba).

1. **Dedup vivo** (`tasks-list` por módulo) mostrou a premissa "perdida" parcialmente stale: **4 dos 26 net-new JÁ eram US** → pulados: nfe-foundation=US-NFE-040, vestuario G3-G5=US-VEST-029/023/024, sells-nfce-inline=US-SELL-041, gates-cor=US-INFRA-035.
2. **22 net-new criadas** via `tasks-create`. O tool nativo NÃO tem `parent_plan`/`labels` → codificados na descrição (greppável por `parent_plan:` + `plano-perdido`).
3. **Descoberta:** `tasks-create` escreve no SPEC.md do checkout do MCP server (CT 100, remoto), uncommitted, NÃO no DB nem no checkout local. Sync = push em `main` → webhook → Job `IndexarMemoryGitParaDb` → DB (ADR 0053 item 8).
4. **Resolução (Wagner: "faça"):** worktree limpo off `origin/main` → apliquei os 22 blocos verbatim em 8 SPEC.md (+380/-0) → commit → **PR #3090** → auto-merge squash → mergeou (`ad95f06a16`). Module Grades Gate: 8 módulos subiram, 0 regrediram.
5. **Auditoria adversarial** (1 skeptic, verdict **YELLOW**): refutou o maior risco (a sync funciona; parser `TaskParserService` section-agnostic, neste repo). Achou 1 P1.

## P1 — colisão de ID `US-RB-052` (data integrity)
US-RB-052 tinha row órfã do **wagner (2026-05-16, nunca no SPEC)**. `tasks-create` assina ID por **max-do-SPEC** e ignorou a órfã → sync UPDATE sobrescreveu título/desc original. **Verifiquei as 22 timelines: isolado a RB-052**; as outras 21 limpas (1 só evento `claude`).
- Conteúdo original NÃO recuperável de git (só em `mcp_task_events`).
- Documentei via `tasks-comment` em US-RB-052 + `spawn_task` (`task_be16e770`) pro fix de causa-raiz (`max(SPEC,DB)+1`).
- **Pendente Wagner:** `SELECT * FROM mcp_task_events WHERE task_id='US-RB-052'` → se título antigo importa, restaurar como US-RB-056.

## Artefatos gerados
- PR #3090 (8 SPEC.md, +380, MERGED `ad95f06a16`) — 22 US.
- `memory/sessions/2026-06-20-adversario-backlog-34-sync.md` (adversary findings).
- `tasks-comment` em US-RB-052 + chip `task_be16e770`.
- Reconciliação slug→ID na descrição do PR #3090.

## Persistência
- **git:** PR #3090 em `main` (SPEC.md + DB sync). Handoff + adversary-log nesta branch.
- **MCP:** 22 US queryáveis (`tasks-detail`). DB `sha=ad95f06a16`.

## Próximos passos pra retomar
- `SELECT * FROM mcp_task_events WHERE task_id='US-RB-052'` (decidir restauração → US-RB-056).
- Chip `task_be16e770` (fix ID-collision no tasks-create).
- Chip adversário: fix frontmatter `Sells/SPEC.md` (`last_updated`/`version` sem aspas — gate SPEC advisory vermelho, **pré-existente**, não-required).
- Dedup IDs concorrentes (sessões paralelas: Jana 120-122, GOV 021-027, RB 053/054) — cruzar `parent_plan` pós-sync.

## Lições catalogadas
- `tasks-create` é **stateful/compartilhado** entre sessões paralelas → gaps de ID = criação concorrente (3 sessões live no mesmo cwd nesta sessão).
- Premissa "plano perdido/não-rastreado" exige **dedup VIVO** sempre — estava ~15% stale.
- ID assignment por **SPEC-max colide com rows DB órfãs** (bug real, fix encaminhado).
- Worktree `frosty-greider-83ab2f` NÃO é worktree git registrado (dir órfã dentro do repo) → git resolve pro D:/oimpresso.com na branch `feat/governance-ds-rollout-ledger`.

## Pointers detalhados
- Adversary: `memory/sessions/2026-06-20-adversario-backlog-34-sync.md`
- Batch fonte: `memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md`
- PR: https://github.com/wagnerra23/oimpresso.com/pull/3090
