---
id: requisitos-task-registry-audit-team-os-2026-05-29
title: "Auditoria Team OS — TaskRegistry MCP vs Jira Team '26/Rovo + Claude Agent Teams"
type: auditoria
status: draft
authority: tecnico
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-29
decided_by: [audit-team-os-expert]
module: TaskRegistry
tier: TECHNICAL_AUDIT
trust_level: advise
pergunta_origem: "isso (rotina/hook que criei) vai ficar na minha rede MCP?"
related_adrs: [0053, 0055, 0070, 0076, 0093, 0094, 0105, 0106]
parent_artifacts:
  - memory/decisions/0070-jira-style-task-management-current-md-removed.md
  - memory/requisitos/TaskRegistry/SPEC.md
  - memory/governance/AUTOMATIONS.md
authors: [audit-team-os-expert]
score_os_solo: 82/100 (lente "Wagner + Claude solo" — MCP-first é feature pra agente IA)
score_team_os: 70/100 (lente "5 humanos + clientes B2B" — esta é a lente que importa)
nota_ponderada_12_funcoes: 7.0/10 (P0=4 · P1=2 · P2=1)
correcao_v2: "2026-05-29 — UI Fase 7 JÁ existe em Modules/ProjectMgmt (Board/Backlog/MyWork/Roadmap/Burndown/Activity, 2822 linhas). Função 9 corrigida 2.5→7; Team OS 64→70. Faltam só Triage + Inbox + push."
decisao_wagner_firme: "NÃO ligar 2º cérebro / Brain B / autonomia ADS agora (custo recorrente indesejado)"
artefatos_alvo:
  - memory/decisions/proposals/drafts/automation-registry-mcp.md
  - memory/governance/AUTOMATIONS.md
  - memory/requisitos/TaskRegistry/SPEC-UI-FASE7.md
  - .claude/hooks/loop-fechar-check.ps1
  - .claude/loop-fechar-o-loop.json
---

# AUDIT Team OS — 2026-05-29

> ⚠️ **CORREÇÃO v2 (2026-05-29, pós-verificação paralela):** a v1 deste audit afirmou que a **UI Fase 7 "nunca foi construída" (função 9 = 2.5)**. **Errado.** A verificação durante a geração dos deliverables encontrou `Modules/ProjectMgmt` com **UI real e quality-gated**: `Board/Index.tsx` (527 linhas) + `Board/DetailSheet.tsx` (801) + `Backlog` (390) + `MyWork` (461) + `Activity` (254) + `Burndown` (243) + `Roadmap` (146) = **2.822 linhas**, todas com `.charter.md` + `.review.md`. **Kanban, Backlog, MyWork, Roadmap, Burndown e Activity JÁ EXISTEM.** Faltam apenas **Triage** e **Inbox** (dedicados) + **push de notificação**. Correção aplicada: função 9 = 2.5 → **7**; Team OS = 64 → **70**; Onda 2 reescopada de "construir Fase 7" para "completar 2 telas + polish". O erro v1 veio de um `find -iname "*board*"` que não casou `Board/Index.tsx` (nome do arquivo é `Index.tsx`). Lição: verificar com `ls` da árvore, não só por nome de arquivo.

