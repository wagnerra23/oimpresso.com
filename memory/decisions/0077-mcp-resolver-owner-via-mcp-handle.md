---
slug: 0077-mcp-resolver-owner-via-mcp-handle
number: 77
title: "MCP resolver via users.mcp_handle (SUPERSEDED por ADR 0081 — Identity Mesh)"
type: adr
status: superseded
authority: canonical
lifecycle: substituido
decided_by: [W]
decided_at: "2026-05-05"
module: copiloto
quarter: 2026-Q2
tags: [mcp, governance, identity, p1, superseded]
supersedes: []
supersedes_partially: []
superseded_by:
  - 0081-identity-mesh-mcp-actors
related:
  - 0053-mcp-server-governanca-como-produto
  - 0070-jira-style-task-management-current-md-removed
pii: false
review_triggers:
  - "Time crescer pra 8+ pessoas e mcp_handle precisar virar entidade governada (com history) em vez de coluna"
  - "Anthropic publicar pattern oficial pra binding de identity em servers MCP"
---

# ADR 0077 — SUPERSEDED por ADR 0081 (Identity Mesh)

> ⚠️ **NOTA 2026-05-05 (mesma sessão):** Esta ADR foi superseded por [ADR 0081 — Identity Mesh](0081-identity-mesh-mcp-actors.md). A solução proposta aqui (`users.mcp_handle` como string) cobria apenas o slug. ADR 0081 implementa solução completa: tabela `mcp_actors` com manifest YAML (trust_level, modules_write, skills_required, etc.).
>
> **Diagnóstico desta ADR continua válido** (token user_id=1 vira username=WR23 que não bate com tasks.owner=wagner). A decisão arquitetural foi superada por uma melhor.
>
> **Não implementar esta ADR.** A solução está em ADR 0081.

---

# ADR 0077 — MCP server: resolver de owner via `users.mcp_handle`, não `users.username` (texto original abaixo)

## Contexto

Tools MCP `my-work`, `my-inbox` e `triage` (sem argumento explícito) retornam erro:

```
Owner não pôde ser resolvido. Passe explicitamente: my-work owner:wagner.
```

Diagnóstico em 2026-05-05 mostrou:

- `mcp_tokens.user_id` está corretamente populado (token id=10 do Wagner aponta pra `users.id=1`).
- `users.id=1` tem `username='WR23'` (legado UltimatePOS, batch da migração 6.7).
- `mcp_tasks.owner` usa `'wagner'` (lowercase, alinhado ao `first_name` mas como string canonical).
- O resolver do MCP server provavelmente faz: `token → user_id → users.username` → busca `mcp_tasks WHERE owner=username` → 0 resultados → erro.

**Resultado prático:** cada chamada precisa `owner:wagner` explícito. Tools que dependem de "quem sou eu?" ficam inúteis pra fluxo natural. Mesmo problema vale pra Eliana (`username='Eliana-01'` ≠ `owner='eliana'`), Maíra (`username='maiara-01'` ≠ `owner='maira'`), etc.

**Alternativas avaliadas:**

- **A) Renomear `users.username` pra lowercase canonical.** ❌ Quebra auth UltimatePOS — `username` é usado em login, RBAC, sessions. Risco de regressão massivo.
- **B) Resolver via `strtolower(first_name)`.** ⚠️ Frágil: colide quando dois usuários compartilham primeiro nome (já temos 2 Elianas: esposa + cliente WR2; provável outros futuros). Sem unicidade no schema.
- **C) Coluna explícita `users.mcp_handle`** (escolhida) — slug canonical único, decoplado de `username` e `first_name`.

## Decisão

Adicionar coluna `users.mcp_handle VARCHAR(50) NULL UNIQUE` e fazer o resolver do MCP server priorizá-la sobre `username`/`first_name`.

**Schema:**
```sql
ALTER TABLE users ADD COLUMN mcp_handle VARCHAR(50) NULL AFTER username;
ALTER TABLE users ADD UNIQUE INDEX users_mcp_handle_unique (mcp_handle);
```

