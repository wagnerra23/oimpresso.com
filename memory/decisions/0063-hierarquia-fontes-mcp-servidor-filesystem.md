# ADR 0063 — Hierarquia de fontes: MCP > servidor produção > filesystem

**Status:** ✅ Aceita
**Data:** 2026-04-30
**Decisores:** Wagner [W] (formalizado), Eliana [E] (reforçado em sessão 30-abr)
**Tags:** governanca · mcp · hooks · filesystem · ssh · hostinger · ct100 · lgpd
**Relacionado:** [ADR 0053](0053-mcp-server-governanca-como-produto.md) · [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) · [ADR 0040](0040-policy-publicacao-claude-supervisiona.md)

## Contexto

Claude Code, ao iniciar sessão, tendia a `Read` filesystem (`memory/decisions/`, `CURRENT.md`, `TASKS.md`, `TEAM.md`) em vez de tools MCP. Resultado:

- **73% mais tokens** medido empiricamente (CURRENT + handoff + 1 ADR via Read = ~14.888 tokens; mesmo via tool MCP = ~3.928)
- **Sem auditoria** — `mcp_audit_log` (LGPD Art. 18) não captura Read filesystem
- **Drift** — filesystem local pode estar desatualizado vs servidor (webhook GitHub→MCP sincroniza em <60s)
- **Frustração do time** — Eliana [E] em 2026-04-30: *"só o MCP é válido. Não use nenhuma outra fonte vai me deixar frustrado"*; reforçado: *"tens acesso aos servidores da empresa, Hostinger, Proxmox — quero que entenda que vai procurar lá sempre"*

A skill `oimpresso-mcp-first` existia mas em modo lembrete (warning-only via `mcp-first-warning.ps1`). Não impediu reincidência.

## Decisão

**Hierarquia de 3 níveis com bloqueio físico:**

### 1ª fonte — MCP server (`mcp.oimpresso.com`)

Conhecimento canônico do projeto. Sempre primeiro.

| Pergunta | Tool MCP |
|---|---|
| Estado do cycle/sprint | `tasks-current` |
| ADR sobre tema X | `decisions-search query:"X"` |
| ADR completa | `decisions-fetch slug:"NNNN-..."` |
| Última sessão | `sessions-recent limit:N` |
| Fato persistente do business | `memoria-search query:"..."` |
| Sessão Claude Code do time | `cc-search query:"..."` |
| Custo pessoal | `claude-code-usage-self` |

### 2ª fonte — Servidores de produção (quando MCP não cobre)

Dados vivos que MCP não indexa.

**Hostinger** — app web `oimpresso.com`, MySQL `oimpresso`:
- Faturamento real (`transactions`, `transaction_payments`)
- Tabelas Copiloto (`copiloto_memoria_facts`, `copiloto_memoria_metricas`, `copiloto_memoria_gabarito`)
- Tabelas MCP (`mcp_audit_log`, `mcp_memory_documents`, `mcp_*`)
- Logs runtime: `storage/logs/copiloto-ai.log`, `storage/logs/otel-gen-ai.log`
- Telescope (DEV/staging)

**CT 100 Proxmox** (192.168.0.50, Tailscale):
- MCP server (`mcp.oimpresso.com` container, FrankenPHP)
- Meilisearch v1.10.3 (index `copiloto_memoria_facts` com hybrid embedder)
- Reverb (descontinuado mas ainda corre — ADR 0058)
- Centrifugo (real-time canônico, em deploy — ADR 0058)
- Vaultwarden (`vault.oimpresso.com` — todas as senhas/tokens)
- Traefik (TLS, certs LE)
- Portainer (Docker admin)

### 3ª fonte — Filesystem local

APENAS com permissão explícita do user na mensagem atual. Casos raros e legítimos:
- Edição de arquivo existente (Edit/Write requer Read interno)
- Criação de ADR/SPEC novo (pré-commit local)
- Debug de hook/skill antes de versionar

## Implementação

| Arquivo | Mudança |
|---|---|
| `.claude/hooks/mcp-first.ps1` | NOVO — block hook (substitui `mcp-first-warning.ps1`). Retorna `decision: deny` em Read/Glob/Grep batendo em allowlist; mensagem mapeia path → tool MCP + SSH fallback |
| `.claude/settings.json` | Aponta `PreToolUse` Read|Glob|Grep pro novo hook |
| `.claude/skills/oimpresso-mcp-first/SKILL.md` | Description estendida com hierarquia 3 níveis + tabelas de mapeamento |
| `CLAUDE.md` | §0 inviolável no topo (antes de §1) — primeiro que agente lê |
| `memory/decisions/0063-...md` | Esta ADR |

### Allowlist do hook (paths bloqueados)

```
memory/decisions/
memory/sessions/
memory/requisitos/.*\.md
memory/comparativos/
memory/08-handoff
memory/04-conventions
memory/05-preferences
memory/00-user-profile
memory/INDEX
CURRENT.md
TASKS.md
TEAM.md
INFRA.md
DESIGN.md
AGENTS.md
```

CLAUDE.md **não** está na allowlist — é primer auto-load do harness Claude Code.

### Override pra dev local

Pra desligar temporariamente em dev (sem afetar time):

```jsonc
// .claude/settings.local.json (gitignored)
{
  "hooks": {
    "PreToolUse": []
  }
}
```

Time inteiro herda via `.claude/settings.json` (commitado).

## Consequências

**Positivas:**
- Tokens reduzidos comprovadamente (73% medido)
- Auditoria LGPD garantida — todo acesso a knowledge passa por `mcp_audit_log`
- RBAC fino — token revogável <30s
- Cross-tenant safe — token só vê dados do `business_id`
- Cross-dev consistente — Wagner/Felipe/Maíra/Luiz/Eliana enxergam tudo igual
- Drift eliminado — servidores são fonte viva

**Negativas:**
- Override mais cerimonioso (editar settings.local.json) — aceito
- MCP off-line vira bloqueador → mitigado por SSH fallback (2ª fonte)
- Edição de ADR/SPEC novo precisa Edit/Write que requer Read interno → resolvido permitindo Read em paths fora da allowlist (CLAUDE.md, settings, skills, etc.)

## Alternativas consideradas

1. **Apenas warning (status quo `mcp-first-warning.ps1`)** — rejeitada: já provada insuficiente, Eliana frustrou
2. **Bloqueio total filesystem** — rejeitada: edição precisa Read; quebra fluxo de criar ADR/SPEC novo
3. **Permission rules em settings.json** — rejeitada: granularidade insuficiente, sem mensagem custom
4. **Hook + override por env var** — rejeitada: tools Claude Code (Read) não passam env

## Métricas pós-implementação

Reavaliar em **30 dias (2026-05-30)**:

- Quantos `decision: deny` o hook gerou? (proxy: quantas vezes Claude tentou filesystem em vez de MCP)
- Tokens médios por sessão caíram?
- Frustração do time reduziu (sinais qualitativos)?
- Quantas vezes precisamos override no `.claude/settings.local.json`?

Se hook está disparando >50× por dia, regra precisa ser melhor internalizada via training/skill (não via deny).

## Referências

- [ADR 0053](0053-mcp-server-governanca-como-produto.md) — MCP server governança
- [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0040](0040-policy-publicacao-claude-supervisiona.md) — Claude supervisiona
- [ADR 0046](0046-chat-agent-gap-contexto-rico.md) — Chat agent contexto rico
- Skill `.claude/skills/oimpresso-mcp-first/SKILL.md`
- Skill `.claude/skills/oimpresso-team-onboarding/SKILL.md`
