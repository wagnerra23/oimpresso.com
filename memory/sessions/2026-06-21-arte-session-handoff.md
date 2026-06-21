---
date: "2026-06-21"
topic: "Auditoria estado-da-arte — Session Handoff do oimpresso (continuidade de contexto entre sessoes: cold-start, encerramento, WIP, paralelismo, custo de retomada, robustez sessao longa). Gap analysis vs best-of-class 2026. Nota 76%, decisao CONSOLIDAR."
type: auditoria-estado-da-arte
tema: session-handoff
escopo: continuidade de contexto entre sessões de agente (cold-start, encerramento, WIP, paralelismo, custo de retomada, robustez sessão longa)
raia: NÃO cobre knowledge-architecture (captura/recall/ADRs) nem governança SDD (gates/CI) — auditores irmãos
autor: audit-research-expert (Fase 1 do ciclo /audit-and-fix)
websearch_count: 7
---

# Estado-da-arte: SESSION-HANDOFF @ oimpresso — gap analysis 2026-06-21

## TL;DR

Maturidade global ponderada **76%**. Recomendação **CONSOLIDAR** (não EVOLUIR) — a arquitetura está alinhada com o SOTA 2026 (handoff-document + brief-fetch como memory-tool agregada + 3 camadas de ativação lazy). O oimpresso está **à frente do mercado** em encerramento confiável (R12 + skill `encerrar-sessao` + hook `force-r12`) e em paralelismo seguro (worktree + `whats-active`). Os 3 gaps mais críticos são **(P0)** ausência de defesa mecânica contra context-rot DENTRO da sessão longa (R12 só dispara no fim — nada compacta no meio); **(P1)** índice de retomada `08-handoff.md` virou um monólito de ~72k tokens que o `/continuar` carrega inteiro (anti-cold-start); **(P1)** WIP não-commitado não sobrevive a crash (zero checkpointing — só `git push WIP a cada 30min` como regra cultural, não mecânica). Saturação realista: ~90% (acima disso é over-engineering pra um ERP solo-founder).

---

## Concorrentes / referências de estado-da-arte (3 categorias)

### A. Frameworks de memória de agente (persistência cross-sessão)
| Sistema | Diferencial | Modelo |
|---|---|---|
| **Letta (ex-MemGPT)** | Memória tiered auto-editável: core (in-context) / recall (histórico) / archival (vector store), agente decide via tool-call o que promover | OSS, YC ($10M seed) |
| **Mem0** | Camada de memória extraída-e-indexada por user/session/agent; retrieval por similaridade+keyword+entity no início da sessão | OSS + cloud |
| **EverMind / Zep** | Long-term memory como componente arquitetural separado do context window | Cloud |
| **Anthropic Memory Tool + Context Editing** | Primitivas nativas (set/2026): agente salva plano em memory file antes do truncamento em 200k; context-editing remove tool-outputs stale | Plataforma Claude |

### B. Compaction / context management (sobrevivência em sessão longa)
| Sistema | Diferencial | Nota |
|---|---|---|
| **Claude Code (harness nativo)** | 3 tiers: Microcompact (no-LLM, cache-hit, 0 custo) → Snip (LRU truncation) → Collapse (sumarização staged); auto-compact a 95% | É o harness que o oimpresso roda |
| **Codex CLI / OpenCode** | Compaction configurável + sliding window | Comparativo Daniel Vaughan 2026 |
| **"Intentional compaction" (padrão)** | Direcionar o agente a sumarizar progresso num markdown limpo ANTES do limite, reiniciar com o summary como input | Best-practice emergente |

### C. Durable execution / checkpointing (WIP sobrevive a crash)
| Sistema | Diferencial | Granularidade |
|---|---|---|
| **LangGraph checkpointer** | Checkpoint antes/depois de cada nó do grafo; protege falha de aplicação (raciocínio ruim, HITL pause, resume) | Node-level |
| **Temporal** | Event-sourced; protege falha de infra (crash de container, partição de rede) | Activity-level |
| **Modal + Temporal/LangGraph** | Combo de produção 2026 pra workloads longos GPU-intensivos | Híbrido |

### D. Handoff-document skills (o que o oimpresso já é)
`create-handoff` / `softaworks/agent-toolkit session-handoff` / "AI Handoff Prompt 8-section" — todos convergem no MESMO padrão que o oimpresso usa: markdown estruturado (estado atual, contexto crítico, próximos passos, decisões+rationale) gerado ANTES de limpar contexto. **O oimpresso está nesta categoria, e mais maduro que os templates públicos.**

---

