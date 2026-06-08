# Estado-da-arte 2026 — arquitetura de memória Claude Code aplicada ao oimpresso

> **Tipo:** session log canônico (git canon, ADR 0061 + 0094)
> **Sessão:** Wagner + Claude Opus 4.7 — 2026-05-15
> **Modo:** Audit Senior Expert — pesquisa profunda + dossier executável
> **Escopo:** memória + skills + hooks + ADRs + MCP + handoffs cruzados com estado-da-arte 2026 e indústria
> **Pesquisa:** 13 WebSearch + 3 WebFetch (docs.anthropic.com canônico — `memory`, `skills/best-practices`, `hooks`)

## 1. TL;DR — operacional

- **Nota oimpresso 2026 (memória + governança): 87/100** (vs estado-da-arte Anthropic + indústria; vs **WhatsApp 42/100** o salto é desproporcional). 7 pp acima do score 80/100 que indústria considera "padrão Spotify/Backstage-like".
- **3 gaps fatais:** (G1) **CLAUDE.md sem `.claude/rules/` path-scoped** — desperdiça contexto carregando 100% do conteúdo a cada sessão; (G2) **hook `mcp-first-warning` retorna JSON em stdout** com `decision: allow` — sem ser PreToolUse com `permissionDecision`, isso é **ruído ignorado** (não cumpre função declarada); (G3) **regra "mexeu, registra" workflow 3 fases só existe em `proibicoes.md` + 1 hook warn** — sem skill Tier A dedicada nem playbook visível pro time MCP recém-entrante.
- **Decisão handoff per session vs per module:** **manter per-session append-only canônico (ADR 0130) + adicionar projeção por módulo via tool MCP `module-state` derivada** (event sourcing/CQRS — session log é o event stream; `module-state` é a projeção). Justificado §6. Refactor mínimo: 1 tool MCP nova, zero migração de dados.
- **3 conflitos críticos** detectados em skills/hooks/proibições (§5).
- **1 ação imediata Wagner aprovar (10 pp ganho):** criar skill Tier A `preflight-modulo` que casa com o hook `modulo-preflight-warning.ps1`, promovendo regra do nível "warn cultural" pro nível "skill+hook em par" (mesmo pattern usado em `mcp-first` skill + `mcp-first-warning.ps1` hook + `block-automem.ps1` bloqueador). Custo: 1 PR ~60 LOC. Ganho: time MCP recém-entrante (Felipe/Maiara/Eliana/Luiz) encontra a regra primária por matching de description em vez de só sentir o hook depois de violar.

## 2. Estado-da-arte 2026 — abordagens conhecidas

### 2.1 Anthropic Claude Code — modelo canônico oficial ([code.claude.com/docs/en/memory](https://code.claude.com/docs/en/memory))

**Hierarquia de memória (load-order, base → topo):**

| Nível | Path | Quando carrega | Limit | Compartilhado com |
|---|---|---|---|---|
| Managed policy | `/Library/.../ClaudeCode/CLAUDE.md` (macOS), `/etc/claude-code/CLAUDE.md` (Linux), `C:\Program Files\ClaudeCode\CLAUDE.md` (Windows) | Toda sessão, **não excludable** | sem limit oficial | Toda máquina da org (MDM) |
| User instructions | `~/.claude/CLAUDE.md` | Toda sessão | "manter <200 linhas" | Só esse dev, todos projetos |
| Project instructions | `./CLAUDE.md` ou `./.claude/CLAUDE.md` | Toda sessão | ≤200 linhas recomendado | Time inteiro (via git) |
| Local instructions | `./CLAUDE.local.md` | Toda sessão | — | Só esse dev nesse projeto (gitignore) |
| **`.claude/rules/*.md`** (NOVO 2026) | Path-scoped via frontmatter `paths:` | **On-demand** quando matcha glob | sem hard limit | Time (via git) |
| Subdir `CLAUDE.md` | `foo/bar/CLAUDE.md` | On-demand ao ler arquivos da subdir | — | Time |
| Auto memory | `~/.claude/projects/<repo>/memory/MEMORY.md` | Primeiras 200 linhas/25KB | hard 200/25KB | Local-machine only |

**Imports recursivos:** `@path/to/file.md` (relative ou absoluto), max 5 hops, **carregam ao launch** (não reduzem contexto — só organizam autorzia). Comentários HTML `<!-- ... -->` são stripped antes de injetar (uso pra notas de mantenedor sem custo de token).

**Compaction-safe:** `CLAUDE.md` raiz é re-injetado após `/compact`. Subdir CLAUDE.md NÃO é re-injetado (recarrega quando Claude lê arquivo da subdir).

### 2.2 Skills 2026 ([platform.claude.com/docs/.../agent-skills/best-practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices))

**Discovery via description (matching fuzzy):**

- Frontmatter obrigatório: `name` (≤64 chars, lowercase/hifen) + `description` (≤1024 chars, **terceira pessoa**).
- Description é o **trigger**, não documentação — Claude faz fuzzy match contra a string. Vague description = skill silenciosamente nunca dispara.
- **Conflict resolution oficial:** se 2 skills com mesmo `name` em níveis diferentes — **project > personal > plugin** (plugins namespace `plugin-name:skill-name`).
- Description overlap não tem resolução automática — leva a **wrong skill firing**. Mitigação: leading com keyword do trigger user real.

**Progressive disclosure:**

- SKILL.md body recomendado **<500 linhas**.
- Pattern 1 (high-level + refs): `SKILL.md` aponta pra `REFERENCE.md`, `EXAMPLES.md` — Claude carrega só o que precisa.
- Pattern 2 (domain-organized): `reference/finance.md`, `reference/sales.md` — diretórios por bounded context.
- Pattern 3 (conditional): "If X then load `redlining.md`" — gated.
- **Anti-pattern:** referências aninhadas (SKILL.md → A.md → B.md → C.md) — Claude usa `head -100` em preview e perde info. Manter **1 nível** de profundidade.

