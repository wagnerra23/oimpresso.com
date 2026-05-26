---
name: Memórias do oimpresso NÃO são privadas — léxico errado
description: No oimpresso todo conhecimento de projeto é compartilhado com o time via MCP. NUNCA usar a palavra "privada" pra descrever auto-mem ou qualquer memória do projeto. Categorias canônicas são CANON (git, visível ao time) vs LOCAL pessoal (~/.claude/oimpresso-local/, só Wagner) — ADR 0131
type: feedback
---

No léxico do oimpresso, **memória do projeto NÃO é "privada"**. Toda memória canon é compartilhada via MCP server com o time inteiro (Wagner/Felipe/Maiara/Eliana/Luiz).

**Why:** Wagner 2026-05-25 corrigiu explicitamente quando o agente descreveu auto-mem (`~/.claude/projects/D--oimpresso-com/memory/`) como "auto-mem privada está bloqueada". A palavra "privada" sugere que algum conhecimento do projeto fica restrito a uma pessoa — isso é falso e contradiz o pilar de transparência da Constituição v2 ([ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §7).

**Categorias canônicas corretas** ([ADR 0131](../decisions/0131-tiering-memoria-canonico-local-segredo.md) — tiering memória):

| Tier | Onde vive | Quem vê | Exemplo |
|---|---|---|---|
| **CANON** | `D:/oimpresso.com/memory/` (git) → MCP webhook → MCP server | Time inteiro via tools MCP | ADRs, SPECs, reference, session logs, feedback |
| **LOCAL pessoal** | `~/.claude/oimpresso-local/` | Só Wagner (filesystem dele) | Notas pessoais Wagner sobre estilo de trabalho |
| **SEGREDO** | Vaultwarden (`vault.oimpresso.com`) | Acesso por permission | Tokens, senhas, certificados |

**O que NÃO existe:** categoria "privada" de memória de projeto. Auto-mem do filesystem `~/.claude/projects/*/memory/` é só dispositivo técnico legado — todo conteúdo foi migrado pra git canon em 2026-05-13 ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) e a pasta hoje só guarda pointer + user_profile pessoal pendente de mover pro LOCAL.

**How to apply:**

1. Quando descrever onde vai salvar conhecimento, usar tier canônico: "salvo como **canon git** (visível ao time via MCP)" ou "salvo como **LOCAL pessoal** (só você)" ou "guardado no **Vaultwarden** (segredo)"
2. NUNCA escrever "auto-mem privada", "memória privada", "memória interna", "memória pessoal do agente"
3. Se hook bloquear escrita em `~/.claude/projects/*/memory/`, explicar: "hook bloqueia escrita nesse path porque migramos pra canon git ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) — vou salvar em `memory/reference/` que vai pro time via MCP"
4. Mesmo notas operacionais transitórias (handoffs, session logs) são CANON git, não "privadas"

**Refs:**
- [ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — conhecimento canônico vai pra git+MCP, zero auto-mem
- [ADR 0131](../decisions/0131-tiering-memoria-canonico-local-segredo.md) — tiering canônico/local/segredo
- [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §7 — transparência como princípio duro
- [MEMORY.md](../../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/MEMORY.md) — pointer auto-mem legada