## Matriz de capacidades (18 dimensões × oimpresso vs SOTA)

| # | Dimensão | oimpresso | SOTA 2026 | Δ |
|---|---|---|---|---|
| 1 | Cold-start orientado (estado vivo agregado) | `brief-fetch` ~3k tok, 7 seções fixas, cache 5min, substitui 5-8 queries | memory-tool retrieval semântico por user/session | ✅ par |
| 2 | Comando de retomada explícito | `/continuar` (cycles-active→my-work→my-inbox→handoff→último session log→resume+confirma) | "resume prompt" / handoff continuation | ✅ acima |
| 3 | Handoff append-only versionado | `memory/handoffs/YYYY-MM-DD-HHMM-slug.md`, 140 arquivos, schema validado em CI (ADR 0130) | handoff markdown | ✅ acima |
| 4 | Template canônico de handoff | `_TEMPLATE.md` 8 seções + frontmatter `tldr/next_steps/prs` | template 8-section | ✅ par |
| 5 | Encerramento confiável (dispara sem cobrar) | R12 + skill `encerrar-sessao` (lazy) + hook `force-r12` (UserPromptSubmit) = 3 camadas | nenhum concorrente tem trigger automático de fim | ✅ muito acima |
| 6 | Robustez do trigger em sessão >200 turnos | Ativação lazy via description-match + hook (resolve "saiu de contexto após 200 turnos") | "intentional compaction antes do limite" | 🟡 parcial (dispara no FIM, não no MEIO) |
| 7 | Defesa contra context-rot mid-sessão | ❌ nenhuma (microcompact do harness é o único — fora do controle do projeto) | compaction intencional @70%; checkpoint de plano em memory-file | 🔴 gap |
| 8 | Sobrevivência de WIP a crash | Regra cultural "git push WIP a cada 30min" (R3); zero checkpointing mecânico | LangGraph node-checkpoint / Temporal event-sourcing | 🔴 gap |
| 9 | Estado de trabalho (tasks) persistente | MCP `tasks-*` (ADR 0070), nunca markdown — sobrevive 100% entre sessões | state persistence em DB | ✅ acima |
| 10 | Paralelismo seguro (Claude-A vs Claude-B) | worktree isolada + `whats-active` (ADR 0119 Tier 1) + skill `session-start-check` | multi-agent coordination | ✅ acima (mas passivo) |
| 11 | Lease/lock formal contra colisão | ❌ Tier 2 dormente (ADR 0119); `mcp_work_leases UNIQUE(task_id)` aprovado D1 mas não construído | lease c/ TTL | 🟡 dormente |
| 12 | Custo de tokens da retomada | brief 3k + handoff ~30-80 linhas; MAS `/continuar` carrega `08-handoff.md` 145 linhas/~72k tok | retrieval seletivo top-k | 🔴 gap (índice monolítico) |
| 13 | Handoff zero-paste via canal de máquina | ADR 0283/0285: `cowork_handoffs` MySQL + tools `handoff-pending`/`-ack` HMAC + `handoff:stale-alert` cron | A2A / agent-to-agent bus | ✅ acima (pioneiro) |
| 14 | Gate de integridade do handoff | `handoff-integrity-guard.mjs` + `memory-schema-gate-extended.yml` (sem órfão, auto-contido, linha-d'água) | nenhum concorrente | ✅ muito acima |
| 15 | Detecção de drift de fechamento | Wagner cobra → reincidência ativa hook P2 dormente; sinal humano, não métrica | — | 🟡 reativo |
| 16 | Session log de longo prazo | `memory/sessions/*.md` (322 arquivos) — memória episódica do projeto | episodic memory | ✅ acima |
| 17 | Honestidade epistêmica na retomada | Lei de Uma Tela (VERDADE→PROPORÇÃO→MANDATO→PROVA): "não verifiquei" vs afirmação confiante; tag ✓lido de @main | — | ✅ muito acima |
| 18 | Robustez de transporte (handoff chega ao destino) | Incidente 2026-06-17: MCP server 19d stale → tools de handoff inalcançáveis; sem sentinela de drift main→CT100 | health-check de pipeline | 🔴 gap |

---

## Score % por sub-área (ponderado)

> Fórmula: `nota_global = Σ(peso_i × score_i)`. Pesos somam 100. Cada score com evidência.

| Sub-área | Peso | Score | = | Evidência |
|---|---:|---:|---:|---|
| **Cold-start / retomada** | 22 | 78% | 17.2 | `brief-fetch` 3k tok ([brief-first SKILL.md](../../.claude/skills/brief-first/SKILL.md)) + `/continuar` ([continuar.md](../../.claude/commands/continuar.md)) cobrem orientação; **−22% porque** `/continuar` passo 4 manda `@memory/08-handoff.md` que é 145 linhas/~72k tok (medido `wc -l`), anula a economia do brief |
| **Encerramento confiável** | 20 | 92% | 18.4 | 3 camadas de ativação ([encerrar-sessao SKILL.md](../../.claude/skills/encerrar-sessao/SKILL.md) + [force-r12-closing-signal.mjs](../../.claude/hooks/force-r12-closing-signal.mjs) + R12 [PROTOCOLO](../reference/PROTOCOLO-WAGNER-SEMPRE.md)); validação CI do schema (ADR 0130). **−8%** detecção de não-execução ainda é humana (Wagner cobra), não métrica auto |
| **Sobrevivência de WIP** | 18 | 70% | 12.6 | tasks no MCP (ADR 0070) = 100% durável; handoff append-only preserva narrativa. **−30% porque** WIP de código não-commitado tem ZERO checkpoint mecânico — só regra cultural "push a cada 30min" (R3§DURING) + lições repetidas de quase-perda (4h salvas por stash, sessão 2026-05-17) |
| **Paralelismo seguro** | 15 | 80% | 12.0 | worktree isolada + `whats-active` + `session-start-check` ([SKILL.md](../../.claude/skills/session-start-check/SKILL.md), ADR 0119). **−20%** é alerta PASSIVO (cultura, não enforcement); lease Tier 2 dormente; reincidência real de sessão-duplicada (handoffs 2026-06-20-2115, 2026-06-18 #2954 dup) |
| **Custo de tokens da retomada** | 13 | 62% | 8.1 | brief 3k + handoff 30-80 linhas é ótimo; **−38% porque** o índice `08-handoff.md` cresceu pra ~72k tok com parentéticos de 1000 palavras/entrada (ADR 0167 removeu o truncamento dos 5 e o limite de 300 linhas está perto de estourar) |
| **Robustez sessão longa (>200 turnos)** | 12 | 65% | 7.8 | R12 lazy resolve o trigger de FECHAMENTO em sessão longa (origem Larissa 17 PRs); **−35% porque** nada defende o MEIO da sessão — SOTA diz context-rot acelera >30k tok e compactar no limite (95%) é "o pior momento"; oimpresso não tem compaction intencional @70% nem checkpoint de plano em memory-file |
| **TOTAL** | **100** | | **76.1%** | |

---

## Top 10 gaps priorizados (impacto × esforço)

> Matriz: P0 = sangra agora · P1 = sangra em sessão grande · P2 = higiene · P3 = nice-to-have. Esforço em dev-days recalibrados (fator 10x IA-pair, ADR 0106).

| # | Gap | Impacto | Esforço | Prio | CONS/EVOL | Ref SOTA |
|---|---|---|---|---|---|---|
| 1 | **Sem defesa mecânica de context-rot mid-sessão** — R12 só dispara no fim; nada força "intentional compaction" no meio. SOTA: compactar @70%, não @95% | Alto | 1.5d | **P0** | CONSOLIDAR | Anthropic Cookbook compaction; context-rot >30k tok |
| 2 | **`08-handoff.md` virou monólito ~72k tok** — `/continuar` o carrega inteiro, anulando o brief 3k. ADR 0167 tirou o truncamento; review_trigger 300 linhas quase batido | Alto | 0.5d | **P1** | CONSOLIDAR | retrieval seletivo top-k |
| 3 | **WIP de código sem checkpoint** — só "git push a cada 30min" cultural; crash/clear perde horas (lição 4h salva por stash) | Alto | 1d | **P1** | CONSOLIDAR | LangGraph node-checkpoint |
| 4 | **Paralelismo é alerta passivo** — `whats-active` não bloqueia; sessão-duplicada reincidiu ≥2× (#2954 dup, 2026-06-20-2115) | Médio | 1d | **P1** | CONSOLIDAR (promover Tier 2 lease — gatilho ADR 0119 já batido 2×) | lease c/ TTL |
| 5 | **Transporte do handoff frágil** — MCP server ficou 19d stale → tools de handoff inalcançáveis; sem sentinela de drift main→CT100 | Médio | 1d | **P1** | CONSOLIDAR | health-check de pipeline |
| 6 | **Detecção de não-fechamento é humana** — Wagner cobra; sem métrica que detecte "sessão terminou sem handoff" | Médio | 0.5d | **P2** | CONSOLIDAR | loop fechado por métrica (princípio 4) |
| 7 | **Sem retrieval semântico de handoffs** — 140 arquivos só por glob/data; "como resolvemos X antes?" exige varredura manual | Médio | 1.5d | **P2** | CONSOLIDAR | Mem0/Letta archival recall |
| 8 | **`/continuar` não detecta sessão paralela viva** — passo 1 não chama `whats-active`; retoma sem saber de irmã ativa | Baixo | 0.25d | **P2** | CONSOLIDAR | multi-agent coordination |
| 9 | **Sem checkpoint de plano em memory-file** — plano da sessão vive só no context window; truncamento >200k perde a intenção | Médio | 0.5d | **P2** | CONSOLIDAR | Anthropic memory-tool (LeadResearcher salva plano) |
| 10 | **Skill `continuar` referenciada mas inexistente** — `encerrar-sessao` cita `../continuar/SKILL.md`; só existe o command `/continuar` (ref morta) | Baixo | 0.1d | **P3** | CONSOLIDAR | — |

---

## Decisão estratégica: CONSOLIDAR (não EVOLUIR)

A arquitetura de session-handoff do oimpresso **já é o paradigma certo** segundo o SOTA 2026: handoff-document estruturado + estado vivo agregado (`brief-fetch` ≈ memory-tool) + tasks duráveis no MCP (≈ state persistence) + ativação lazy de 3 camadas pra sobreviver à sessão longa. Nenhum concorrente público tem a combinação de **trigger automático de encerramento + gate de integridade em CI + canal zero-paste de máquina**. Trocar de paradigma (importar Temporal/LangGraph/Letta inteiro) seria over-engineering pra um ERP solo-founder — o próprio time já catalogou isso na sessão 2026-06-15 ("~80% do SOTA já existe; gap é mecanismo, não ferramenta; NÃO trazer Temporal/CRDT/A2A/GraphRAG/Letta"). O caminho é **endurecer as bordas**: tampar o context-rot mid-sessão (gap #1), desinchar o índice de retomada (gap #2), e dar checkpoint mecânico ao WIP (gap #3). São incrementos sobre fundação sólida — CONSOLIDAR.

## Roadmap curto (3 ondas — só por ser CONSOLIDAR, é incremental)

- **Onda 1 (P0/P1 — ~3d):** (a) skill `compactar-intencional` que detecta utilização >70% e força sumarização-pra-handoff-parcial ANTES do auto-compact 95% do harness (gap #1); (b) desinchar `08-handoff.md` — agrupar por quarter + mover entradas >60d pra `08-handoff-archive.md`, e fazer `/continuar` ler só os 5 mais recentes do índice + último session log (gap #2 + #8); (c) hook `Stop`/cron que checa "WIP não-commitado >30min" e nudga push (gap #3).
- **Onda 2 (P1/P2 — ~2.5d):** promover lease Tier 2 (`mcp_work_leases UNIQUE(task_id)` — gatilho ADR 0119 batido) (gap #4); sentinela de drift main→CT100 + health-check das tools de handoff (gap #5); métrica `jana:health-check` novo SQL "sessão fechada sem handoff em 24h" (gap #6).
- **Onda 3 (P2/P3 — ~2d):** retrieval semântico sobre `memory/handoffs/**` (reusar Meili já no stack) (gap #7); checkpoint de plano em memory-file no início de sessão grande (gap #9); criar a skill `continuar` real ou remover a ref morta (gap #10).

---

## Modos de falha conhecidos SEM defesa mecânica ainda

1. **Context-rot silencioso mid-sessão** — Claude degrada (recall some embora o conteúdo esteja presente) muito antes do auto-compact 95%. Hoje só o microcompact do harness atua, e ele NÃO reduz tamanho — só otimiza cache. Nenhum mecanismo do PROJETO força compaction intencional. (gap #1)
2. **Crash/`/clear` com WIP não-commitado** — perde horas. Defesa atual = regra cultural "push 30min". Já quase-aconteceu (4h salvas por stash do Wagner, 2026-05-17; HEAD destacado em worktree sem `-b`, 2026-06-18). (gap #3)
3. **Sessão duplicada na mesma branch** — Wagner replica prompt em 2-3 sessões; `whats-active` é passivo e `/continuar` nem o chama. Reincidiu (#2954 dup; handoff 2026-06-20-2115 "rodei o mesmo prompt em paralelo e dupliquei"). (gap #4, #8)
4. **Handoff escrito mas inalcançável pelo próximo agente** — MCP server stale 19d (2026-06-17) deixou `handoff-pending`/`-ack` mortos sem ninguém notar; sem sentinela. (gap #5)
5. **Auto-merge de handoff com erro de schema** — append-only + gate de integridade vira catch-22: se mergeia com `date:` não-quotado, o gate trava o PRÓXIMO handoff (catalogado 2026-06-19: "bati 2× `/date must be string`"). Defesa = disciplina humana de quotar, não mecânica.
6. **Índice de retomada que cresce sem teto** — ADR 0167 removeu o truncamento; o review_trigger de 300 linhas é manual e está a ~50% de estourar. Sem auto-arquivamento. (gap #2)

---

## Surpresa positiva (oimpresso > mercado)

1. **Encerramento auto-disparado de 3 camadas** — skill lazy (description-match) + hook UserPromptSubmit cross-platform + R12 Tier A. Nenhum handoff-skill público dispara SOZINHO no fim da sessão; todos exigem o humano lembrar de pedir "create handoff". O oimpresso resolveu exatamente o gap que o SOTA descreve ("gate na memória do humano").
2. **Gate de integridade do handoff em CI** — `handoff-integrity-guard.mjs` + schema validado (sem órfão, auto-contido, linha-d'água "pousou só pós-main"). Mecaniza o que o resto do mercado deixa como convenção.
3. **Honestidade epistêmica na retomada** — Lei de Uma Tela (VERDADE→PROPORÇÃO→MANDATO→PROVA) força ler `@main` agora e tagear ✓lido vs ⚠não-verifiquei. Diretamente combate o anti-padrão "afirmar de cópia local stale" — algo que nenhum framework de memória endereça.

## Surpresa negativa (mercado > oimpresso)

1. **Zero defesa de context-rot mid-sessão** — enquanto o SOTA (Anthropic Cookbook, LogRocket, Morph) prega "compactar intencionalmente @70%", o oimpresso só age no FECHAMENTO. A própria origem do R12-lazy (sessão Larissa 200+ turnos) prova que sessão longa é o ponto fraco — e a cura foi só pro trigger de fim, não pro meio.
2. **WIP sem checkpointing** — LangGraph/Temporal dão durabilidade automática node/activity-level; o oimpresso depende de regra cultural de push manual. Para um time que roda sessões épicas de 17 PRs / 8h, é o gap de maior risco-expectativa.

---

## Fontes (SOTA 2026)

- [State of AI Agent Memory 2026 — mem0.ai](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [7 State Persistence Strategies for Long-Running AI Agents — indium.tech](https://www.indium.tech/blog/7-state-persistence-strategies-ai-agents-2026/)
- [Claude Code Context Compaction (microcompact/snip/collapse)](https://y-agent.github.io/inside-claude-code/04-context-compaction.html)
- [Context Compaction Showdown — Codex/Claude Code/others](https://codex.danielvaughan.com/2026/04/10/context-compaction-showdown-coding-agents/)
- [Effective context engineering for AI agents — Anthropic](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents)
- [Context engineering: memory, compaction, tool clearing — Claude Cookbook](https://platform.claude.com/cookbook/tool-use-context-engineering-context-engineering-tools)
- [Durable Agent Execution in Production 2026: Temporal, LangGraph — AgentMarketCap](https://agentmarketcap.ai/blog/2026/04/10/durable-agent-execution-production-temporal-modal-event-sourced)
- [Durable Execution in LangGraph — vadim.blog](https://vadim.blog/durable-execution-agents-that-survive-failure-and-resume-where-they-left-off)
- [Letta (MemGPT) Walkthrough 2026 — SurePrompts](https://sureprompts.com/blog/letta-memgpt-walkthrough)
- [Mem0 vs Letta — vectorize.io](https://vectorize.io/articles/mem0-vs-letta)
- [AI Handoff Prompt 8-section template — Don't Sleep On AI](https://www.dontsleeponai.com/handoff-prompt)
- [Context Rot: Why your AI gets dumber — Josh Owens](https://joshowens.dev/context-rot/)
- [Context Rot is slowing down your AI agent — LogRocket](https://blog.logrocket.com/context-rot-slowing-down-your-ai-agent-how-fix/)

## Evidência interna consultada
- [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) · [ADR 0167](../decisions/0167-errata-0130-indice-handoff-historico-longo.md) · [ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) · [ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md) · [ADR 0283](../decisions/0283-handoff-loop-zero-paste.md)
- Skills: `brief-first`, `encerrar-sessao`, `session-start-check` · Hook `force-r12-closing-signal.mjs` · Command `/continuar`
- R12 [PROTOCOLO-WAGNER-SEMPRE](../reference/PROTOCOLO-WAGNER-SEMPRE.md) · `memory/handoffs/_TEMPLATE.md` (140 handoffs, 322 session logs)