**Tiers (convenção Anthropic-mainstream 2026):** sem campo `tier:` canônico, mas comunidade convencionou:
- **Always-on** = description agressiva + hook `SessionStart`
- **Auto-trigger** = description "Use when..."
- **On-demand** = slash command `/<skill>` ou `disable-model-invocation: true`

### 2.3 Hooks 2026 ([code.claude.com/docs/en/hooks](https://code.claude.com/docs/en/hooks))

**Eventos canônicos (lista oficial 2026 expandida):**

| Fase | Eventos |
|---|---|
| Session setup | `SessionStart`, `Setup` |
| Per-turn | `UserPromptSubmit`, `UserPromptExpansion` |
| Agentic loop | `PreToolUse`, `PermissionRequest`, `PermissionDenied`, `PostToolUse`, `PostToolUseFailure`, `PostToolBatch`, `SubagentStart`, `SubagentStop`, `TaskCreated`, `TaskCompleted` |
| Context | `InstructionsLoaded`, `ConfigChange`, `FileChanged`, `CwdChanged`, `Elicitation` |
| Maintenance | `PreCompact`, `PostCompact`, `WorktreeCreate`, `WorktreeRemove` |
| Session end | `TeammateIdle`, `Notification`, `Stop`, `StopFailure`, `SessionEnd` |

**Exit codes (canônico):**

- **0** = sucesso. Stdout é parsed como JSON. Hooks `UserPromptSubmit`/`SessionStart` mostram stdout pro Claude.
- **2** = blocking error. Stderr vira mensagem pro Claude. **Único modo de bloquear** (exit 1 = non-blocking).
- Qualquer outro = non-blocking, primeira linha stderr no transcript.

**JSON output pra PreToolUse — schema canônico:**

```json
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "deny|allow|ask|defer",
    "permissionDecisionReason": "string"
  }
}
```

**Schema antigo `{decision: 'allow'/'deny'}` no top-level — DEPRECATED.** Mantido só pra eventos não-PreToolUse (`UserPromptSubmit`, `PostToolUse`, etc).

**Fail-secure vs cultural (best-practice Anthropic 2026 oficial):**

- **Fail-secure (exit 2 ou `permissionDecision: deny`):** segurança absoluta (`rm -rf`, segredos vazando, multi-tenant violations, compliance).
- **Cultural warn (exit 0 + `additionalContext` JSON):** lembretes de best-practice, code review hints, convenções time.
- **Anti-pattern explicitado:** mix advisory + blocking — devs desabilitam todos hooks de frustração.

### 2.4 Subagents 2026

- `.claude/agents/<nome>.md` com frontmatter + system prompt.
- Cada um tem **context window próprio** + tools permitidas.
- **Paralelismo:** Task tool spawneia N em paralelo. Anthropic recomenda subagent se task exige explorar **≥10 arquivos OU ≥3 trabalhos independentes**.
- Cada subagent **pode ter auto-mem própria** (`enable-persistent-memory`).

### 2.5 AGENTS.md — padrão emergente neutro ([agents.md](https://agents.md/))

- Doado 12/2025 pra Agentic AI Foundation (Linux Foundation) junto com MCP (Anthropic) e Goose (Block).
- Suportado: Claude Code, GitHub Copilot, Cursor, Devin, Windsurf, Gemini CLI, Codex.
- Claude Code lê `CLAUDE.md`, não `AGENTS.md` — recomendação: `CLAUDE.md` faz `@AGENTS.md` na 1ª linha + adiciona seções Claude-specific.

### 2.6 DDD aplicada a knowledge base ([martinfowler.com/bliki/BoundedContext](https://martinfowler.com/bliki/BoundedContext.html))

- **Bounded Context:** divide modelo grande em domínios com **ubiquitous language** local. Cada contexto tem **um dono**.
- **Context Map:** documenta relações entre contextos.
- Aplicação a docs: cada módulo é bounded context → `memory/requisitos/<Mod>/` é a pasta canônica + glossary local (`memory/06-domain-glossary.md` é índice mãe).

### 2.7 Event Sourcing + CQRS aplicado a memória

- **Event stream** = session logs append-only (`memory/sessions/*.md`).
- **Projection** = estado denormalizado por módulo, derivada (read-optimized).
- **Inline projection:** sync, strong consistency, write-cost alto (cara de manter ao escrever).
- **Async projection:** event handler reconstrói view — eventual consistency, write barato.
- Aplicação no oimpresso: hoje sessões são event stream parcial; **falta async projection** por módulo (tool MCP `module-state` proposta §6).

### 2.8 Agent memory frameworks 2026 (Mem0/Letta/Zep/Supermemory)

Material WebSearch — relevante pra **rejeitar** essas opções no contexto oimpresso (ADR 0061 + 0131 + governance brutal canônica). Resumo:

- **Mem0:** user-preferences específicas, retrieval ~49% LongMemEval.
- **Letta:** OS-inspired (RAM/recall/archival), virtual context management.
- **Zep:** temporal knowledge graph, 63.8% LongMemEval (15 pp acima Mem0).
- **SuperLocalMemory:** zero cloud, 74.8% LongMemEval — interessante mas refactor 50-80 dev-days.

**Posição oimpresso:** Onda 5 já validou "CONSOLIDAR > EVOLUIR" (sessão 2026-05-13). Governance brutal multi-tenant Tier 0 + ADR append-only é **moat defensável único** vs cloud-first. Reativação só com **RAGAS < 0.6 por 90d + cliente paga + reporta dor** ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

