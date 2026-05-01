# MANUAL — Memórias do projeto oimpresso (pro novo usuário)

> **Pra quem é:** todo dev novo (humano OU agente IA — Claude, Cursor, outro) que nunca trabalhou neste projeto.
>
> **Tempo de leitura:** 5 minutos.
>
> **O que você vai aprender:** onde está cada tipo de conhecimento do projeto, como achar, e como contribuir sem violar governança (ADR 0061 + 0063).

---

## 1. O modelo mental em 30 segundos

O projeto tem **3 fontes de verdade** ordenadas por prioridade:

| # | Fonte | Onde mora | Quando usar |
|---|---|---|---|
| **1ª** | **MCP server** `mcp.oimpresso.com` | container CT 100 Proxmox | Conhecimento canônico (ADR, session, SPEC, task, perfil) — **sempre primeiro** |
| **2ª** | **Servidores de produção** | Hostinger (app + MySQL `oimpresso`); CT 100 (Meilisearch, Telescope) | Dados vivos — faturamento real, métricas runtime, logs IA |
| **3ª** | **Filesystem local** | `D:\oimpresso.com\memory\` | APENAS com permissão explícita do user |

Hook `.claude/hooks/mcp-first.ps1` BLOQUEIA Read/Glob/Grep em paths canônicos (`memory/decisions/`, `memory/sessions/`, `CURRENT.md`, etc.) com `decision: deny`. Override pra dev local: editar `.claude/settings.local.json` (gitignored).

**Por quê:** filesystem local desincroniza, não tem auditoria LGPD, e gasta 73% mais tokens. MCP é fonte viva, governada, auditada e barata. Ver [ADR 0063](decisions/0063-hierarquia-fontes-mcp-servidor-filesystem.md).

---

## 2. Mapa pergunta → tool MCP

Memorize esta tabela. Toda pergunta do projeto cai numa destas 7 tools.

| Quero saber | Tool MCP |
|---|---|
| Estado do cycle, sprint, tasks ativas | `tasks-current` |
| ADR sobre tema X | `decisions-search query:"X"` |
| ADR completa | `decisions-fetch slug:"NNNN-..."` |
| Última sessão, histórico de trabalho | `sessions-recent limit:5` |
| Fato persistente sobre business (cliente) | `memoria-search query:"..."` |
| Sessão Claude Code de outro dev do time | `cc-search query:"..."` |
| Quanto eu consumi de Claude Code | `claude-code-usage-self` |

**Exemplo prático:**

```
> Qual a stack IA do projeto?
→ decisions-search query:"stack IA canonica"
  → top hit: ADR 0035
  → decisions-fetch slug:"0035-stack-ai-canonica-wagner-2026-04-26"