> **Pedido Wagner via parent agent:** auditar o "Team OS" do oimpresso — o sistema MCP de tasks + governança ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) / [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) / [ADR 0055](../../decisions/0055-self-host-equivalent-anthropic-team.md)) — comparando com Jira (Atlassian Team '26 + Rovo) e Claude Code Agent Teams (Opus 4.6, fev/2026).
>
> **Pergunta-origem (literal):** *"isso vai ficar na minha rede MCP?"* — sobre uma rotina/hook que o agente havia criado na sessão.
>
> **Resposta executiva curta:** O backend MCP é o **source-of-truth certo** (Jira-style, audit append-only, memory-linked) e está, em governança, **acima do Jira**. Mas faltam três coisas pra ser um *Team* OS de verdade: **(a) interface humana** (Jira é UI-first; o nosso é tool-only), **(b) agente-como-assignee que executa** e **(c) grafo de relações automático**. Duas lentes: **~82/100 como OS Wagner+Claude solo**, **~70/100 como Team OS** (5 humanos + clientes B2B, pós-correção v2). A distância 82→70 É o roadmap.

---

## 1. A pergunta-origem: "isso vai ficar na minha rede MCP?"

**Resposta verificada: NÃO, do jeito que está.** O código revela **3 tiers de persistência** com indexação MCP distinta:

| Tier | O que é | Indexado no MCP? | Indexador |
|---|---|:---:|---|
| **Canônico** | `memory/**` + alguns `.md` raiz | **SIM** | `IndexarMemoryGitParaDb` (varre `memory/decisions`, `sessions`, `requisitos/*/SPEC.md`, `adr`, audits, comparativos) |
| **Skills** | `.claude/skills/*/SKILL.md` | **SIM** | `ImportarSkillsDoGitService` ([ADR 0076](../../decisions/0076-skills-no-git-importadas-mcp.md)) → `mcp_skills` |
| **Local tooling** | `.claude/hooks/*.ps1`, `settings.json`, `.claude/*.json` | **NÃO** | nenhum — invisível ao MCP e ao time |

A rotina/hook criada na sessão **caiu no tier "Local tooling"** → **não vira documento MCP**. Mesmo versionada via git, cada dev tem seu próprio `settings.json`; a Jana/MCP fica **cega** pra ela. Não existe entidade governada que registre "esta automação existe, roda em tal trigger, pertence a fulano, foi auditada".

Isso expõe diretamente o **gap #11 — não existe Registry de Automações** (ver §3 e §4). A resposta executável à pergunta-origem é a **Onda 1.1** (§5): criar `mcp_automations` e cadastrar a rotina "Fechar o Loop" como a 1ª linha — **aí sim** ela passa a estar na rede MCP, auditável.

---

## 2. Como o estado-da-arte funciona — e como o nosso DEVERIA ser

### 2.1 Jira Team '26 + Rovo (Atlassian)

- **Hierarquia:** Initiative → Epic → Story/Task → Subtask + **Plans** (até 5 níveis acima do epic, via Advanced Roadmaps).
- **Salto 2026 — Teamwork Graph:** mapa vivo com ~150 bilhões de conexões ligando *work ↔ docs ↔ goals ↔ decisões ↔ pessoas*. Não é hierarquia manual; é grafo **automático** alimentado pela telemetria dos próprios produtos Atlassian.
- **Rovo Agents como ASSIGNEES:** agentes deixam de ser "chatbot lateral" e viram **assignee de work items**, com **audit log** e **execução multi-step autônoma** (modo Max). O agente *pega* a task e *executa*, não só sugere.

### 2.2 Claude Code Agent Teams (Opus 4.6, fev/2026)

- **Team lead** coordena N **teammates**, cada um em seu próprio contexto, que **conversam entre si diretamente**.
- **Shared memory bank único** compartilhado pelo time de agentes.
- **Custo:** ~3-4× tokens de uma sessão single-agent (cada teammate tem contexto próprio).

### 2.3 Como o nosso DEVERIA ser pra igualar

O **backend MCP já é o source-of-truth certo** — Jira-style, audit append-only, memory-linked. O gap não é o modelo de dados; é a *superfície*:

1. **Interface humana** — Jira é **UI-first**; o nosso é **tool-only**. Bloqueia time não-técnico e board de cliente B2B.
2. **Agente-como-assignee que executa** — hoje só *sugere* (`tasks-suggest-*` designed; ADS/Brain B dormente). Rovo Max *executa*.
3. **Grafo de relações automático** — temos os dados crus (`mcp_git_links` + `mcp_task_memory_links` + `mcp_task_events`), mas a ligação é **manual**, não um Teamwork Graph que se monta sozinho.

---

## 3. NOTA — as 12 funções de um Team OS

Avaliação ponderada por peso de cada função (P0=4, P1=2, P2=1). Estado atual cruzado com código real ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) hierarquia + tools MCP).

