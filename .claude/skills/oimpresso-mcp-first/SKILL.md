---
name: oimpresso-mcp-first
description: ATIVAR SEMPRE no INÍCIO da sessão e antes de qualquer Read/Glob/Grep/Bash que toque memory/, decisions/, sessions/, requisitos/, TASKS.md, CURRENT.md, TEAM.md, handoff, SPEC, ADR, ou qualquer pergunta sobre estado/tasks/skills/perfis do projeto oimpresso. Tool MCP é a ÚNICA fonte válida; filesystem só com permissão explícita. Eliana e Wagner ficam frustrados quando Claude lê filesystem em vez de MCP.
---

# Skill: oimpresso-mcp-first

🔴 **REGRA FUNDAMENTAL** — Wagner formalizou em 2026-04-30; **Eliana reforçou 2026-04-30 (frustrada)**:

> *"seu conhecimento é MCP"* — Wagner
> *"só o MCP é válido. Não use nenhuma outra fonte vai me deixar frustrado."* — Eliana [E], 2026-04-30

**Default obrigatório:** ao iniciar sessão E antes de QUALQUER pergunta sobre projeto (tasks, ADR, perfil, skills, estado, infra, decisão, sessão), use **exclusivamente** tools MCP. Filesystem (Read/Glob/Grep/Bash em `memory/`, `TASKS.md`, `CURRENT.md`, `TEAM.md`, `INFRA.md`, `DESIGN.md`, etc.) **só com permissão explícita** do usuário na própria mensagem.

**Se a tool MCP não retornar o que precisa:** diga isso ao usuário e peça permissão antes de cair em filesystem. Não caia em silêncio.

## Hierarquia de fontes (ADR 0063 — INVIOLÁVEL)

| # | Fonte | Quando usar | Como acessar |
|---|---|---|---|
| **1ª** | **MCP server** `mcp.oimpresso.com` | Conhecimento canônico do projeto (ADR, session, SPEC, task, perfil time) | Tools `tasks-current` / `decisions-search` / `decisions-fetch` / `sessions-recent` / `memoria-search` / `cc-search` / `claude-code-usage-self` |
| **2ª** | **Servidores de produção** (quando MCP não cobre) | Dados vivos: faturamento real, métricas runtime, logs IA, estado de containers | **Hostinger SSH** (app + MySQL `oimpresso` — `mcp_*`, `copiloto_memoria_*`, `transactions`, logs `storage/logs/copiloto-ai.log`); **CT 100 Proxmox/Docker** (MCP server, Meilisearch index, Reverb, Vaultwarden, Telescope, Centrifugo) |
| **3ª** | **Filesystem local** | APENAS com permissão explícita do user na mensagem atual | Read/Glob/Grep — bloqueado pelo hook `.claude/hooks/mcp-first.ps1` em paths canônicos |

## Mapeamento pergunta → tool MCP

| Quero saber | NÃO faça | Faça |
|---|---|---|
| Estado do cycle | `Read CURRENT.md` | `tasks-current` |
| ADR sobre X | `Glob memory/decisions/*X*` + Read | `decisions-search query:"X"` |
| ADR completa | `Read memory/decisions/0053-...md` | `decisions-fetch slug:"0053-..."` |
| Última sessão | `ls memory/sessions/ -t \| head -1` | `sessions-recent limit:1` |
| Fato persistente do business | (sem fonte) | `memoria-search query:"..."` |
| Sessão Claude Code do time | (sem fonte) | `cc-search query:"..."` |
| Quanto Wagner consumiu | (estimar) | `claude-code-usage-self` |
| Perfil/WIP do time | `Read TEAM.md` | `decisions-search query:"TEAM perfis"` |
| Acesso SSH/credencial | `Read INFRA.md` | `decisions-search query:"INFRA SSH"` + Vaultwarden |

## Quando cair em SSH (2ª fonte)

| Pergunta | Servidor | Comando exemplo |
|---|---|---|
| Faturamento real biz=4 | Hostinger | `mysql oimpresso -e "select sum(final_total) from transactions where business_id=4 and type='sell' and transaction_date >= ..."` |
| Logs IA de hoje | Hostinger | `tail -200 storage/logs/copiloto-ai.log` |
| Métricas Meilisearch index | CT 100 | `curl -H "Authorization: Bearer $KEY" https://meilisearch.oimpresso.com/indexes/copiloto_memoria_facts/stats` |
| Status containers Docker | CT 100 | `docker ps`, `docker logs <name>` |
| Audit log MCP | CT 100 ou Hostinger MySQL | `select * from mcp_audit_log order by id desc limit 50` |

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