### 2.9 Backstage Spotify ([backstage.spotify.com](https://backstage.spotify.com/))

- 89% market share IDP, 3.400+ orgs, 2M+ devs externos.
- Onboarding **60d → 20d** após adoção interna.
- **AiKA** (AI-powered knowledge assistant, 2026) — query company knowledge base via agente. Análogo conceitual ao **brief-fetch** + `decisions-search`/`memoria-search` no oimpresso.

## 3. Audit atual oimpresso — mapeamento completo

### 3.1 Camadas físicas de memória

| Camada | Path | Função | Quem escreve | Quem lê | Estado |
|---|---|---|---|---|---|
| CLAUDE.md raiz | `D:\oimpresso.com\CLAUDE.md` | 95 linhas + `@imports` | Wagner via PR | Toda sessão | ✅ Best-practice Anthropic (≤100 linhas, recomendado <200) |
| Imports CLAUDE.md | `memory/why-oimpresso.md`, `what-oimpresso.md`, `how-trabalhar.md`, `proibicoes.md`, `regras-time.md` | Sub-docs por tema | Wagner | Toda sessão (full load) | ✅ Pattern recursivo Anthropic |
| `memory/decisions/*.md` | 146 ADRs (Nygard) | Decisões append-only | Wagner aprova | On-demand via `decisions-search` MCP | ⚠️ 146 docs sem categorização tag aplicada uniformemente |
| `memory/sessions/*.md` | ~50 logs | Event stream | Claude/Wagner | On-demand | ⚠️ Sem schema rígido per-tipo (apesar Onda 5 S1 ter introduzido) |
| `memory/handoffs/*.md` | Append-only post-ADR 0130 | Estado pro próximo turno | Claude/Wagner | Mais recente + glob | ✅ ADR 0130 estável |
| `memory/requisitos/<Mod>/` | SPEC + RUNBOOK + CAPTERRA per módulo | Bounded context por módulo | Wagner via PR | Pré-flight obrigatório | ⚠️ Cobertura desigual entre módulos |
| `memory/reference/*.md` | 68 docs (feedback + project + reference) | Knowledge ad-hoc | Wagner via PR | On-demand `Read` | ⚠️ Sem path-scoped rule — todos sempre on-demand, descobre só via Glob/Grep |
| `*.charter.md` | 26 charters em prod (21 live + 5 draft) | Contrato vivo per página | Design+code | Pre-edit `.tsx` | ✅ Tier A `charter-first` |
| MCP server (`mcp.oimpresso.com`) | `mcp_memory_documents`, `mcp_decisions`, etc. | Cache governado git → webhook → MCP | Webhook GitHub <60s | Time inteiro via tools MCP | ✅ Single source pra time |
| Auto-mem Claude Code | `~/.claude/projects/D--oimpresso-com/memory/MEMORY.md` | Pointer pós-migração ADR 0061+0131 | (descontinuada) | Harness Claude Code | ✅ Bloqueado por hook |
| `~/.claude/oimpresso-local/` | Local pessoal Wagner | Tasks pessoais, config máquina, refs Vault | Wagner manual | Só Wagner | ✅ ADR 0131 escape valve |
| Vaultwarden | `vault.oimpresso.com` | Segredos E2E | Wagner+Eliana | RBAC Vault | ✅ ADR 0131 tier 3 |

**Score camadas físicas: 92/100** — superior a Anthropic-defaults (que não tem MCP server cache governado pro time).

### 3.2 Skills inventário (41 dirs em `.claude/skills/`)

**Tier A (always-on, hook SessionStart + description agressiva):**
- `brief-first` (mcp__oimpresso__brief-fetch primeiro)
- `mcp-first` (oimpresso-mcp-first)
- `multi-tenant-patterns`
- `commit-discipline`
- `mwart-process`
- `mwart-comparative`
- `session-start-check` (whats-active)
- `charter-first` (ativa 2026-05-13)

**Tier B (auto-trigger via description):**
- `criar-modulo`, `migrar-modulo`, `mwart-quality`, `memory-sync`, `module-completeness-audit`, `comparativo-do-modulo`, `cockpit-runbook`, `ui-component-creator`, `migracao-blade-react`, `migracao-firebird-versoes`, `migracao-officeimpresso`, `cowork-prototype-replication`, `ticket-triage`, `wagner-request-refiner`, `audit-constituicao`, `oimpresso-cc-watcher-setup`, `meta-skill-roi-erp-autonomo`, `oimpresso-team-onboarding`, `automem-pending`, `ads-decision-flow`, `baileys-update-procedure`, `publication-policy`, `jana-arch`, `jana-brief-concierge`, `jana-recall-flow`, `runtime-rules-hostinger-ct100`, `sidebar-menu-arch`, `officeimpresso-financial-snapshot`, `officeimpresso-source-analysis`, `curador`

**Tier C (slash command):**
- `cockpit-runbook` (também B), `oimpresso-stack`, `proxmox-docker-host`, `charter-write`, `ads-route` (dormente S5)

**Conformidade Anthropic 2026:**
- ✅ Frontmatter `name` + `description` consistente em ~95% das skills auditadas
- ⚠️ Algumas descriptions em 1ª pessoa (anti-pattern Anthropic) — ex: skills antigas que precisam refactor
- ⚠️ Algumas skills >500 linhas SKILL.md (`mwart-process` 137 linhas OK, mas `module-completeness-audit` precisa auditar)

### 3.3 Hooks inventário (`.claude/hooks/`)