| # | Função | Peso | Estado atual | Como deveria ser | Nota |
|---:|---|:---:|---|---|---:|
| 1 | Modelagem do trabalho | P0 | Project→Epic→Cycle→Task→Subtask + Component | = Linear/Jira; falta **Initiative** (Tier 3) | **9** |
| 2 | Workflow & estados | P1 | ENUM custom por projeto + transitions JSON (`mcp_workflows`) | editor **visual** de transições | **8** |
| 3 | Identificadores & rastreio git | P1 | `COPI-123` + git bidir (fixes→done) | best-in-class, igual/melhor que Jira | **9** |
| 4 | Views & queries | P2 | `mcp_views` saved filters | **designed mas sem UI** pra renderizar | **7** |
| 5 | Notificações & inbox | P1 | `my-inbox` **pull-only** | falta **push** (Slack/email/WhatsApp) | **6** |
| 6 | Integração com conhecimento | P0 | `mcp_task_memory_links` (task↔ADR/SPEC) + RAG | **diferencial**, mas manual vs Teamwork Graph automático | **8.5** |
| 7 | Execução AI-native (agente assignee) | P0 | `tasks-suggest-*` designed; ADS/Brain B **DORMENTE** | Rovo Max executa multi-step; nós só sugerimos | **4.5** |
| 8 | Coordenação & presença de time | P1 | request-response via tools; **sem presença** | Agent Teams lead+teammates; Jira presença live | **4** |
| 9 | UI/acessibilidade (board, não-técnico, cliente) | P0 | **Board/Backlog/MyWork/Roadmap/Burndown/Activity JÁ existem** (`Modules/ProjectMgmt`, 2822 linhas, charters+reviews); faltam **Triage + Inbox** dedicados | Jira/Linear UI-first; completar 2 telas + validar acessibilidade não-técnico/B2B | **7** |
| 10 | Governança & audit | P0 | `mcp_task_events` append-only + Spatie RBAC + ADS policy | best-in-class, **ACIMA de Jira** (audit irrevogável) | **9** |
| 11 | Registry de automações (crons/hooks/rotinas) | P1 | **INEXISTENTE** — invisível ao MCP | entidade **governada + auditável** | **2** |
| 12 | Multi-tenant / board B2B | P2 | `business_id` pronto, **não exercitado** | board de cliente; falta ativar | **7** |

### 3.1 Cálculo da nota ponderada

```
P0 (funções 1, 6, 7, 9, 10): 9 + 8.5 + 4.5 + 7 + 9 = 38.0  ×4 = 152   (função 9 corrigida 2.5→7 na v2)
P1 (funções 2, 3, 5, 8, 11): 8 + 9 + 6 + 4 + 2     = 29.0  ×2 =  58
P2 (funções 4, 12):          7 + 7                 = 14.0  ×1 =  14
                                            ────────────────────────
                                            soma ponderada       = 224
                                            soma dos pesos: 5×4 + 5×2 + 2×1 = 32
                                            nota = 224 / 32 = 7.0 / 10
```

### 3.2 DUAS LENTES (ponto mais importante deste audit)

> A nota muda **radicalmente** conforme quem é o "time". Deixar isso vago seria o erro de leitura mais caro.

| Lente | Score | Por quê |
|---|:---:|---|
| **OS Wagner + Claude solo** | **~82/100** | MCP-first **é feature**, não bug — o "usuário" é um agente IA que lê/escreve tools nativamente. Audit, memory-links e git-sync já entregam um OS de altíssima qualidade pra um operador + copiloto. |
| **Team OS** (5 humanos + clientes B2B) | **~70/100** | É a lente que importa pra "Team Jira / Team Claude". A UI **existe** (função 9 = 7, corrigida na v2) mas falta **Triage + Inbox**; faltam ainda **push** (função 5 = 6), **registry de automação** (função 11 = 2) e **agente-executor** (função 7 = 4.5) pro time não-técnico + cliente operarem com folga. |

**A distância 82 → 70 É o roadmap.** Tudo em §4 e §5 existe pra fechar esses 12 pontos sem rasgar o que já é best-in-class (audit + memory-links + git). O maior dreno agora **não é mais UI** — é o **Registry de automações (2)**, **coordenação/presença (4)** e **execução AI-native (4.5)**.

