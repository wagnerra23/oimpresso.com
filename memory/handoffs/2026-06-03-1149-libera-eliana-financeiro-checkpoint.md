---
date: 2026-06-03
hour: "11:49 BRT"
topic: Checkpoint WIP staging-ct100 + liberação Eliana no Financeiro + revisão permissões MCP
duration: "~1h"
authors: [wagner, claude]
---

# Libera Eliana no Financeiro — checkpoint + revisão de permissões MCP

> Sessão curta operacional. Sem PR pra `main`, só push no branch `feat/staging-ct100`.

## Estado MCP no momento

- **Cycle ativo:** CYCLE-08 "Receita — Onda A" (2026-05-31 → 2026-06-28, 11% decorrido). Métrica-mãe = RECEITA.
- **Branch:** `feat/staging-ct100` · `local == origin` (0/0) · histórico linear.
- **HEAD:** `404ae36a3 fix(financeiro): realinha chips de filtro + primary header DS v4` (08:47 BRT) — **já em cima do meu checkpoint** → Eliana/trabalho de Financeiro **não travado**.

## O que aconteceu

Wagner: *"a Eliana tá fazendo o financeiro, tô liberando ela pra fazer o que for necessário... comite e merge nessa instrução e libere ela."* Depois: *"feche tudo e não trava ela."*

1. **Checkpoint commit** `59662515b` (32 arquivos) — working tree estava com mistura grande (Financeiro + NFSe + Sells múltiplos-endereços + BusinessScope concerns + stylelint gate + KB). Wagner escolheu *"tudo menos gerados"* + *"só push no branch"* (não merge na main).
   - **Excluídos 1007 arquivos gerados** (`bootstrap/cache/*.php` + `resources/js/{actions,routes,wayfinder}/`) e restaurado `bootstrap/cache/.gitignore` (tinha sido deletado).
   - Mensagem corrigida via `--force-with-lease` (1ª tentativa entrou `@` por erro de here-string PowerShell no tool Bash).
2. **Liberação MCP:** atribuí cluster P0 Onda 22 à `eliana` (US-FIN-026/027/028) + comentário de autorização do Wagner na US-FIN-026.
3. **Revisão de permissões MCP** (a pedido do Wagner): identidade `eliana` em `mcp_actors`.

## Revisão de permissões — `eliana` (L3 vertical specialist)

- **modules_write:** Financeiro, FinanceiroAvancado, NfeBrasil, NFSe, Accounting, RecurringBilling. **modules_read:** `*`.
- **modules_blocked:** Connector, Superadmin, Governance, ADS, TeamMcp, Mobile, Copiloto/Jana.
- **actions_blocked:** drop_table, schema_destructive, push_main_no_pr, deploy_prod_solo, edit_non_financial_code.
- 🔑 **Sem enforcement:** NÃO existe classe `ActionGate` no código — `actions_blocked` é declarativo ("gate enforce em ActionGate Fase 5", não construída). Hoje nada trava ela mecanicamente.
- ⚠️ Não dá pra confirmar daqui o **estado vivo** do servidor (`mcp.oimpresso.com`) nem se o **token dela está ativo** — fica no admin `oimpresso.com/copiloto/admin/team` (comando `RotateTokenCommand` se precisar rotacionar).
- **Veredito:** Financeiro coberto, nada mecânico trava; "não trava ela" satisfeito.

## Artefatos gerados

- Commit `59662515b` (checkpoint, 32 arquivos +3128/-18) em `feat/staging-ct100`.
- MCP: US-FIN-026/027/028 owner=eliana + comentário autorização.

## Persistência

- **Git:** push em `origin/feat/staging-ct100` (checkpoint + handoff).
- **MCP:** tasks atualizadas (durável, ADR 0144).

## Próximos passos pra retomar

- Eliana: `git pull` em `feat/staging-ct100` e seguir o Financeiro (já está fazendo — HEAD 404ae36a3).
- Se login MCP dela falhar → rotacionar token no admin.

## Lições catalogadas

- **Here-string PowerShell (`@'...'@`) NÃO funciona no tool Bash** — vira `@` literal na mensagem. Usar `-F arquivo` ou aspas simples no Bash.
- "Liberar pessoa" tem 2 eixos: git (branch destravado) + MCP (manifesto/token). Manifesto sem `ActionGate` = intenção, não trava.
- Working tree com 1007 gerados não-ignorados (`resources/js/{actions,routes,wayfinder}/` do Wayfinder) — candidato a `.gitignore` em PR separado.

## Pointers detalhados

- Manifesto: [`McpActorsSeeder.php:240`](../../Modules/TeamMcp/Database/Seeders/McpActorsSeeder.php)
- Config MCP: [`.mcp.json`](../../.mcp.json) · admin `oimpresso.com/copiloto/admin/team`