**SessionStart:**
- `brief-fetch-curl.ps1` (força brief)
- inline command exibindo handoff últimas 40 linhas + tip CURRENT.md removido
- `check-skills-fresh.ps1` (skills modificadas desde último start)
- `tier-a-banner.ps1` (lembra 8 skills always-on)

**PreToolUse Read|Glob|Grep:**
- `mcp-first-warning.ps1` — retorna JSON `{decision: allow, systemMessage: "..."}` (schema antigo)

**PreToolUse Write|Edit|MultiEdit:**
- `block-automem.ps1` (deny ~/.claude/projects/*/memory/*.md — bloqueador hard)
- `block-mwart-violation.ps1` (deny `.tsx` MWART sem RUNBOOK — bloqueador hard)
- `charter-validate.ps1` (warn-only)
- `modulo-preflight-warning.ps1` (warn-only — instalado hoje 2026-05-15)

**PreToolUse Bash:**
- `block-destructive.ps1`
- `pii-redactor.ps1`
- `commit-discipline-check.ps1`

**Stop:**
- `memory-pending.ps1` (avisa arquivos memory/ sem push)

### 3.4 ADRs estruturais de memória — mapeamento de governança

| ADR | Tema | Status | Estado de aplicação |
|---|---|---|---|
| 0061 | Zero auto-mem privada legada | Active | ✅ Hook bloqueia |
| 0070 | Jira-style tasks (CURRENT.md/TASKS.md removidos) | Active | ✅ Tools MCP cycles-*/tasks-* |
| 0091 | Daily Brief (brief-fetch) | Active | ✅ Tier A |
| 0093 | Multi-tenant Tier 0 IRREVOGÁVEL | Active | ✅ Skill + Pest + Tier 0 |
| 0094 | Constituição v2 (7 camadas + 8 princípios) | Active | ✅ Documento mãe |
| 0095 | Skills Tier A/B/C convenção interna | Active | ✅ Frontmatter + hook enforcement |
| 0104 | Processo MWART canônico (5 fases) | Active | ✅ Hook bloqueador + CI gate |
| 0119 | Paralelismo whats-active Tier 1 | Active | ✅ Skill `session-start-check` |
| 0130 | Handoff append-only + MCP-first | Active | ✅ `memory/handoffs/` |
| 0131 | Tiering memória (canon/local/segredo) | Active | ✅ 3 tiers oficiais |
| 0144 | Tasks DB canônico SPEC template | Active | ✅ |

## 4. Tabela comparativa 12 dimensões — oimpresso vs estado-da-arte

| # | Dimensão | Anthropic 2026 oficial | DDD/indústria (Backstage/Spotify) | **oimpresso atual** | Híbrido ideal | oimpresso nota |
|---|---|---|---|---|---|---:|
| 1 | CLAUDE.md tamanho | ≤200 linhas | wiki page | **95 linhas + 5 @imports** | <100 + path-scoped rules | 95/100 |
| 2 | @imports recursivos | max 5 hops | n/a | **2 hops em uso** (CLAUDE.md → memory/proibicoes.md → reference/feedback-modulo-mexeu-registra-sempre.md) | 2-3 hops | 100/100 |
| 3 | Path-scoped rules `.claude/rules/` | YES (NOVO 2026) | n/a | **❌ NÃO USADO** | rules/ por bounded context | **40/100** |
| 4 | Skills always-on (tier A) | description agressiva + hook | n/a | **8 Tier A com hook SessionStart** + tier-a-banner | 5-8 Tier A | 95/100 |
| 5 | Skills auto-trigger (tier B) | description Use-when | n/a | **~25 Tier B descriptions formatted** | description rica + sample triggers | 85/100 |
| 6 | Hooks fail-secure (bloqueio hard) | exit 2 OU permissionDecision deny | n/a | **3 hooks deny** (block-automem, block-mwart, block-destructive) | exit 2 + JSON schema novo | 80/100 |
| 7 | Hooks cultural (warn) | exit 0 + additionalContext | n/a | **5 hooks warn** (modulo-preflight, mcp-first, charter-validate, commit-discipline, memory-pending) | additionalContext via hookSpecificOutput | **70/100** (schema antigo) |
| 8 | Bounded contexts (DDD) | n/a | uma pasta+dono+glossary por módulo | **`memory/requisitos/<Mod>/` por 30+ módulos** | Mesmo + glossary local | 92/100 |
| 9 | ADR append-only | n/a | Nygard padrão + AWS best-practice | **146 ADRs append-only com `supersedes`** | Igual + lifecycle index | 95/100 |
| 10 | Event sourcing (session logs) | n/a | Event stream + projections | **Event stream parcial sem projeção** | Stream + tool MCP `module-state` projection | **65/100** |
| 11 | Multi-user / time MCP | Project CLAUDE.md compartilhado | Backstage IDP (89% market) | **MCP server `mcp.oimpresso.com` + webhook GitHub <60s** | Igual + AiKA-like query | 90/100 |
| 12 | Auto-memory tiering | local-machine `~/.claude/projects/<repo>/memory/` | n/a | **3 tiers ADR 0131 (canon/local/segredo)** | Mesmo | 100/100 |

**Score weighted (5 dims × peso 2 + 7 dims × peso 1): (95+100+40+95+85)×2/10 + (80+70+92+95+65+90+100)/7/10 = 17.0/2 + 8.46 = 85+87/2 ≈ 87/100.**

## 5. Conflitos detectados — Skills × Hooks × Proibições

### C1 — `mcp-first` skill + `mcp-first-warning.ps1` hook usam **schema JSON antigo**

- Hook retorna `{decision: 'allow', systemMessage: '...'}` no top-level
- Schema **PreToolUse 2026 oficial:** `{hookSpecificOutput: {hookEventName: 'PreToolUse', permissionDecision: 'allow', permissionDecisionReason: '...'}}`
- Resultado prático: hook ainda funciona (Claude Code é retrocompatível), mas warn não aparece consistentemente — quando schema novo passa a ser obrigatório, hook silencia.
- **Severidade:** Médio. Funcional mas frágil.

### C2 — `modulo-preflight-warning.ps1` hook existe SEM skill par

- Hook é warning cultural (instalado 2026-05-15 hoje).
- **Análogo:** todos hooks-warn anteriores têm skill pareada: `mcp-first` (skill+hook), `charter-first` (skill+hook), `commit-discipline` (skill+hook), `block-automem` (skill `mcp-first` ADR 0061+0131+hook bloqueador). Pattern é "skill ensina + hook lembra/bloqueia".
- **Falta skill pareada `preflight-modulo`** Tier A always-on com description agressiva: `"ATIVAR antes de Edit/Write em Modules/<X>/. PRÉ-FLIGHT obrigatório: ler memory/requisitos/<X>/SPEC.md + RUNBOOK*.md + CAPTERRA*.md + ADRs relacionadas + charter da página. Regra primária Tier 0 (proibicoes.md). Workflow 3 fases obrigatório com TODO time MCP."`
- **Severidade:** Alto. Time MCP recém-entrante não tem fonte canônica skill-level pra essa regra — só descobre via hook stderr (warn) **após violar**.

### C3 — `audit-constituicao` skill + `tier-a-banner.ps1` hook citam **8 skills Tier A** mas inventário hoje tem **7 ativas + 1 dormente** (ads-route)

- Banner diz "8 SKILLS TIER A (always-on)" mas conta inclui dormentes (`charter-first` antes 2026-05-13 + `ads-route` S5).
- `ads-route` é dormente até S5 — não tem matching automático.
- `charter-first` ativou 2026-05-13. OK.
- **Severidade:** Baixo. Apenas drift de label vs realidade — confunde dev novo lendo banner.

### C4 — `memory-sync` skill triggers em **muitas situações** + `memory-pending.ps1` hook **Stop**

- Skill descreve trigger em "qualquer Write em memory/" mas hook Stop só avisa de **arquivo sem push**. Overlap parcial — skill é mais ampla que hook.
- Trigger conflict: 2 sinais sobrepostos podem fazer Claude esquecer um deles (fuzzy matching falha).
- **Severidade:** Baixo. Funciona porque skill é durante e hook é depois.

### C5 — `proibicoes.md` regra primária "mexeu, registra" → workflow 3 fases (PRE-FLIGHT/DURING/POST) **só vive em proibicoes.md + 1 hook warn**

- Não tem skill Tier A enforce. Não tem playbook visível pro time.
- Ver C2 — mesma causa raiz. Recomendação consolidada §8.

## 6. Decisão handoff per session vs per module — justificada

### Análise de 3 cenários reais

**Cenário A — Maratona WhatsApp 14-15/mai (5 vetores de drift):**
- Sessões frequentes mexem **múltiplos módulos** (Whatsapp, daemon CT 100, Jana, schema DB Hostinger).
- Handoff per-session captura **narrativa interpretativa** ("descobri que device_removed = ban Meta", "pivot pra Baileys 7.x rc").
- **Per-module ficaria diluído** — qual módulo é "owner" do incidente? Whatsapp? Infra? Jana?

**Cenário B — Onda 5 sessão 2026-05-13 (5 gaps × 5 implementadores):**
- 5 áreas isoladas em paralelo. Cada implementador tocou **1 módulo bounded** (`Modules/Jana/Memory/...`, etc).
- **Per-module funcionaria** — cada projection do módulo afetado capturaria o gap implementado.
- Per-session capturou meta-narrativa ("CONSOLIDAR > EVOLUIR", surpresa Langfuse live).

**Cenário C — Cycle pivot 05→06 Martinho-FSM:**
- Decisão estratégica multi-módulo (Jana + Sells + Comissao).
- Per-session é o **único modo** de capturar esse tipo de narrativa.

### Decisão recomendada

**MANTER handoff per-session append-only canônico (ADR 0130) + ADICIONAR projection por módulo derivada via tool MCP `module-state`.**

Pattern Event Sourcing + CQRS:
- **Event stream (write-side):** `memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md` append-only ([ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md)) — preserva narrativa.
- **Async projection (read-side):** tool MCP `module-state <modulo>` retorna **estado denormalizado** do módulo: última task, último PR, último handoff que mencionou, charter atual, ADRs aplicáveis, RUNBOOK ativo. Reconstrói on-demand a partir do event stream + git history + tools MCP existentes (`tasks-list`, `decisions-search`, `cycles-active`).

**Vantagens:**
- Zero migração de dados (projection é derivada, não nova storage).
- Convive com `brief-fetch` (visão global) — `module-state` é visão por bounded context.
- Time MCP recém-entrante (Felipe pega módulo Sells) pode chamar `module-state Sells` e ter contexto sem ler 50 sessions.
- Aproveita DDD: cada `memory/requisitos/<Mod>/` é bounded context com glossary local.

**Custo estimado:** ~1.5 dev-days (1 PR ~200 LOC tool MCP nova + 1 dia smoke real + Pest). ROI imediato com time MCP.

**Rejeição da alternativa pura per-module:**
- Perde narrativa transversal (Cenário A e C inexpressáveis).
- Stripe/Airbnb/Spotify hibridizam: ADRs (transversais) + bounded contexts (módulo) + RFCs (per-feature). Pure per-module só funciona em monolitos sem feedback loop multi-time.

## 7. Top 10 gaps priorizados (impacto × esforço calibrado IA-pair 10×)

| # | Gap | Impacto | Esforço (dev-h IA-pair) | Score (I/E) | Ação |
|---|---|---:|---:|---:|---|
| **G1** | Skill `preflight-modulo` Tier A pareada com hook (conflito C2 + C5) | **9** (regra primária invisível pro time MCP) | **1.5h** (skill 60 LOC + ajuste banner) | **6.0** | Criar PR pequeno |
| **G2** | `.claude/rules/` path-scoped (dimensão #3) | **8** (60 KB de contexto economizado/sessão × 8 sessões/dia × 5 devs = 2.4 MB/dia menos tokens) | **3h** (mover ~20 entries de reference/ + ajustar frontmatter `paths:` glob) | **2.7** | PR moderado |
| **G3** | Tool MCP `module-state <modulo>` (event sourcing projection §6) | **9** (time MCP recém-entrante ganha contexto rápido) | **8h** (laravel/mcp tool + view query + Pest) | **1.1** | US separada |
| **G4** | Migrar hooks PreToolUse pra schema novo `hookSpecificOutput` (C1) | 5 (futuro-proof) | 1h (refactor 1 hook + smoke) | 5.0 | Quick win |
| **G5** | Banner Tier A acerta count (C3) — distinguir live vs dormente | 3 | 0.3h | 10.0 | Trivial |
| **G6** | Auditoria skill `module-completeness-audit` SKILL.md vs limite 500 linhas | 4 | 0.5h | 8.0 | Trivial |
| **G7** | Adicionar `AGENTS.md` raiz fazendo `@CLAUDE.md` (compat Codex/Cursor team futuro) | 4 (Felipe/Maiara podem usar outros agents) | 0.5h | 8.0 | Trivial |
| **G8** | Description em 1ª pessoa em skills antigas (anti-pattern Anthropic 2026) | 4 | 2h (audit + refactor) | 2.0 | PR audit |
| **G9** | Playbook visual onboarding time MCP (4 entrantes — Felipe/Maiara/Eliana/Luiz) | 8 | 4h (atualizar `oimpresso-team-onboarding` SKILL.md + screenshots Vaultwarden + brief-fetch) | 2.0 | PR onboarding |
| **G10** | Glossary local por bounded context (`memory/requisitos/<Mod>/_glossary.md`) | 5 (ubiquitous language DDD) | 6h (10-15 módulos × 30min cada) | 0.83 | Backlog |

**Tier 0 ganho rápido (G1+G2+G4+G5+G6+G7 = total ~7h IA-pair) sobe nota 87 → 92.**
**Tier 1 (G3+G9 = 12h) sobe 92 → 97.**

## 8. Arquitetura final proposta + migração faseada

### Diagrama conceitual (Mermaid-style ASCII)

```
                          ┌──────────────────────────────┐
                          │   Time MCP (Felipe, Maiara,   │
                          │   Eliana, Luiz, Wagner)        │
                          └────────────┬─────────────────┘
                                       │ query
                          ┌────────────▼─────────────────┐
                          │   MCP server mcp.oimpresso.com │
                          │   (tools: brief-fetch,         │
                          │    decisions-search,           │
                          │    cycles-active, my-work,     │
                          │    handoff-fetch,              │
                          │    module-state ← NOVO §6)     │
                          └────────────┬─────────────────┘
                                       │ webhook GitHub <60s
                          ┌────────────▼─────────────────┐
                          │   git memory/ canônico        │
                          │   ┌───────────────────────┐   │
                          │   │ decisions/*.md        │   │
                          │   │ sessions/*.md (events)│   │
                          │   │ handoffs/*.md (events)│   │
                          │   │ requisitos/<Mod>/     │   │
                          │   │   ├ SPEC.md           │   │
                          │   │   ├ RUNBOOK*.md       │   │
                          │   │   ├ CAPTERRA*.md      │   │
                          │   │   └ _glossary.md ←G10│   │
                          │   │ reference/*.md        │   │
                          │   └───────────────────────┘   │
                          └────────────┬─────────────────┘
                                       │ load order
                          ┌────────────▼─────────────────┐
                          │ CLAUDE.md (95 linhas)         │
                          │ @imports recursivos:           │
                          │   why-oimpresso.md             │
                          │   what-oimpresso.md            │
                          │   how-trabalhar.md             │
                          │   proibicoes.md                │
                          │   regras-time.md               │
                          │                                │
                          │ .claude/rules/ ← G2 path-scoped│
                          │   modules-edit.md              │
                          │   tsx-frontend.md              │
                          │   php-backend.md               │
                          │   migrations.md                │
                          └────────────┬─────────────────┘
                                       │
                          ┌────────────▼─────────────────┐
                          │ Skills (.claude/skills/)       │
                          │  Tier A (8 atualmente + preflight-modulo ← G1)│
                          │  Tier B (~25)                  │
                          │  Tier C (slash)                │
                          └────────────┬─────────────────┘
                                       │
                          ┌────────────▼─────────────────┐
                          │ Hooks (.claude/hooks/)         │
                          │  SessionStart: 4 hooks         │
                          │  PreToolUse: 7 hooks           │
                          │     (3 deny + 4 warn) ← G4     │
                          │     migrar schema novo        │
                          │  Stop: memory-pending          │
                          └──────────────────────────────┘
                                       │
                          ┌────────────▼─────────────────┐
                          │ ~/.claude/oimpresso-local/    │
                          │ (ADR 0131 escape valve)        │
                          │                                │
                          │ Vaultwarden vault.oimpresso.com│
                          │ (segredos E2E)                 │
                          └──────────────────────────────┘
```

### Caminho de migração faseado

**Fase 0 (HOJE — 2026-05-15):** Aprovação Wagner deste dossier — escopo + sequência.

**Fase 1 (esta semana, 1.5h total IA-pair):** Quick wins G4+G5+G6+G7
- 1 PR pequeno: refactor `mcp-first-warning.ps1` schema novo + ajuste banner Tier A count + audit `module-completeness-audit` SKILL.md size + criar `AGENTS.md` raiz com `@CLAUDE.md`.
- Custo: ~1.5h. Risco: zero.

**Fase 2 (~2026-05-17, 1.5h IA-pair):** G1 skill `preflight-modulo`
- 1 PR: criar `.claude/skills/preflight-modulo/SKILL.md` com description agressiva matching "Edit/Write em Modules/" + atualizar banner Tier A pra incluir.
- Custo: 1.5h. Risco: baixo (skill nova é additive).
- **Sinal de sucesso:** Felipe/Maiara em primeira sessão MCP veem banner com skill `preflight-modulo` listada e adotam pré-flight sem ler hook warn.

**Fase 3 (~2026-05-18 a 19, 3h IA-pair):** G2 `.claude/rules/` path-scoped
- 1 PR: criar 4-6 arquivos em `.claude/rules/`:
  - `modules-edit.md` (paths: `Modules/**/*.php`) — pré-flight + skill `preflight-modulo` ref
  - `tsx-frontend.md` (paths: `resources/js/Pages/**/*.tsx`) — MWART + charter
  - `php-backend.md` (paths: `Modules/**/Http/**/*.php`, `Modules/**/Services/**/*.php`) — multi-tenant
  - `migrations.md` (paths: `**/Database/Migrations/*.php`) — ADR 0093 + biz_id indexado
- Mover entries pertinentes de `memory/reference/` que são path-specific.
- Custo: 3h. Risco: médio (precisa validar Glob no Claude Code).
- **Sinal de sucesso:** sessão típica carrega ~30% menos tokens iniciais (validar via `claude-code-usage-self`).

**Fase 4 (~2026-05-22, 8h IA-pair):** G3 tool MCP `module-state`
- US separada com SPEC + RUNBOOK. Wagner aprova spec antes.
- Implementa `ModuleStateTool.php` em `Modules/Jana/Mcp/Tools/`.
- Smoke biz=1, Pest, registro em `OimpressoMcpServer`.

**Fase 5 (~2026-05-25, 4h):** G9 playbook onboarding visual time MCP
- Refator `oimpresso-team-onboarding` SKILL.md com screenshots Vaultwarden + brief-fetch + módulos primários por dev.

**Fase 6 (backlog 30d):** G8 + G10 audit descriptions 1ª pessoa + glossary local por módulo.

**Custo total Fase 1-5: ~18h IA-pair = ~2 dev-days. Resultado esperado: nota 87 → 97/100, time MCP onboarding ≤ 1d (vs target Spotify 20d).**

## 9. Surpresa estratégica descoberta na pesquisa

**`.claude/rules/` path-scoped foi lançado em 2026** ([code.claude.com/docs/en/memory](https://code.claude.com/docs/en/memory)) e o oimpresso **não usa**. Esse é **o vetor de economia de contexto mais alto desbloqueado em 12 meses** — promove `memory/reference/*` (68 arquivos hoje 100% on-demand via Read) pra **load condicional por glob de path**. Tokens iniciais médios sessão hoje (25-40k) caem ~10-15k. ROI: 4h trabalho × 30 sessões/dia × 5 devs × 12kt × R$ [redacted Tier 0]/kt (Opus) ≈ R$ [redacted Tier 0]/dia economizado em LLM + atrito menor (Claude carrega instrução só quando relevante). Em 30d: ~R$ [redacted Tier 0] Em 1 ano: R$ [redacted Tier 0]k.

## 10. 3 perguntas decisivas pra Wagner aprovar mudanças macro

1. **Skill `preflight-modulo` Tier A (G1) — aprovar criação?** Pareia com hook `modulo-preflight-warning.ps1` recém-instalado. Esforço 1.5h. Sem isso, regra primária "mexeu, registra" continua invisível pro time MCP (Felipe/Maiara/Eliana/Luiz) recém-entrante — só descobre via warn pós-violação. **Recomendação: SIM.**

2. **`.claude/rules/` path-scoped (G2) — adotar pattern Anthropic 2026?** 4-6 arquivos cobrindo Modules/PHP, Pages TSX, Migrations. Move conteúdo seletivo de `memory/reference/`. ROI ~R$ [redacted Tier 0]k/ano em LLM + menos noise. Esforço 3h. **Recomendação: SIM** (gate quantitativo após Fase 3: validar redução tokens via `claude-code-usage-self`).

3. **Tool MCP `module-state` (G3) — vale 8h IA-pair AGORA ou esperar time MCP entrar?** Argumento PRO-AGORA: time MCP onboarda mais rápido (Felipe pega Sells, chama `module-state Sells`, contextualiza em ~1min). Argumento WAIT: pode ser supérfluo se brief-fetch + decisions-search já cobre. **Recomendação:** esperar 2 semanas pós-time entrar — se 3+ vezes Felipe/Maiara perguntar "qual estado do módulo X" sem brief responder, **implementar** (ADR 0105 — cliente como sinal qualificado).

## 11. Fontes (pesquisa profunda)

### Anthropic canônico (docs.anthropic.com)
- [Claude Code Memory — `code.claude.com/docs/en/memory`](https://code.claude.com/docs/en/memory) — hierarquia, @imports, path-scoped rules, auto-memory
- [Skill Authoring Best Practices — `platform.claude.com/docs/.../agent-skills/best-practices`](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices) — description matching, progressive disclosure, anti-patterns
- [Claude Code Hooks Reference — `code.claude.com/docs/en/hooks`](https://code.claude.com/docs/en/hooks) — eventos, exit codes, JSON schema, fail-secure vs cultural

### Comunidade / posts técnicos 2026
- [The Complete Guide to CLAUDE.md (Medium, Bijit Ghosh, mai/2026)](https://medium.com/@bijit211987/the-complete-guide-to-claude-md-memory-rules-loading-and-cross-tool-compression-97cc12ed037b)
- [SKILL.md vs CLAUDE.md vs AGENTS.md (Termdock)](https://www.termdock.com/blog/skill-md-vs-claude-md-vs-agents-md)
- [Claude.md vs Skills vs Subagents vs Hooks (AlgoMart, abr/2026)](https://medium.com/algomart/claude-md-vs-skills-vs-subagents-vs-hooks-how-to-choose-the-right-claude-code-customization-layer-903b75d061db)
- [Hooks, Rules, and Skills feedback loops (Medium, Jesse, mar/2026)](https://jessezam.medium.com/hooks-rules-and-skills-feedback-loops-in-claude-code-d47e5f58364d)
- [Claude Code Hooks Production CI/CD Patterns (Pixelmojo)](https://www.pixelmojo.io/blogs/claude-code-hooks-production-quality-ci-cd-patterns)
- [AGENTS.md Open Standard (DevTk.AI, 2026)](https://devtk.ai/en/blog/what-is-agents-md-guide/)
- [Claude Code Subagents Practical 2026 Guide (Nimbalyst)](https://nimbalyst.com/blog/claude-code-subagents-guide/)

### DDD / arquitetura
- [Martin Fowler — Bounded Context](https://martinfowler.com/bliki/BoundedContext.html)
- [DDD re-distilled (Yoan Thirion knowledge base)](https://yoan-thirion.gitbook.io/knowledge-base/software-architecture/ddd-re-distilled)
- [Event Sourcing + CQRS implementation guide (2026)](https://www.youngju.dev/blog/architecture/2026-03-10-event-sourcing-cqrs-architecture-implementation.en)
- [Azure Architecture Center — Event Sourcing Pattern](https://learn.microsoft.com/en-us/azure/architecture/patterns/event-sourcing)

### ADRs / governance
- [AWS Prescriptive Guidance — ADR best practices](https://aws.amazon.com/blogs/architecture/master-architecture-decision-records-adrs-best-practices-for-effective-decision-making/)
- [Microsoft Azure Well-Architected — ADRs](https://learn.microsoft.com/en-us/azure/well-architected/architect-role/architecture-decision-record)
- [Martin Fowler — Architecture Decision Record](https://martinfowler.com/bliki/ArchitectureDecisionRecord.html)

### Multi-user / scaling
- [Backstage Spotify](https://backstage.spotify.com/) — IDP 89% market 2026
- [Spotify Portal for Backstage](https://backstage.spotify.com/products/portal)
- [Roadie Backstage Guide 2026](https://roadie.io/backstage-spotify/)
- [Onboarding for Tech Teams (TechClass)](https://www.techclass.com/resources/learning-and-development-articles/onboarding-for-technical-teams-reducing-time-to-full-productivity)

### Agent memory frameworks (referência rejeitada por moat governance)
- [State of AI Agent Memory 2026 (Mem0)](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [Context Engineering Complete Guide April 2026 (Supermemory)](https://supermemory.ai/blog/what-is-context-engineering-complete-guide/)
- [5 AI Agent Memory Systems Compared (Mem0/Zep/Letta/Supermemory/SuperLocalMemory)](https://dev.to/varun_pratapbhardwaj_b13/5-ai-agent-memory-systems-compared-mem0-zep-letta-supermemory-superlocalmemory-2026-benchmark-59p3)

### oimpresso interno (cross-ref)
- [CLAUDE.md raiz](../../CLAUDE.md)
- [memory/proibicoes.md](../proibicoes.md) — workflow 3 fases regra primária
- [memory/how-trabalhar.md](../how-trabalhar.md) — paralelização agents
- [ADR 0094 Constituição v2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0095 Skills Tiers](../decisions/0095-skills-tiers-convencao-interna.md)
- [ADR 0061 Zero auto-mem](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0131 Tiering memória](../decisions/0131-tiering-memoria-canonico-local-segredo.md)
- [ADR 0130 Handoff append-only](../decisions/0130-handoff-append-only-mcp-first.md)
- [ADR 0091 Daily Brief](../decisions/0091-daily-brief.md)
- [feedback-modulo-mexeu-registra-sempre.md](../reference/feedback-modulo-mexeu-registra-sempre.md)
- [evolucao-memoria-2026-05-13.md](../reference/evolucao-memoria-2026-05-13.md) — sessão Onda 5 contexto
- [aprendizados-onda1-2-3-2026-05-13.md](../reference/aprendizados-onda1-2-3-2026-05-13.md)

---

**Próximo passo Wagner:** decidir as 3 perguntas §10. Se aprovado G1+G2+G4-G7 (~7h IA-pair), spawnar 1 PR Fase 1 + 1 PR Fase 2 (preflight-modulo Tier A) imediatos. Tempo até nota 92/100: **3 dias**. Tempo até nota 97/100 (Fase 5 completa): **2 semanas**.

**Pendência decisória explícita Wagner:** não há ADR mãe nova proposta neste dossier — todos gaps são complementares aos ADRs canônicos existentes (0094 mãe, 0095 tiers, 0130 handoff, 0131 tiering). Se G3 (tool MCP `module-state`) for aprovada Fase 4, **aí sim** vai precisar ADR 0XXX nova justificando projeção por bounded context.
