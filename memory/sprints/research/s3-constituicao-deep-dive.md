---
name: S3 Deep Dive — Constituição v2 + Skills Tier A + CLAUDE.md
description: Pesquisa estado-da-arte 2026 pra Sprint 3. CLAUDE.md best practices, Skills frontmatter oficial, agent constitutions enterprise. Achados que afetam nosso plano.
type: project
created: 2026-05-06
related_sprint: S3
sources_count: 3
---

# S3 — Constituição v2 + Skills Tier A + CLAUDE.md (deep-dive)

> **Objetivo da pesquisa:** validar nosso plano S3 contra o estado-da-arte público de
> 2026 sobre CLAUDE.md, Skills, e agent constitutions. Identificar gaps e oportunidades.

---

## Achado #1 — CLAUDE.md atual está ~4× maior que best-practice 2026

**Recomendação 2026** ([alexop.dev](https://alexop.dev/posts/stop-bloating-your-claude-md-progressive-disclosure-ai-coding-tools/), [HumanLayer](https://www.humanlayer.dev/blog/writing-a-good-claude-md), [TurboDocx](https://www.turbodocx.com/blog/how-to-write-claude-md-best-practices)): root `CLAUDE.md` deve ter **<100 linhas** e seguir padrão WHY / WHAT / HOW / Progressive Disclosure.

**Nosso CLAUDE.md atual:** ~390 linhas. Nosso plano S3 dizia "≤350 linhas" — ainda longe demais.

### Padrão WHY/WHAT/HOW

| Camada | O que vai aqui | Tamanho recomendado |
|---|---|---|
| **WHY** | Propósito do projeto, motivação | 1 parágrafo |
| **WHAT** | Stack, estrutura modular, módulos canônicos | 1 tabela + 1 lista |
| **HOW** | Como trabalhar, fluxo, tools MCP | 1 fluxo numerado |
| **Progressive Disclosure** | Pointers `@path/to/file.md` pra detalhes | lista de imports |

### Implicação no plano S3

🔴 **Reduzir CLAUDE.md de 350 → 100 linhas é mudança maior do que previsto.** Conteúdo atual precisa migrar pra:
- `memory/why-oimpresso.md` (visão de produto, ~30 linhas)
- `memory/what-oimpresso.md` (stack + estrutura, ~50 linhas)
- `memory/how-trabalhar.md` (fluxo MCP-first, ~80 linhas)
- `memory/proibicoes.md` (regras NÃO fazer, ~40 linhas)
- `memory/regras-time.md` (quem faz o quê, ~30 linhas)

CLAUDE.md vira um índice com `@imports`:

```markdown
# Oimpresso — primer pra agentes

## Por que existe
@memory/why-oimpresso.md

## Stack e estrutura
@memory/what-oimpresso.md

## Como trabalhar
@memory/how-trabalhar.md

## Proibições (Tier 0)
@memory/proibicoes.md

## Time e responsabilidades
@memory/regras-time.md
```

**Total:** ~30 linhas. Resto vira lazy-loaded.

---

## Achado #2 — Feature `@path/to/file.md` imports é canônica e não estamos usando

Claude Code suporta **recursive imports até 5 níveis** via `@path/to/file.md` ([Implementing CLAUDE.md and Agent Skills](https://www.groff.dev/blog/implementing-claude-md-agent-skills)). Hoje nosso CLAUDE.md repete conteúdo que já vive em `memory/decisions/`, `memory/04-conventions.md`, etc.

### Implicação

Adoção desse padrão pode resolver a tensão **"quero contexto rico vs. quero CLAUDE.md curto"** sem perda — Claude Code resolve os imports automaticamente quando relevante.

### Ação concreta no S3

Adicionar passo no checklist:
- [ ] Reescrever CLAUDE.md como índice com 8–12 imports `@`
- [ ] Mover blocos longos pra `memory/{why,what,how,proibicoes,regras-time}.md`
- [ ] Validar que Claude Code resolve imports corretamente em sessão real

---

## Achado #3 — Frontmatter de Skills oficial NÃO tem campo `tier`

Pesquisa ([Anthropic Skills repo](https://github.com/anthropics/skills/blob/main/skills/skill-creator/SKILL.md), [Best Practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices), [Complete Guide PDF](https://resources.anthropic.com/hubfs/The-Complete-Guide-to-Building-Skill-for-Claude.pdf)): campos canônicos do frontmatter são:

```yaml
---
name: skill-name             # obrigatório
description: "Use when..."   # obrigatório, é o trigger
allowed-tools: Read, Grep    # opcional, restringe acesso
disable-model-invocation: false  # opcional, desativa auto-trigger
---
```

**Não existe** `tier: A/B/C` no padrão Anthropic.

### Implicação

Nosso conceito Tier A/B/C é **convenção interna nossa**, não está no produto. Funcionalmente:
- **Tier A "always-on"** ≠ feature do Anthropic. O que existe é: skill carregada via `description` match agressivo + `disable-model-invocation: false`. Skill verdadeiramente always-on requer reforço VIA HOOK ou prompt no CLAUDE.md.
- Atualmente, nosso `brief-first` provavelmente NÃO é always-on no sentido técnico — ele só dispara se a description matchar a pergunta. Se Claude começa sessão com "olá", nada dispara.

### Ação concreta no S3

- [ ] Decidir: manter `tier: A/B/C` no frontmatter como **convenção interna documentada** OU mover pra outro mecanismo
- [ ] Para Tier A "verdadeiramente always-on", usar **hook `SessionStart`** em `.claude/settings.json` que força chamada `brief-fetch` (já temos um hook similar pra check-skills)
- [ ] Mover regras Tier A→C pra ADR canônica (não pro frontmatter)

**Mecanismo proposto:**

| Comportamento desejado | Mecanismo técnico real | Hoje? |
|---|---|---|
| brief-fetch SEMPRE primeira tool | Hook SessionStart no `.claude/settings.json` | ✅ existe (check-skills) |
| Skill dispara em contexto X | `description` agressiva no SKILL.md | ✅ funciona |
| Skill nunca dispara automaticamente | `disable-model-invocation: true` | ✅ canônico |

---

## Achado #4 — "Agent Constitution" virou pauta enterprise 2026

Termos como **"agentic constitution"** ([CIO.com](https://www.cio.com/article/4118138/why-your-2026-it-strategy-needs-an-agentic-constitution.html)), **"runtime constitutions"** ([Blake Crosley](https://blakecrosley.com/en/blog/agent-self-governance)), **"embedded constitutions enforce at runtime"** ([arXiv 2604.27691](https://arxiv.org/html/2604.27691)) são consenso emergente.

Estatística marcante: apenas **36% das enterprises têm governance centralizada** de agentes hoje, **12% usam plataforma centralizada** ([Google AI Governance](https://www.artificialintelligence-news.com/news/agentic-ai-governance-enterprise-readiness-google/)). Gartner: 40% das aplicações empresariais terão agentes task-specific até fim de 2026.

### Implicações pro Oimpresso

🟢 **Estamos na vanguarda.** Nosso plano de 7 camadas com brief diário + skills tieradas + ADS + cockpit já está mais maduro que 88% das empresas. Não precisamos pivotar — só executar.

🟡 **Naming alinha:** "Constituição v2" = "Agentic Constitution" do mercado. Bom posicionamento.

🔴 **Falta runtime enforcement.** Hoje nossa constituição é texto (`memory/decisions/`). 2026 best-practice = **runtime constitution embedded** — sistema bloqueia ações que violam. Isso é exatamente o que o S5 ADS Universal vai fazer.

### Princípios canônicos enterprise (frequentes)

1. **Transparency** — agentes devem explicar por que tomaram decisão (já no `mcp_audit_log`)
2. **Human oversight** — humano pode override ou revogar (já HITL `mcp_inbox` channel `hitl`)
3. **Accountability** — toda ação atribuível a um agent ID + commit (✅ via Conventional Commits + Refs SPRINT-N)
4. **Fairness/Privacy** — LGPD compliance, dados scopados (← business_id Tier 0!)
5. **Reliability** — fallback humano quando confiança baixa (PolicyEngine ALLOW/REQUIRE_BRAIN_B/HUMAN_REVIEW/BLOCK)
6. **Cost control** — budget caps com alerta (já no plano S5 §6.5)

Nossos 6 princípios duros (atuais) cobrem 4/6. Faltam:
- ⚠️ **Transparency** (parcial — temos audit log, falta UI de "por que essa decisão" no cockpit)
- ⚠️ **Reliability** (parcial — temos cap mas falta fallback automático em caso de falha Brain B)

### Ação concreta no S3

- [ ] Adicionar 2 princípios duros à Constituição: **Transparência** + **Confiabilidade com fallback**
- [ ] ADR mãe da Constituição cita expressamente "agentic constitution" + benchmark Gartner
- [ ] Documentar enforcement runtime (S5 ADS) como mecanismo da constituição

---

## Achado #5 — `description` é o ÚNICO trigger; "Use when..." é obrigatório

Regra dura ([Anthropic best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices), [TechBytes](https://techbytes.app/posts/anthropic-claude-skills-guide-breakdown-feb-2026/)): **a description é trigger, não summary**. Deve começar com "Use when..." e listar gatilhos explícitos.

### Auditoria das nossas 19 skills (preview rápido)

Já cumprem o padrão (descobri lendo SKILL.md de algumas):
- ✅ `multi-tenant-patterns` — "Use ao criar Eloquent Model, Controller, Service, Job ou Migration que toca dados de negócio"
- ✅ `brief-first` — "ATIVAR PRIMEIRO em toda sessão"
- ✅ `criar-modulo` — "Use ao criar novo módulo Laravel modular"

Talvez não cumprem (precisa auditoria S3):
- ⚠️ `oimpresso-stack` — começa com "Use ao iniciar trabalho no oimpresso" (✅ ok)
- ❓ outras 15 — auditoria pendente

### Ação concreta no S3

- [ ] Auditar 19 SKILL.md, classificar:
  - ✅ Description começa com "Use ao/quando/Antes de" → manter
  - ❌ Description é resumo descritivo → REESCREVER como trigger
- [ ] Tabela na auditoria S3 marca essa coluna

---

## Recomendações finais pro plano S3 (revisões)

### O que manter

- Estrutura geral do dossier (6 arquivos)
- ADR mãe + ADR skills tiers + auditoria + 5 skills Tier A
- Wagner dirige, Sonnet/Opus assistem

### O que mudar

| Item | Plano original S3 | Revisão pós deep-dive |
|---|---|---|
| Tamanho CLAUDE.md | ≤350 linhas | **≤100 linhas + imports `@`** |
| Estrutura CLAUDE.md | 8 seções monolíticas | **WHY/WHAT/HOW + 5 imports** |
| Tier A/B/C frontmatter | campo no SKILL.md | **convenção interna em ADR; mecanismo real = SessionStart hook + description aggressive** |
| Princípios duros | 6 (com multi-tenant) | **8 (adicionar Transparência + Confiabilidade)** |
| Auditoria 19 skills | só Tier + disparos | **adicionar coluna "description é trigger?"** |
| Estimativa | 3-4 dias | **5-7 dias** (refactor CLAUDE.md em 5 arquivos é maior que previsto) |

### O que adicionar

- [ ] Criar `memory/{why,what,how,proibicoes,regras-time}.md` no S3
- [ ] Hook `SessionStart` que força brief-fetch (se ainda não force)
- [ ] ADR canônica documentando convenção Tier A/B/C como interna (não Anthropic-padrão)

### O que remover

- ❌ Campo `tier:` no frontmatter SKILL.md (não é canônico Anthropic) — substituir por documentação em ADR + hook real

---

## Sources

- [Stop Bloating Your CLAUDE.md — alexop.dev](https://alexop.dev/posts/stop-bloating-your-claude-md-progressive-disclosure-ai-coding-tools/)
- [Writing a good CLAUDE.md — HumanLayer](https://www.humanlayer.dev/blog/writing-a-good-claude-md)
- [How to Write a CLAUDE.md File That Actually Works — TurboDocx](https://www.turbodocx.com/blog/how-to-write-claude-md-best-practices)
- [Implementing CLAUDE.md and Agent Skills — Matthew Groff](https://www.groff.dev/blog/implementing-claude-md-agent-skills)
- [Skill authoring best practices — Anthropic Docs](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices)
- [The Complete Guide to Building Skills for Claude — Anthropic PDF](https://resources.anthropic.com/hubfs/The-Complete-Guide-to-Building-Skill-for-Claude.pdf)
- [Why your 2026 IT strategy needs an agentic constitution — CIO.com](https://www.cio.com/article/4118138/why-your-2026-it-strategy-needs-an-agentic-constitution.html)
- [Self-Governing Agents: Runtime Constitutions — Blake Crosley](https://blakecrosley.com/en/blog/agent-self-governance)
- [When Agents Evolve, Institutions Follow — arXiv 2604.27691](https://arxiv.org/html/2604.27691)
- [Anthropic Skills Repository](https://github.com/anthropics/skills)
