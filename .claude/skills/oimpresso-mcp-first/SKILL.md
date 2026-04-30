---
name: oimpresso-mcp-first
description: ATIVAR antes de Read/Glob/Grep em memory/, ler ADR/session/spec do projeto, buscar conhecimento canônico do oimpresso, criar arquivo em ~/.claude/projects/*/memory/, OU pensar em "vou guardar isso na memória pra lembrar". Lembra: tool MCP primeiro (não filesystem); zero auto-mem privada (ADR 0061 — todo conhecimento vai pra git/MCP).
---

# Skill: oimpresso-mcp-first

🔴 **REGRA FUNDAMENTAL** — Wagner formalizou em 2026-04-30:

> *"seu conhecimento é MCP"*

Antes de qualquer ação de leitura/busca em conhecimento canônico do projeto, **tentar tool MCP primeiro**. Filesystem só como fallback documentado.

## Mapeamento pergunta → tool

| Quero saber | NÃO faça | Faça |
|---|---|---|
| Estado do cycle | `Read CURRENT.md` | `tasks-current` |
| ADR sobre X | `Glob memory/decisions/*X*` + Read | `decisions-search query:"X"` |
| ADR completa | `Read memory/decisions/0053-...md` | `decisions-fetch slug:"0053-..."` |
| Última sessão | `ls memory/sessions/ -t \| head -1` | `sessions-recent limit:1` |
| Fato persistente do business | (sem fonte) | `memoria-search query:"..."` |
| Sessão Claude Code do time | (sem fonte) | `cc-search query:"..."` |
| Quanto Wagner consumiu | (estimar) | `claude-code-usage-self` |

## Por que importa

- **73% menos tokens** medido empiricamente (CURRENT+handoff+1 ADR via Read = ~14.888 tokens; mesmo via tool MCP = ~3.928)
- **Auditado** em `mcp_audit_log` (LGPD Art. 18)
- **RBAC fino** — token revogável <30s
- **Permission per-doc** (`scope_required`) — Read filesystem ignora isso
- **Cross-tenant safe** — token vê só dados do business_id

## Quando filesystem É aceitável

- MCP server caiu (`/api/mcp/health` 5xx)
- Edição de arquivo (`memory/decisions/NNNN-novo.md` novo) — ok ler `_TEMPLATE_`
- Debug local antes de commit
- Wagner explicit pediu Read

Em todos os outros casos: **tool MCP primeiro**, mesmo que pareça mais lento. Não é. É auditado.

## ⛔ ZERO auto-mem privada (ADR 0061)

**NUNCA criar arquivo em `~/.claude/projects/*/memory/*.md`** — hook `block-automem.ps1` BLOQUEIA com `decision: deny`.

Quando pensar "vou guardar pra próxima sessão lembrar", **PARA** e:

| Tipo de conhecimento | Caminho git correto |
|---|---|
| Decisão arquitetural | `memory/decisions/NNNN-slug.md` (Nygard) → commit + push |
| Receita/runbook reproduzível | `memory/requisitos/{Mod}/RUNBOOK-tema.md` |
| Quirk de cliente / preferência coletiva | `memory/05-preferences.md` (apenda) |
| Estado/incidente histórico | `memory/sessions/YYYY-MM-DD-slug.md` |
| Comparativo competitivo | `memory/comparativos/slug_capterra.md` |
| Endpoint / SSH / credencial | `INFRA.md` (apenda) + Vaultwarden secrets |
| Convenção projeto | `memory/04-conventions.md` (apenda) |

Webhook GitHub sincroniza pro MCP em <60s — todo time enxerga via `decisions-search`.

**4 exceções permitidas pra working memory ad-hoc** (ADR 0061):
1. Credencial temporária dev (descartável <24h)
2. Working memory dentro da sessão atual (não persiste)
3. Cache local de tools/skills (`.claude/skills/` é OK pq versionado git)
4. Hint pessoal Wagner-only EXPLICITAMENTE pedido por ele

Em qualquer outro caso, hook bloqueia e pede migração pro git.

## Refs canônicos no MCP

- **ADR 0059** — Governança Anthropic Team plan adaptado (10 pilares)
- **ADR 0053** — MCP server governança como produto
- **ADR 0055** — Self-host equivalente Anthropic
- **ADR 0057** — Regras tela team (token + DXT)
- **ADR 0040** — Claude supervisiona (não pergunta sobre rotineira reversível)

Lê via `decisions-fetch slug:"NNNN-..."`.

---

**Se eu (Claude) violar essa regra:** Wagner pode pedir `cc-search query:"Read memory"` no MCP pra auditar quantas vezes fiz Read filesystem na sessão atual + última 30 dias. Dados saem do `mcp_cc_messages.tool_name`. Métrica reflexiva.
