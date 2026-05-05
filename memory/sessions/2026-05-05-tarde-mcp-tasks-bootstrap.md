---
date: 2026-05-05
slot: tarde
title: "Bootstrap retroativo das tasks ADS Skills no MCP DB + ADR 0077 do resolver bug"
participants: [W, C]
duration_min: 45
tags: [mcp, governance, ads, bootstrap, retroactive]
---

# 2026-05-05 tarde — Bootstrap retroativo MCP

## Gatilho

Wagner pediu "teste as tarefas". Diagnóstico revelou 3 vazamentos de governança:

1. Sessão maratona ADS Skills de ontem (24 commits, 6 fases UI) ficou 100% off-the-record do MCP — handoff narrativo + git, zero entries em `mcp_tasks`. Violou ADR 0070.
2. CYCLE-02 mencionado em handoff como "proposto" mas nunca criado em `mcp_cycles`.
3. Tools `my-work` (sem owner) e `my-inbox` quebradas — hipótese inicial era `mcp_tokens.user_id=NULL` (errada), causa real é resolver de owner do MCP server usando `users.username='WR23'` (legacy UltimatePOS) em vez do `owner='wagner'` que tasks reais usam.

## Ações

**SQL transacional via SSH+MySQL no Hostinger** (CT 100 lê desse mesmo DB via autossh):

- ✅ `mcp_jira_projects` ADS criado (id=23, color=amber, icon=sparkles).
- ✅ `mcp_cycles` CYCLE-02 criado (id=332, project_id=23, status=planning, 2026-05-13 → 2026-05-26). Goal: time descobre skills via tool MCP, Wagner valida fluxo edit→approve→publish, drift filesystem auto-detectado, ≥16 skills+versions.
- ✅ 6 tasks ADS-1..ADS-6 status=done com `source_git_sha` apontando pros commits da sessão de ontem (fbe8d127 ADR-0076, c3a40651 UI MVP, 76e42e4d FASE 1, 87720631 FASE 2, bfadf43c FASE 3, 281b95b3 FASE 4).
- ✅ `mcp_jira_projects.next_task_number=7` (próxima task será ADS-7).

**ADR 0077 escrito** propondo coluna `users.mcp_handle` com UNIQUE INDEX e resolver MCP server fail-loud. Status=proposto, decided_by=[W], aguarda aprovação Wagner.

## Observações

- **Wagner tem 2 user rows** em `users` (id=1 username=WR23 + id=2 username=NULL). Token id=14 aponta pra id=2 — duplicação precisa ser resolvida antes da seed do mcp_handle (parte do plano de execução do ADR 0077).
- **5 perms ads.admin.skills.\*** no Hostinger são Spatie permissions (DB users), separadas do `mcp_handle` proposto. Não conflitam.
- **CYCLE-02 status=planning** → invisível em `cycles-active`. Vira active quando Wagner rodar `cycles-close CYCLE-01 --rollover-to=CYCLE-02` em 2026-05-12.

## Aprendizado / disciplina

A causa raiz dos 3 vazamentos é a mesma: **não tem hook bloqueando commit em `Modules/<X>/` sem task-id no body**. Sessão de ontem otimizou velocidade (24 commits em algumas horas) e governança ficou 100% no humano. Próxima vez: hook `pre-commit` que detecta `feat(<modulo>):` sem `task_id:` e avisa (não bloqueia, mas torna explícito).

## Próximas P0

1. Wagner aprovar ADR 0077 → executar plano (~30min).
2. Tool MCP `skills-search` (Goal 4 do CYCLE-02).
3. Wagner testar fluxo end-to-end (Goal 5 do CYCLE-02) — ~5min.
4. Hook `pre-commit` anti-drift commits-sem-task-id (~20min).
