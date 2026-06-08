---
name: Tools MCP do oimpresso não carregam automaticamente em worktree filho
description: Worktrees Claude criadas pelo harness não recebem tools mcp__Oimpresso_MCP__* — só o brief-fetch vem via hook SessionStart. Pra usar cycles-close, my-inbox, tasks-update etc, ToolSearch pra carregar schemas.
type: feedback
---
Quando Claude Code cria worktree filho via Agent tool (ex: agent paralelo, worktree isolada via `isolation: worktree`), as tools MCP do servidor oimpresso (`mcp__Oimpresso_MCP___Wagner__*`) **NÃO ficam disponíveis automaticamente** no harness do filho. Só o que vem do hook SessionStart (atualmente: `brief-fetch` cacheado via curl).

Sintoma: tentativa de chamar `cycles-active` ou `tasks-update` direto retorna `Function not found`. Pra usar, precisa primeiro carregar schemas:
```
ToolSearch query="select:mcp__Oimpresso_MCP___Wagner__cycles-active,..."
```

Depois disso o tool aparece como function callable na próxima resposta.

**Why:** 2026-05-14 22h → 2026-05-15 00h. Sessão pivot CYCLE-05→06 começou no worktree filho `musing-wilbur-3da897`. Sem tools MCP, eu apenas preparei papelada (5 blocos copy-paste pro Wagner colar no harness principal). Quando worktree filho foi deletado pelo sistema e voltei pro principal (`wizardly-kalam-438c28`), as tools `cycles-close`, `cycles-create`, `my-inbox` apareceram no deferred list e pude carregar via ToolSearch, então executei o pivot de verdade.

**How to apply:**
- Em worktree filho, **avisar Wagner desde o início**: "tools MCP `cycles-*`, `tasks-*`, `my-inbox` não estão carregadas aqui — vou preparar comandos copy-paste pra você executar no harness principal"
- OU **carregar via ToolSearch logo na 1ª resposta** se sessão precisar de tools MCP — economiza viagem
- Pra ações que MEXEM em estado canon (cycles-close, tasks-update, ADR vote), preferir worktree principal sempre — worktree filho serve melhor pra preparação + papelada
- Verificar com `ToolSearch query="oimpresso"` antes de prometer execução

**Não confundir com:**
- Worktree principal sem tools MCP — ai sim é problema de configuração `.claude/settings.local.json` ou tokens expirados
- Hook SessionStart desabilitado — verificar `~/.claude/projects/D--oimpresso-com/.claude/hooks/`

**Refs:** ADR 0119 (paralelismo sessões whats-active) · skill `brief-first` · brief-fetch-curl.ps1 do SessionStart · sessão 2026-05-14 worktree filho `musing-wilbur-3da897` pivot CYCLE-06