```

Não vá ao `memory/decisions/0035-...md` direto — o hook bloqueia E o MCP é mais rápido.

---

## 3. Anatomia da memória (onde escrever cada tipo)

Quando você for **criar** ou **atualizar** conhecimento, escolhe o caminho certo:

| Tipo | Caminho git | Quando usar |
|---|---|---|
| **Decisão arquitetural** | `memory/decisions/NNNN-slug.md` (formato Nygard) | "Por que escolhemos X em vez de Y" |
| **Decisão específica de módulo** | `memory/requisitos/{Modulo}/adr/{arq\|tech\|ui}/NNNN-slug.md` | Decisões locais que não afetam plataforma |
| **SPEC de módulo** | `memory/requisitos/{Modulo}/SPEC.md` | User stories + requisitos funcionais |
| **Runbook (procedimento operacional)** | `memory/requisitos/{Modulo}/RUNBOOK-tema.md` | "Como fazer X passo-a-passo" |
| **Audit / health check** | `memory/requisitos/{Modulo}/audits/YYYY-MM-DD.md` | Snapshot do estado em data específica |
| **Architecture overview** | `memory/requisitos/{Modulo}/ARCHITECTURE.md` | Estrutura técnica do módulo |
| **Glossário de termos** | `memory/requisitos/{Modulo}/GLOSSARY.md` | Vocabulário do domínio |
| **Changelog** | `memory/requisitos/{Modulo}/CHANGELOG.md` | Keep-a-Changelog format |
| **Comparativo competitivo** | `memory/comparativos/slug_capterra.md` | Estilo Capterra/G2 — concorrência |
| **Session log** | `memory/sessions/YYYY-MM-DD-slug.md` | Histórico cronológico de sessão de trabalho |
| **Handoff canônico** | `memory/08-handoff.md` (apenda no final) | Estado vivo entre sessões |
| **Estado do cycle** | `CURRENT.md` (sobrescreve) | Foto do agora, refresh a cada cycle |
| **Backlog completo** | `TASKS.md` | Todas as tasks abertas/fechadas |
| **Equipe** | `TEAM.md` | Perfis, WIP, matriz quem-pode-pegar-o-quê |
| **Acesso/SSH/credencial** | `INFRA.md` (apenda) + Vaultwarden | Endpoints, fixes manuais |
| **Convenção projeto** | `memory/04-conventions.md` (apenda) | Padrões de código/processo |
| **Preferência coletiva** | `memory/05-preferences.md` (apenda) | Quirks de cliente, preferências do time |

**O que NÃO criar:**

- ❌ Auto-mem privada em `~/.claude/projects/*/memory/*.md` — hook `block-automem.ps1` BLOQUEIA. ADR 0061 proíbe: silos invisíveis pro time, sem code review, sem versionamento.
- ❌ README.md genérico do tipo "este projeto faz X" — usa `memory/requisitos/{Modulo}/README.md` que tem estrutura padronizada.

---

## 4. Fluxo: do filesystem ao MCP queryable

Quando você cria/edita um doc canônico, ele só vira queryable via MCP **depois deste fluxo**:

```
1. Você edita memory/decisions/0064-novo.md
2. git add + git commit
3. git push (origin/claude/sua-branch ou main após merge)
4. GitHub webhook dispara → POST mcp.oimpresso.com/api/mcp/sync-memory
5. Service IndexarMemoryGitParaDb processa: PII redactor + frontmatter parser + UPSERT em mcp_memory_documents
6. Pronto — em <60s qualquer dev do time pode decisions-search query:"novo"
```

**Se você não fizer push, ninguém do time enxerga.** Por isso a skill `memory-sync` existe — ela lembra você de commitar+pushar antes de encerrar sessão.

Slash command pronto: `/sync-mem` commita + pusha tudo em `memory/` e governança (CURRENT/TASKS/TEAM/CLAUDE/DESIGN/INFRA).

---

## 5. Quick start — 5 comandos pra começar

Depois de fazer onboarding com [`oimpresso-team-onboarding`](`.claude/skills/oimpresso-team-onboarding/SKILL.md`) (token MCP + SSH + Vaultwarden + Tailscale — ver [`RUNBOOK-wagner-liberar-acesso-time.md`](requisitos/Infra/RUNBOOK-wagner-liberar-acesso-time.md)), rode:

```
1. tasks-current                                            # estado do cycle
2. sessions-recent limit:3                                  # o que aconteceu nos últimos dias
3. decisions-search query:"<modulo que você vai mexer>"     # ADRs do seu domínio
4. memoria-search query:"<cliente ou tema>"                 # fatos do business
5. cc-search query:"<problema parecido>"                    # outros devs do time já resolveram?
```

**5 minutos depois disso você sabe:**
- O que precisa ser feito agora (cycle goal)
- O que mudou recente
- As decisões arquiteturais do seu módulo
- Histórico do cliente
- Se alguém já resolveu problema parecido

---

## 6. Anti-padrões (não fazer)

| ❌ Anti-padrão | ✅ Padrão correto |
|---|---|
| `Read memory/decisions/0053-mcp-server-...md` | `decisions-fetch slug:"0053-mcp-server-governanca-como-produto"` |
| `Glob memory/decisions/*MCP*` | `decisions-search query:"MCP"` |
| `Read CURRENT.md` | `tasks-current` |
| Criar `~/.claude/projects/*/memory/anotacao.md` | Migrar pra `memory/decisions/` ou `memory/sessions/` no repo |
| Commit sem push (esquece) | `/sync-mem` no fim da sessão |
| Push direto na main com mudança crítica | Branch `claude/feature` + PR + ADR 0040 (Wagner aprova publicação externa) |
| ADR sem `Status` / `Decisores` / `Data` no header | Formato Nygard completo (template em `memory/decisions/_template.md`) |
| Editar arquivo no servidor SSH sem commit | Sempre worktree git → commit → deploy (ADR 0061 — drift Eliana 3.7→6.7 queimou) |
| Pôr CPF/CNPJ real em PR/commit/log | `[REDACTED]` mesmo em dev (ADR 0030) |

---

## 7. Skills auto-ativáveis que ajudam

Skills em `.claude/skills/<nome>/SKILL.md` ativam quando o `description:` casa com a tarefa. Pra novo usuário no projeto, as essenciais:

| Skill | Quando ativa |
|---|---|
| `oimpresso-mcp-first` | SEMPRE no início da sessão — força hierarquia MCP > SSH > filesystem |
| `oimpresso-stack` | Trabalho técnico (Laravel 13.6, PHP 8.4, Inertia v3, multi-tenant UltimatePOS) |
| `oimpresso-team-onboarding` | 1ª vez no projeto — configura MCP token + SSH + Vault + Tailscale |
| `multi-tenant-patterns` | Editar Model/Controller/Job que toca `business_id` |
| `publication-policy` | Antes de push/PR/deploy/postagem externa |
| `runtime-rules-hostinger-ct100` | SSH no Hostinger ou CT 100 |
| `memory-sync` | Após criar/editar doc em `memory/` — lembra de commitar+pushar |
| `/continuar` (slash) | Retomar sessão de onde parou |
| `/sync-mem` (slash) | Commit + push tudo de `memory/` + governança |

**Não precisa decorar** — elas auto-ativam pela `description` quando relevante. Mas conhecer ajuda a confiar nelas.

---

## 8. Quando algo der errado

| Sintoma | Diagnóstico | Solução |
|---|---|---|
| `decisions-search` retorna vazio | Doc novo ainda não indexado | Aguarda 60s pós-push; se persistir, força `php artisan mcp:sync-memory` no CT 100 |
| MCP tool retorna 401 | Token expirado/revogado | Pede novo token pro Wagner via `/copiloto/admin/team` |
| MCP tool retorna 403 | Sem permission `copiloto.mcp.use` | Wagner atribui via tinker |
| MCP tool retorna 429 | Quota diária excedida | Aguarda reset 00:00 BRT ou Wagner libera |
| Read em `memory/...` falha com `decision: deny` | Hook mcp-first bloqueando | É esperado — usar tool MCP correta. Override só com permissão explícita do Wagner |
| Auto-mem `~/.claude/.../memory/*.md` falha | Hook `block-automem` bloqueando | É esperado (ADR 0061) — migrar pra git no caminho correto (tabela §3) |
| MCP server caiu | CT 100 down ou container stopped | Fallback: SSH no CT 100 (`docker ps`, `docker logs mcp-oimpresso`); enquanto isso usa SSH Hostinger pra dados vivos |

---

## 9. Como melhorar este sistema

Quando perceber padrão repetitivo (3+ vezes mesma pergunta), proponha:

- **ADR nova** — se é decisão arquitetural ainda não registrada
- **Skill nova** — se é trigger automático recorrente
- **RUNBOOK novo** — se é procedimento manual que vai repetir
- **Tool MCP nova** — se MCP atual não cobre (raro — atual cobre 95%)

Fluxo de proposta: branch + commit + PR + Wagner aprova (ADR 0040). Sem PR não vira canônico.

---

## 10. TL;DR — 5 regras de ouro

1. 🔴 **MCP primeiro, sempre.** Filesystem só com permissão.
2. 🔴 **Tudo canônico vai pra git.** Auto-mem privada é proibida (ADR 0061).
3. 🔴 **Push pra time enxergar.** Sem push, MCP não indexa, ninguém vê.
4. 🔴 **Formato Nygard pra ADR.** Template em `memory/decisions/_template.md`.
5. 🔴 **Slash `/continuar` no início, `/sync-mem` no fim.** Sessão sem fricção.

---

## Referências

- [ADR 0027](decisions/0027-gestao-memoria-roles-claros.md) — Gestão de memória (papéis canônicos, meta-ADR)
- [ADR 0061](decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0063](decisions/0063-hierarquia-fontes-mcp-servidor-filesystem.md) — Hierarquia de fontes
- [ADR 0053](decisions/0053-mcp-server-governanca-como-produto.md) — MCP como produto governado
- [ADR 0059](decisions/0059-governanca-memoria-estilo-anthropic-team.md) — Governança Anthropic Team plan adaptada
- [`RUNBOOK-wagner-liberar-acesso-time.md`](requisitos/Infra/RUNBOOK-wagner-liberar-acesso-time.md) — Setup completo de acesso pra dev novo
- [`CLAUDE.md`](../CLAUDE.md) — Primer técnico do projeto (§0 = hierarquia inviolável)

---

> **Última atualização:** 2026-04-30 — criação inicial pela Eliana[E+C]. Manual será revisado quando RUNBOOK Wagner for executado e novo dev fizer onboarding real (feedback loop).