**Seed inicial (5 pessoas do time):**
```sql
UPDATE users SET mcp_handle='wagner' WHERE id=1;  -- W
UPDATE users SET mcp_handle='wagner' WHERE id=2;  -- W (segundo registro do Wagner; mesmo handle ❌ → unique falha. Resolver primeiro: deletar o duplicado ou marcar revoked)
UPDATE users SET mcp_handle='eliana' WHERE id=3;  -- E (esposa)
UPDATE users SET mcp_handle='maira'  WHERE id=74; -- M
-- felipe + luiz: descobrir IDs
```

> ⚠️ Wagner tem 2 rows em `users` (id=1 username=WR23 + id=2 username=NULL). Antes da seed, decidir qual mantém — provavelmente revogar id=2 e migrar token id=14 (que aponta pra id=2) pra id=1.

**MCP server resolver (pseudo-código):**
```php
// antes
$handle = $token->user->username;

// depois
$handle = $token->user->mcp_handle
    ?? throw new ResolverException("user_id={$token->user_id} sem mcp_handle. Seed obrigatório.");
```

Sem fallback silencioso pra `username`/`first_name` — fail loud força seed correto.

## Justificativa

- **Decoplagem:** identity pra MCP é semântica diferente de login UltimatePOS. Mistura gera bug exatamente como o atual.
- **Alinhamento com dados existentes:** `mcp_tasks.owner` JÁ usa o formato canonical lowercase. Criar `mcp_handle` formaliza a convenção.
- **Unicidade real:** UNIQUE INDEX previne 2 pessoas com mesmo handle (não cobre `first_name`).
- **Fail loud > fail silencioso:** resolver sem fallback obriga onboarding correto. Pessoa nova sem `mcp_handle` quebra rápido em vez de virar tickets fantasma.

**Reabrir esta decisão se:** time virar 8+ pessoas e governança de handles precisar history/audit (aí vira tabela própria `mcp_handles` com `valid_from`/`valid_to` per ADR 0074 bi-temporal pattern).

## Consequências

**Positivas:**
- `my-work`, `my-inbox`, `triage` (sem owner) voltam a funcionar — produtividade desbloqueada.
- Pattern claro pra onboarding (ADR 0066/team-onboarding skill atualiza pra setar `mcp_handle` no setup).
- Sem risco de regressão em UltimatePOS auth.

**Negativas / Trade-offs:**
- Migration nova obrigatória (~5 linhas).
- Seed manual 5 rows (uma vez). Documentar em `memory/requisitos/Infra/RUNBOOK-criar-modulo.md` ou em skill `oimpresso-team-onboarding`.
- 1 fix de código no MCP server CT 100 + redeploy FrankenPHP (~10min).

**Riscos mitigados:**
- Drift identity Wagner-com-2-user-rows: força resolver da ambiguidade antes de tudo (decisão sobre id=1 vs id=2).
- Sequer detectar gente nova sem handle (fail loud).

## Plano de execução (~30min, P1)

1. **(5min)** Migration L13.6: `database/migrations/2026_05_06_xxxxxx_add_mcp_handle_to_users.php`.
2. **(10min)** Resolver Wagner duplicado: identificar qual user row é o "vivo" (id=1), revogar id=2 + migrar `mcp_tokens.user_id=2 → 1` (token id=14).
3. **(5min)** Seed 5 handles via `mysql -e 'UPDATE users SET mcp_handle=...'` ou seeder.
4. **(5min)** MCP server CT 100: editar resolver, deploy via push, restart Octane/FrankenPHP.
5. **(5min)** Validar via `my-work` (sem owner) — deve retornar 30 tasks do Wagner direto.

Bloqueia: dashboard `/copiloto/admin/board` (US-PROJECT-1) — depende de `my-work` per-user funcionando pra Kanban personalizado.

## Referências

- [ADR 0053 — MCP server governança como produto](0053-mcp-server-governanca-como-produto.md)
- [ADR 0070 — Jira-style task management](0070-jira-style-task-management-current-md-removed.md)
- Diagnóstico session log: `memory/sessions/2026-05-05-tarde-mcp-tasks-bootstrap.md` (a criar)
