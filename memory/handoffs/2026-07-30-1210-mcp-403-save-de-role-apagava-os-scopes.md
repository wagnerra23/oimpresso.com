---
date: "2026-07-30"
time: "12:10 UTC"
slug: "mcp-403-save-de-role-apagava-os-scopes"
tldr: "MCP fora do ar para os 4 users do time: um save em /roles/695/edit apagou os 17 jana.mcp.* porque syncPermissions é destrutivo e nenhum módulo os expunha ao form — o conserto pela UI era impossível pelo próprio bug. Prod restaurado (11 na role + 2 diretos em [W], isolando valores de F/M/L) e a armadilha desarmada em 2 PRs."
prs: [5060, 5063]
decided_by: [W]
related_adrs:
  - 0053-mcp-server-governanca-como-produto
  - 0093-multi-tenant-isolation-tier-0
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
next_steps:
  - "Sessão NOVA pra ter as tools mcp__oimpresso__* de volta (o cliente fez handshake no 401)"
  - "Decidir o que fazer com o main LOCAL divergente deste worktree (7 commits nunca pushados)"
  - "Reconciliar Modules/Jana/Resources/permissions.php — declara 12 dos 17 scopes"
---

# Handoff 2026-07-30 12:10 — o 403 do MCP era a tela de papéis comendo as permissões

> 2 PRs **MERGED**: [#5060](https://github.com/wagnerra23/oimpresso.com/pull/5060) (fix estrutural, merge [W] 11:34) · [#5063](https://github.com/wagnerra23/oimpresso.com/pull/5063) (registra o guard no lane do CI, merge 11:56).

## Estado MCP no momento do fechamento

Consultado **por HTTP** (as tools `mcp__oimpresso__*` não existem nesta sessão — o cliente
fez o handshake no SessionStart, quando o servidor ainda devolvia 401; o endpoint está 200):

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **30 tasks** ativas @wagner (13 em REVIEW; topo `US-COPI-123` p0)
- `sessions-recent` → 3 últimas, todas indexadas 2026-07-30 (parser de UC · SDD Sells · trio Sells/Show)

## O que aconteceu

`brief-fetch` devolvia `401 · User não tem permission jana.mcp.use`. Token **válido** — passou
de `invalid_token` e `user_not_found`; o que barrava era o gate RBAC do `McpAuthMiddleware`.

**Alcance:** 4 users — `WR23`(1), `felipe-01`(12), `maiara-01`(74), `luizaugusto-01`(569).
Todos na role **695 `Operacional#1`** (biz=1), com **0 das 17** `jana.mcp.*`. Janela medida no
log de prod: verde `2026-07-29 15:07:09`, vermelho `15:32:13`. O check `mcp_token_sem_permission`
([#4969](https://github.com/wagnerra23/oimpresso.com/pull/4969)) **alertou às 06:00 de 30/07 e
ninguém viu** — o detector funcionou; faltou ouvinte.

**A causa é aritmética, não hipótese.** `RoleController@update` faz
`syncPermissions($request->input('permissions'))` — destrutivo, remove tudo que não veio no POST.
O form de `/roles/{id}/edit` só renderiza o que os módulos declaram em `user_permissions()`, e a
Jana declarava **5** enquanto `TeamMcp` devolvia `[]`. A prova: depois do save, o conjunto `jana.*`
da role era **exatamente** esses 5. Corolário que fechava o circuito — **a UI não conseguia
consertar**, porque as permissions não existiam nela. Por isso reincidiu 4×
([#4928](https://github.com/wagnerra23/oimpresso.com/pull/4928) registra as 3 anteriores).

## Artefatos gerados

| Arquivo | O quê |
|---|---|
| [`McpScopesSeeder.php`](../../Modules/Jana/Database/Seeders/McpScopesSeeder.php) | catálogo vira fonte única legível (`catalogo()`) |
| [`DataController.php`](../../Modules/Jana/Http/Controllers/DataController.php) | `user_permissions()` **deriva** os 22 do catálogo (17 `jana.mcp.*` + 5 `jana.cc.*`, também invisíveis e igualmente apagáveis) |
| [`McpScopesVisiveisNoRoleEditTest.php`](../../Modules/Jana/Tests/Feature/McpScopesVisiveisNoRoleEditTest.php) | trava a paridade catálogo ⇄ tela; controle anti-verde-tautológico + piso 17 |
| [`.github/ci-sqlite-pest.list`](../../.github/ci-sqlite-pest.list) | registra o guard no lane que o executa |

## Restauração de produção (aditiva, `givePermissionTo` — nunca `sync`)

- **role 695 ← 11 scopes** (`use`, `tasks.read/write/advance/close`, `cycles.manage`,
  `projects.manage`, `decisions.read`, `sessions.read`, `usage.self`, `governanca.tecnico`)
- **user 1 (WR23) ← 2 diretos**: `governanca.financeiro`, `usage.all`

**Por que os 2 saíram da role:** o catálogo diz *"Wagner + Eliana[E]"* e *"Apenas Wagner/admin"*
(spend cross-team em R$), e a 695 é **compartilhada** com F/M/L — que por regra Tier 0 não veem
valores. O conjunto restaurado é o que o `mcp_audit_log` **prova** que a role usava
(`tasks-create` ×574, `tasks-update` ×520, `cycles-create/close`; `maiara-01` `tasks-create` ×11).
`memory.manage` e os 3 `handoff.*` ficaram fora — sem evidência de uso.

## Persistência

- **git:** 2 PRs merged; `main` em `eca4388ec0`.
- **prod:** deploy `success`; `user_permissions()` devolve **27** (5 base + 22 do catálogo).
- **smoke real:** `POST /api/mcp` → **HTTP 200** (2×, incl. pós-deploy) · bloqueados **4 → 0** ·
  `/roles/695/edit` renderiza os checkboxes MCP **pré-marcados**, com `usage.all` e
  `governanca.financeiro` **desmarcados** (isolamento visível na tela).
- **execução do guard provada por log:** `PASS Modules\Jana\Tests\Feature\McpScopesVisiveisNoRoleEditTest`
  no CI do `main` — de **0** para **3** ocorrências.

## Próximos passos pra retomar

```bash
gh pr view 5063 && node .claude/hooks/brief-fetch-curl.mjs
```

## Lições catalogadas

- **LC-13 (ocorrência 4, cometida e consertada aqui):** escrevi o guard e ele **não rodava em lane
  nenhuma** — `ci.yml` roda lista explícita, `jana-pest.yml` roda allowlist. Passou só porque eu o
  rodei à mão no CT 100, e eu estava prestes a reportar "CI 100 verde" como se cobrisse. `grep -c`
  no log deu **0**. Quem disparou a checagem foi a pergunta *"testou?"* do [W], não iniciativa minha.
- **Não virou máquina nova:** a defesa correta já existia (o `mcp_token_sem_permission` do #4969
  detectou certo). O buraco era **ouvinte do alarme**, não detector — e ninguém deve armar um 2º.

## Pointers detalhados

- Session log: [`2026-07-30-mcp-scopes-apagados-por-save-de-role.md`](../sessions/2026-07-30-mcp-scopes-apagados-por-save-de-role.md)
- Achados colaterais **não corrigidos**: `Modules/Jana/Resources/permissions.php` declara 12/17;
  o checkout do CT 100 tem `ForjaRoutesSmokeTmpTest.php` órfão que **quebra o bootstrap da suíte
  inteira lá**; o `main` **local** deste worktree diverge com 7 commits nunca pushados.