---

## 4. As 10 funções faltantes (gap list)

| # | Função faltante | Resolve | Dado já existe? |
|---:|---|---|---|
| 1 | **Registry de Automações** (`mcp_automations`) | **a pergunta-origem** — crons/hooks/rotinas viram entidade governada | parcial (rotinas existem em `.claude/`, mas fora do MCP) |
| 2 | **Completar UI** (Triage + Inbox dedicados) — Board/Backlog/Roadmap/MyWork/Burndown/Activity **já existem** em `Modules/ProjectMgmt` | função 9 (já em 7) | backend 100% pronto; UI ~80% pronta |
| 3 | **Push de notificações** (Slack/email/WhatsApp) | função 5 (`my-inbox` deixa de ser pull-only) | `mcp_inbox_notifications` já existe |
| 4 | **Agente-como-assignee que executa** | função 7 (efeito Rovo Max) | `tasks-suggest-*` designed |
| 5 | **Teamwork Graph leve** | função 6 — grafo automático task↔commit↔doc↔pessoa | **sim** — `git_links` + `memory_links` + `events` |
| 6 | **Presença / colaboração real-time** | função 8 | **sim — Centrifugo já no stack!** ([ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) |
| 7 | **Initiatives / Roadmap (Tier 3)** | função 1 — 5º nível acima do epic | adiado por design no ADR 0070 |
| 8 | **SLA / time tracking** | função 12 — entra com 1º cliente B2B | adiado por design no ADR 0070 |
| 9 | **Ingestão de sinais cross-tool** | WhatsApp/suporte → tasks automaticamente | — |
| 10 | **Orquestração via Agent Teams** | função 7/8 — lead + teammates | — |

---

## 5. Plano em 3 ondas — COST-AWARE

> **Decisão Wagner FIRME:** **NÃO ligar o 2º cérebro / Brain B / autonomia ADS agora** — custo recorrente indesejado. O plano abaixo respeita isso à risca: tudo que custa token recorrente fica **adiado conscientemente**, e o salto de nota vem de governança + UI + AI-native *barato*.

### Onda 1 — custo ~zero, governança

| # | Item | Esforço | Efeito |
|---:|---|:---:|---|
| 1.1 | **Registry de Automações** — `mcp_automations` + tool `automations-list`. A rotina **"Fechar o Loop"** vira a **1ª linha** → **aí SIM entra na rede MCP**, auditável. | ~4h | resolve a pergunta-origem; função 11: 2→8 |
| 1.2 | **Push do `my-inbox`** → WhatsApp/email | ~3h | função 5: 6→8 |

> **Sacada central:** a Onda 1.1 é a **resposta executável** à pergunta-origem. Hoje a rotina **NÃO está na rede MCP**; com o registry, **passa a estar** — sem ligar nenhum cérebro.

### Onda 2 — completar a UI (não construir do zero)

| # | Item | Esforço | Efeito |
|---:|---|:---:|---|
| 2.1 | **Completar UI** — telas **Triage** + **Inbox** dedicadas + polish de acessibilidade/não-técnico no Board/Backlog existentes (Inertia, **AppShellV2 + PT-01**) | ~5-7h | função 9: 7→9 |

**Correção v2:** a Fase 7 do [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) **já foi ~80% construída** em `Modules/ProjectMgmt` (Board/Backlog/MyWork/Roadmap/Burndown/Activity, 2822 linhas, charters+reviews). A Onda 2 deixa de ser "construir o board" e vira "completar Triage + Inbox + validar usabilidade não-técnica". SPEC-alvo: [`SPEC-UI-FASE7.md`](SPEC-UI-FASE7.md).

### Onda 3 — AI-native barato (efeito Rovo SEM Brain B)

| # | Item | Esforço | Efeito |
|---:|---|:---:|---|
| 3.1 | **Jana-as-assignee READ-ONLY** — summarize / draft / link-memory / suggest-priority, usando **Brain A `gpt-4o-mini` barato** | ~6h | função 7: 4.5→7 sem custo recorrente alto |
| 3.2 | **Teamwork Graph leve** — **view materializada, zero LLM** | ~4h | função 6: 8.5→9 |

### Adiar conscientemente (decisão Wagner)

- **Execução autônoma com escrita** (Brain B / Rovo Max real) — custo recorrente.
- **Agent Teams orchestration** — ~3-4× tokens.
- **Initiatives / SLA** — até existir cliente B2B (sinal qualificado — [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

### Sequência visual

```
ONDA 1 (custo ~zero — governança)        ONDA 2 (completar UI)            ONDA 3 (AI-native barato)
  ├─ 1.1 mcp_automations (~4h) ★          └─ 2.1 Triage+Inbox+polish (~5-7h) ├─ 3.1 Jana read-only (~6h)
  │      [resolve a pergunta-origem]             [função 9: 7→9]            └─ 3.2 Graph view materializada (~4h)
  └─ 1.2 push my-inbox (~3h)              [Board/Backlog/Roadmap já existem]
                                          ADIAR: Brain B/Rovo Max real · Agent Teams (3-4×) · Initiatives/SLA
```

---

## 6. Artefatos relacionados (cross-links)

| Artefato | Caminho | Papel |
|---|---|---|
| **ADR proposta** (nº 0234, status *proposto*) | [`memory/decisions/proposals/drafts/automation-registry-mcp.md`](../../decisions/proposals/drafts/automation-registry-mcp.md) | Automation Registry / `mcp_automations` — a decisão que destrava a Onda 1.1 |
| **Inventário canônico** (já indexado MCP) | [`memory/governance/AUTOMATIONS.md`](../../governance/AUTOMATIONS.md) | inventário vivo de automações do oimpresso |
| **SPEC UI** (Onda 2) | [`memory/requisitos/TaskRegistry/SPEC-UI-FASE7.md`](SPEC-UI-FASE7.md) | Kanban + Backlog + Triage + Inbox |
| **Rotina seed** | [`.claude/hooks/loop-fechar-check.ps1`](../../../.claude/hooks/loop-fechar-check.ps1) + [`.claude/loop-fechar-o-loop.json`](../../../.claude/loop-fechar-o-loop.json) | a "Fechar o Loop" — vira a 1ª linha de `mcp_automations` |

---

## 7. Fontes

**Jira / Atlassian / Rovo:**

- [Jira hierarchy — Epics, Stories, Themes (Atlassian)](https://www.atlassian.com/agile/project-management/epics-stories-themes)
- [Jira Plans / Initiatives — configuring hierarchy levels (Atlassian Confluence)](https://confluence.atlassian.com/advancedroadmapsserver0329/configuring-initiatives-and-other-hierarchy-levels-1021218664.html)
- [Rovo agents (Atlassian)](https://www.atlassian.com/software/rovo)
- [Rovo at Team '26 (Atlassian Blog)](https://www.atlassian.com/blog/company-news/rovo-team-26)
- [Atlassian opens Teamwork Graph, pushes Rovo agentic execution at Team '26 (SiliconANGLE)](https://siliconangle.com/2026/05/06/atlassian-opens-teamwork-graph-pushes-rovo-agentic-execution-team-26/)

**Claude Code Agent Teams:**

- [Claude Agent Teams (docs)](https://code.claude.com/docs/en/agent-teams)
- [Claude Code Agent Teams & Subagents playbook 2026 (Developers Digest)](https://www.developersdigest.tech/blog/claude-code-agent-teams-subagents-2026)
- [Claude Code subagents shared memory (Hindsight / Vectorize)](https://hindsight.vectorize.io/blog/2026/05/06/claude-code-subagents-shared-memory)

---

**Última atualização:** 2026-05-29 (**v2** — correção UI Fase 7) — audit-team-os-expert · TaskRegistry MCP vs Jira Team '26/Rovo + Claude Agent Teams · veredito: ~82/100 solo, ~70/100 Team OS (nota ponderada 7.0/10) · decisão Wagner: NÃO ligar Brain B agora · Onda 1.1 (`mcp_automations`) resolve a pergunta-origem · UI Fase 7 já existe em Modules/ProjectMgmt (faltam Triage+Inbox).
