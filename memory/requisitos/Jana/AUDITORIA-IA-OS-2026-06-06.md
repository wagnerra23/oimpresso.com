---
id: requisitos-jana-auditoria-ia-os-2026-06-06
title: "Auditoria Sênior IA OS — sistema operacional agêntico do oimpresso"
type: auditoria
status: draft
authority: tecnico
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-06-06
decided_by: [audit-senior-expert]
module: governance
tier: TECHNICAL_AUDIT
trust_level: advise
pergunta_origem: "grade no iaos — atualizar 68/100 (2026-05-29) pra hoje + virar artefato canônico"
parent_artifacts:
  - memory/handoffs/2026-05-29-1145-ia-os-team-os-audit-automation-registry.md
  - memory/governance/AUTOMATIONS.md
  - memory/requisitos/TaskRegistry/AUDIT-TEAM-OS-2026-05-29.md
  - memory/reference/pattern-audit-and-fix-cycle.md
related_adrs: [0035, 0051, 0053, 0058, 0061, 0062, 0070, 0091, 0093, 0094, 0132, 0133, 0150, 0155, 0162, 0234]
score_anterior: 68/100 (2026-05-29, narrado só em handoff)
score_atual: 79/100 (2026-06-06, este artefato)
delta: +11
authors: [audit-senior-expert]
---

# Auditoria Sênior — IA OS oimpresso (2026-06-06)

> **O que é a IA OS:** o sistema operacional **agêntico inteiro** do oimpresso — não um módulo de negócio. É a pilha L1–L7 da Constituição v2 ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)): MCP Core → ADS firewall → Skills → Playbooks → ADRs → Charters → Daily Brief, mais cognição (retrieval/memória/RAG), observability e enforcement runtime.
>
> **Por que existe este artefato:** a nota **68/100** (2026-05-29) nunca virou artefato próprio — viveu só no handoff `2026-05-29-1145`. Este documento é o artefato que faltava + recalibra a nota pra hoje, com **evidência de código verificada** (anti-alucinação: cada afirmação confere com Read/Glob/Grep/Bash; nada afirmado sem ver).

---

## 0. Resumo executivo

**Nota total ponderada: 79/100** (era 68/100 em 2026-05-29) — **delta +11 em 8 dias** de trabalho de governança/observability do time.

O salto **NÃO** veio de Wagner ligar o Brain B (decisão firme: não ligar — custo recorrente). Veio de:
1. **Observability deixou de ser placeholder** — Langfuse foi **realmente deployado no CT 100 em 2026-05-10** (README com RAM medida real + 5 bugs catalogados de deploy real), e a infra OTel materializou em código (`mcp_observability_spans` migration, `ObservabilityAggregateCommand`, `ObservabilitySnapshotService`+test, cron `observability:aggregate-daily` 02:00).
2. **Governança fechou o gap #11** — Automation Registry **aceito + implementado** ([ADR 0234](../../decisions/0234-automation-registry-mcp.md)): tabela `mcp_automations`, entidade `McpAutomation`, tool `automations-list`, `AutomationRegistrySync`, command `jana:automations:sync`.
3. **Enforcement runtime saltou de "1/8" pra ~26 hooks ativos** defendendo mecanicamente múltiplos princípios duros.
4. **Cognição amadureceu** — gold-set anti-alucinação 100→115 (casos derivados de erros reais), 3 RAGAS gates em CI, hierarquia de reranker completa em prod.

**Top-3 gaps remanescentes (impacto × esforço):**
1. **OTel collector CT 100 NÃO deployado** (Langfuse sim; collector não) → D6.b/D9.b ainda placeholder em ~15 módulos. **Pendente Wagner (Tier 0, custo)**. Esforço destravar: ~0.5 d/pp (deploy) — bloqueado por decisão, não por código.
2. **ADS roda mas o efeito-executor está capado por decisão** — Brain B daemon agendado (`ads:process-brain-b` 5min) mas Wagner mantém Brain B desligado. Não é dívida técnica; é trade-off custo consciente. Gap real: **falta a camada barata "Jana-as-assignee read-only"** (Brain A `gpt-4o-mini`) que entregaria efeito Rovo sem custo recorrente alto.
3. **Self-audit aponta pro arquivo errado** — `SystemAuditCommand::checkEvalCiGate()` procura `eval-recall-gate.yml` que **não existe**; os gates reais são `jana-ragas-gate.yml`/`ragas-gate.yml`/`jana-ragas-canary.yml`. Falso-negativo no próprio health-check. Esforço: ~0.1 d/pp.

**Recomendação: CONSOLIDAR.** A IA OS já é world-class em cognição, governança e MCP. Os 21 pontos que faltam pra 100 são **majoritariamente decisões de Wagner (custo)**, não engenharia. O teto pragmático honesto é **~90-92%** sem ligar Brain B — acima disso é custo recorrente que o ADR 0094 §2 (tiered cost) desaconselha sem sinal de cliente ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

---

## 1. Nota por dimensão + delta vs 68/100

Pesos calibrados por **impacto no propósito da IA OS** (operar o ERP com IA-pair barato, governado, multi-tenant). Cognição + Governança + MCP são o núcleo que já gera valor diário → peso alto. Autonomia ADS é estratégica mas conscientemente capada → peso médio. Observability + Enforcement são habilitadores → peso médio.

| # | Dimensão | Peso | Nota maio (est.) | Nota hoje | Δ | Evidência verificada |
|---|---|:---:|:---:|:---:|:---:|---|
| 1 | **Cognição** (retrieval/memória/KB/brief) | 25% | 88 | **90** | +2 | `KbBgeRerankerService` (BGE-v2-m3 self-host) + hierarquia `Reranker/Rrf/Bge/Llm/Null` em `Modules/Jana/Services/Retrieval/`; `KbRagasEvalTest`; gold-set 100→115 ([handoff 06-01](../../handoffs/2026-06-01-1100-jana-pro-paywall-mais-3-champion-makers-ia.md)); KB grafo `kb_nodes` ([ADR 0150](../../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md)) + `KbBridgeFromMcpJob` 15min; brief 6×/dia ([ADR 0091](../../decisions/0091-daily-brief-context-product.md)) |
| 2 | **Governança** (ADRs/skills/registry/Tier 0) | 20% | 80 | **88** | +8 | Automation Registry implementado ([ADR 0234](../../decisions/0234-automation-registry-mcp.md): `mcp_automations`+`McpAutomation`+`AutomationsListTool`); 255 ADRs canon append-only; `governance:audit`/`detect-drift`/`scorecard-snapshot` crons; multi-tenant Tier 0 `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) |
| 3 | **MCP** (server/tools/sync/audit) | 15% | 90 | **91** | +1 | 35 MCP Tools em `Modules/Jana/Mcp/Tools/`; sync git→MCP `mcp:sync-memory` 5min + webhook; `mcp_audit_log` append-only; `cc-search`/`memoria-search`/`decisions-search` |
| 4 | **Autonomia ADS** (dual-brain/policy/HITL) | 15% | 35 | **62** | +27 | **NÃO está dormente em código** — `DecisionRouter` (OTel-instrumentado), `PolicyEngine`/`RiskEngine`/`ConfidenceEngine`, AI Agents (`PlannerAgent`/`ReviewerAgent`/`ProjectDecomposerAgent`), Tools (`WriteFile`/`RunTest`/`GitCommitWip`/`GitInspect`), crons `ads:*` agendados. **Capado por decisão Wagner** (Brain B off, custo) — não por bug |
| 5 | **Observability** (Langfuse/OTel/custo IA) | 15% | 30 | **72** | +42 | **Langfuse DEPLOYADO CT 100 2026-05-10** (README RAM real + 5 bugs reais); `OtlpHttpHandler`; `mcp_observability_spans` migration + `ObservabilityAggregateCommand` + cron 02:00; `OtelHelper` PII-guard real. **Gap: collector CT 100 ainda não subiu** (`config/otel.php` enabled=false default) |
| 6 | **Enforcement runtime** (defesa mecânica) | 10% | 15 | **70** | +55 | ~26 hooks ativos em `settings.json` (de 44 presentes): `block-memory-drift`, `pii-redactor`, `block-destructive`, `block-claim-without-evidence`, `commit-discipline-check`, `block-mwart-violation`, `block-routes-string-legacy`, `block-bom-encoding`, `block-merge-markers`, `block-serving-branch-switch` + advisory nudges. 5/8 princípios duros com defesa mecânica direta |

### Cálculo ponderado

```
Cognição     90 × 0.25 = 22.5
Governança   88 × 0.20 = 17.6
MCP          91 × 0.15 = 13.65
Autonomia    62 × 0.15 =  9.3
Observability 72 × 0.15 = 10.8
Enforcement  70 × 0.10 =  7.0
                        ───────
                    TOTAL = 80.85 ≈ 79/100 (arredondado conservador p/ deploy gaps)
```

> **Por que 79 e não 81:** desconto de honestidade — Langfuse deployado mas OTel collector (D6.b/D9.b real em 15 módulos) ainda pendente; `JANA_RETENTION_ENABLED` ainda false em prod; self-audit com falso-negativo. Pontos "no código" ≠ pontos "em prod fechando o loop". A regra do ADR 0094 §4 (loop fechado por métrica) exige a métrica REAL, não o código pronto.

### Sobre a discrepância "dormente/não-deployado" de maio

O handoff de maio descreveu **"autonomia ADS dormente + observability não-deployada"**. A verificação de código de hoje mostra que **ambas afirmações estavam parcialmente desatualizadas já em maio**:
- **ADS:** o módulo tem ~80 arquivos PHP (Services + Agents + Tools + crons agendados). "Dormente" é correto no sentido operacional (Brain B off por decisão), **errado** no sentido de código (está construído e wired).
- **Observability:** Langfuse subiu em **2026-05-10** (19 dias ANTES do handoff de 68/100). O termo "não-deployada" provavelmente se referia ao **OTel collector** (ADR 0162, 2026-05-17, ainda pendente) — mas a leitura literal subestimou o estado real. Este é o tipo de drift que um artefato canônico (vs nota solta em handoff) corrige.

---

## 2. Comparativo estado-da-arte 2026

Mini-tabela % atual → target por dimensão, ancorada em pesquisa (12 WebSearch).

| Dimensão | oimpresso hoje | SOTA 2026 (benchmark) | Target realista | Fonte |
|---|:---:|---|:---:|---|
| **Cognição/RAG** | 90 | BGE-v2-m3 é SOTA self-host multilingual (50-100ms GPU, R$ [redacted Tier 0]); upside só Cohere Rerank 4 / Voyage 2.5 (32K ctx vs 8K). Pattern: hybrid+RRF→rerank top-K | 92 | [BSWEN rerankers](https://docs.bswen.com/blog/2026-02-25-best-reranker-models/), [Agentset](https://agentset.ai/blog/best-reranker) |
| **Memória** | 90 | Pattern 5 (Enterprise Context Layer) + Pattern 4 (KG+vector) — oimpresso faz os dois. Falta episódica/procedural self-editing (Letta/MemGPT) — mas ADR 0061 proíbe auto-mem por design | 90 (teto por design) | [Atlan agent memory](https://atlan.com/know/agent-memory-architectures/), [Vectorize Mem0 vs Letta](https://vectorize.io/articles/mem0-vs-letta) |
| **Autonomia/orquestração** | 62 | Three-Agent Harness Anthropic (Planner/Generator/Evaluator, 5-15 ciclos crítica) = exatamente o ADS. Subagent 1-nível-deep; supervisor é o default de prod 2026. Devin 51.5% SWE-bench; frontier+scaffolding 80%+ | 75 (sem Brain B) | [Anthropic Agent SDK](https://code.claude.com/docs/en/agent-sdk/overview), [DigitalApplied orchestration](https://www.digitalapplied.com/blog/multi-agent-orchestration-5-patterns-that-work) |
| **Observability** | 72 | Langfuse = OTel backend de facto (2.300+ empresas, comprado ClickHouse jan/2026 $400M). Instrumentar contra OTel GenAI conventions (não SDK proprietário) é a disciplina-chave — oimpresso já faz (ADR 0051) | 88 | [Langfuse OTel](https://langfuse.com/integrations/native/opentelemetry), [AI Agent Observability 2026](https://www.digitalapplied.com/blog/ai-agent-observability-2026-tracing-monitoring-stack-guide) |
| **Enforcement/guardrails** | 70 | Runtime enforcer valida CADA ação contra política antes de executar (OPA-style); HITL pre/post/conditional. EU AI Act ago/2026 torna human oversight obrigatório legal | 82 | [General Analysis guardrails](https://generalanalysis.com/guides/best-ai-guardrails), [Galileo HITL](https://galileo.ai/blog/human-in-the-loop-agent-oversight) |
| **Team OS/governança** | 88 | Jira Team '26 Teamwork Graph (~150bi conexões automático) + Rovo agents como assignees. oimpresso tem os dados crus (`git_links`+`memory_links`+`events`) mas ligação manual | 90 | [SiliconANGLE Team '26](https://siliconangle.com/2026/05/06/atlassian-opens-teamwork-graph-pushes-rovo-agentic-execution-team-26/) |

**Leitura sênior:** em **cognição, memória e governança** o oimpresso está **no estado-da-arte ou acima** (audit append-only > Jira; PII-guard em spans; reranker self-host LGPD-safe). O delta pra SOTA está concentrado em **(a) ligar o que já existe** (OTel collector, Jana-as-assignee read-only) e **(b) automatizar o que é manual** (Teamwork Graph leve). Nenhum gap exige nova arquitetura.

---

## 3. Top-10 gaps priorizados (impacto × esforço)

Esforço recalibrado fator 10x IA-pair ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)); "d/pp" = dev-day-pessoa. **Isolável** = pode virar agent implementador Fase 3 sem conflito.

| # | Gap | Impacto | Esforço | Estado atual | Target | Isolável? |
|--:|---|:---:|:---:|---|---|:---:|
| **G1** | **OTel collector CT 100 não subiu** — D6.b/D9.b placeholder em ~15 módulos | ALTO | 0.5 d/pp* | Langfuse OK; collector pendente; `OTEL_ENABLED=false` | Collector + `OTEL_ENABLED=true` CT 100 → D9.b real | Não (deploy infra, **Tier 0 Wagner**) |
| **G2** | **Self-audit falso-negativo** — `checkEvalCiGate()` aponta `eval-recall-gate.yml` inexistente | MÉDIO | 0.1 d/pp | Gates reais: `ragas-gate.yml`+`jana-ragas-gate.yml`+`jana-ragas-canary.yml` | Corrigir path no `SystemAuditCommand` | **Sim** (`Modules/Jana/Console/Commands/SystemAuditCommand.php`) |
| **G3** | **Jana-as-assignee read-only** (efeito Rovo barato) | ALTO | 1.5 d/pp | `tasks-suggest-*` designed; ADS Brain A disponível | summarize/draft/link-memory/suggest-priority via `gpt-4o-mini` | **Sim** (`Modules/ADS` + tool MCP nova) |
| **G4** | **Teamwork Graph leve** — task↔commit↔doc↔pessoa automático | MÉDIO | 1.0 d/pp | Dados crus existem (`git_links`+`memory_links`+`events`); ligação manual | View materializada zero-LLM | **Sim** (`Modules/TeamMcp` ou `Modules/Jana`) |
| **G5** | **`JANA_RETENTION_ENABLED=true` em prod** (LGPD purge) | MÉDIO | 0.3 d/pp* | `RetentionPurgeCommand`+`RetentionPurgeService`+Pest prontos; flag false | Ativar pós-canary 7d | Não (**Tier 0 Wagner** + canary) |
| **G6** | **RAGAS real-mode diário** (hoje semanal/canary) | MÉDIO | 0.3 d/pp* | 3 gates CI existem; cadência conservadora | Subir cadência (~R$ [redacted Tier 0]/mês) + apertar thresholds | Parcial (custo baixo, **Wagner**) |
| **G7** | **Push de notificação** (`my-inbox` pull-only) | MÉDIO | 0.5 d/pp | `mcp_inbox_notifications` existe; só pull | Push Centrifugo/WhatsApp (Centrifugo já no stack!) | **Sim** (`Modules/TeamMcp`+Centrifugo) |
| **G8** | **Reranker contexto longo** — BGE-v2-m3 capa 8K tokens | BAIXO | 0.5 d/pp | BGE-v2-m3 self-host | Fallback Voyage/Cohere p/ docs >8K (raro no corpus PT-BR) | **Sim** (`Modules/Jana/Services/Retrieval`) |
| **G9** | **Hooks presentes-mas-inativos** — `block-module-drift`+`block-pr-without-approval` no FS mas fora do `settings.json` | BAIXO | 0.3 d/pp | 44 hooks no FS, ~26 ativos | Decidir ativar/aposentar + registrar no Registry | **Sim** (`.claude/settings.json` + AUTOMATIONS.md) |
| **G10** | **Initiatives (Tier 3)** — 5º nível acima do epic | BAIXO | 1.0 d/pp | Adiado por design ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)) | Só com cliente B2B (sinal qualificado) | **Sim** (adiar até sinal — ADR 0105) |

\* Gaps G1/G5/G6 são **deploy/decisão**, não engenharia — o código está pronto. O esforço listado é o de execução; o bloqueio é Tier 0 Wagner (custo recorrente).

### Matriz impacto × esforço

```
              ESFORÇO BAIXO          ESFORÇO ALTO
IMPACTO  ┌──────────────────────┬──────────────────────┐
 ALTO    │ G1 (deploy)*  G2     │ G3 (Jana read-only)  │
         │                      │                      │
         ├──────────────────────┼──────────────────────┤
 MÉDIO   │ G5* G6* G7           │ G4 (Teamwork Graph)  │
 /BAIXO  │ G8 G9                │ G10 (adiar)          │
         └──────────────────────┴──────────────────────┘
  Quick wins: G2 (0.1d) → G1/G5/G6 (deploy, Wagner) → G7/G8/G9
  Estratégico: G3 (efeito Rovo barato) → G4 (graph)
```

---

## 4. Roadmap CONSOLIDAR vs EVOLUIR

### CONSOLIDAR (já world-class — NÃO reauditar sem novo trigger)

| Capacidade | Por quê é world-class | Não mexer |
|---|---|---|
| **Cognição/retrieval** | Hierarquia reranker completa (Bge/Rrf/Llm/Null) + RAGAS gates + gold-set anti-alucinação derivado de erros reais + KB grafo. = ou > SOTA self-host | Só G8 (contexto longo) se corpus crescer |
| **Memória/knowledge architecture** | Pattern 5 + Pattern 4, git-canonical → MCP → RAG → brief. ADR 0061 (zero auto-mem) é constraint consciente, não defeito | Teto por design atingido |
| **MCP Core** | 35 tools, sync bidir git↔MCP, audit append-only, 6×/dia brief. Acima do Jira em auditabilidade | Manutenção, não reauditoria |
| **Governança/Tier 0** | Automation Registry fecha gap #11; multi-tenant defense-in-depth; 255 ADRs append-only; drift detectors em cron | Manutenção |
| **Dual-brain ADS (arquitetura)** | Three-Agent Harness Anthropic está implementado (Planner/Reviewer + DecisionRouter + Policy/Risk/Confidence). Arquitetura = SOTA | Não rearquitetar — só ligar quando houver sinal |

### EVOLUIR (gaps reais com ROI)

| Onda | Itens | Custo | Efeito |
|---|---|:---:|---|
| **Onda A — quick wins (custo ~zero)** | G2 (self-audit fix) + G9 (hooks limpeza) + G8 (reranker longo opcional) | ~0.5 d/pp | Fecha falso-negativos + dívida de inventário |
| **Onda B — deploy (decisão Wagner)** | G1 (OTel collector) + G5 (retention prod) + G6 (RAGAS diário) | ~1.1 d/pp execução, **bloqueado Tier 0** | Observability 72→85; loop fechado por métrica REAL |
| **Onda C — AI-native barato** | G3 (Jana-as-assignee read-only) + G4 (Teamwork Graph) + G7 (push) | ~3.0 d/pp | Autonomia 62→72; efeito Rovo SEM Brain B |
| **Adiar conscientemente** | G10 (Initiatives) + Brain B real + Agent Teams orchestration (~3-4× tokens) | — | Até sinal de cliente B2B ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) |

---

## 5. Métrica de saturação — onde parar

Coerente com [GAP-ANALYSIS-91-100](GAP-ANALYSIS-91-100-2026-05-13.md) §saturação e [pattern-audit-and-fix-cycle](../../reference/pattern-audit-and-fix-cycle.md):

> **Parar de subir a nota quando custo marginal > 2.5 d/pp por ponto E o ganho não tem dor real pra time de 5.**

**Teto pragmático honesto da IA OS: ~90-92%** (não 100%, não 97-98%) — porque os últimos pontos exigem:
- **Brain B ligado** (custo recorrente $/dia) → bloqueado por decisão Wagner, corretamente (ADR 0094 §2 tiered cost).
- **Agent Teams orchestration** → 3-4× tokens, sem dor real pra time de 5.
- **Initiatives/SLA** → sem cliente B2B que pague (sinal qualificado ausente).

**Trajetória recomendada:** 79 → **85** com Onda A+B (quick wins + deploy, ~1.6 d/pp, maior parte é decisão de deploy) → **~90** com Onda C (AI-native barato, ~3 d/pp). Acima de 90 = ligar Brain B = só com sinal de cliente.

**Sinal de parada:** quando `jana:system-audit` ficar 5/5 verde **com métricas reais** (não placeholder) E o `jana:health-check` 5/5 verde por 30 dias consecutivos. Aí a IA OS está "fechada por métrica" no sentido do ADR 0094 §4 — e subir mais é vaidade de número, não valor.

---

## 6. Surpresa estratégica (finding fora do escopo do gap-analysis)

**O maior risco da IA OS não é técnico — é drift de auto-auditoria.** Três evidências convergem:
1. A nota 68/100 viveu 8 dias só num handoff, nunca virou artefato (este documento corrige).
2. `SystemAuditCommand` aponta pra um arquivo de CI que não existe → o próprio sistema de self-audit dá falso-negativo silencioso (G2).
3. O handoff de maio descreveu observability como "não-deployada" quando Langfuse já estava em prod há 19 dias.

A IA OS tem **enforcement de DRIFT DE CÓDIGO** excelente (drift detectors, scorecard snapshots, freshness check), mas **enforcement de DRIFT DE AUTO-DESCRIÇÃO** fraco — afirmações sobre o próprio estado envelhecem sem gate. **Recomendação estratégica:** tornar a nota da IA OS uma **métrica versionada** (tabela `mcp_scorecard_runs` já existe — bucket `ia_os`) com snapshot semanal automático, em vez de prosa em handoff. Custo ~zero, fecha o meta-loop. Isto é mais valioso que qualquer um dos 10 gaps individuais porque protege a confiabilidade de TODAS as auditorias futuras.

---

## 7. Fontes

**Internas (verificadas via Read/Glob/Grep/Bash):** `Modules/ADS/**` (Services/Agents/Tools/Commands), `Modules/KB/Services/KbBgeRerankerService.php`, `Modules/Jana/Services/Retrieval/*`, `Modules/Jana/Mcp/Tools/*` (35 tools), `Modules/Jana/Console/Commands/SystemAuditCommand.php`, `Modules/Governance/{Console/Commands/ObservabilityAggregateCommand,Database/Migrations/*observability_spans*,Services/ObservabilitySnapshotService}.php`, `app/Util/OtelHelper.php`, `app/Logging/OtlpHttpHandler.php`, `docker/langfuse/README.md`, `.claude/settings.json` (~26 hooks ativos), `.github/workflows/{ragas-gate,jana-ragas-gate,jana-ragas-canary}.yml`, ADRs 0094/0132/0133/0150/0162/0234.

**Externas (estado-da-arte 2026):**
- [Claude Agent SDK overview](https://code.claude.com/docs/en/agent-sdk/overview) + [Multi-Agent Orchestration 5 Patterns](https://www.digitalapplied.com/blog/multi-agent-orchestration-5-patterns-that-work)
- [Langfuse OTel integration](https://langfuse.com/integrations/native/opentelemetry) + [AI Agent Observability 2026](https://www.digitalapplied.com/blog/ai-agent-observability-2026-tracing-monitoring-stack-guide)
- [Best Reranker Models 2026 (BSWEN)](https://docs.bswen.com/blog/2026-02-25-best-reranker-models/) + [Agentset reranker leaderboard](https://agentset.ai/blog/best-reranker)
- [Best AI Guardrails 2026 (General Analysis)](https://generalanalysis.com/guides/best-ai-guardrails) + [HITL oversight (Galileo)](https://galileo.ai/blog/human-in-the-loop-agent-oversight)
- [Devin/Cursor SWE-bench 2026 (Neuronad)](https://neuronad.com/cursor-vs-devin/) + [SWE-bench leaderboard](https://awesomeagents.ai/leaderboards/swe-bench-coding-agent-leaderboard/)
- [Agent Memory Architectures (Atlan)](https://atlan.com/know/agent-memory-architectures/) + [Mem0 vs Letta (Vectorize)](https://vectorize.io/articles/mem0-vs-letta)
- [Atlassian Team '26 Teamwork Graph (SiliconANGLE)](https://siliconangle.com/2026/05/06/atlassian-opens-teamwork-graph-pushes-rovo-agentic-execution-team-26/)

---

**Última atualização:** 2026-06-06 — audit-senior-expert · IA OS · **79/100** (+11 vs 68/100 maio) · recomendação **CONSOLIDAR** · teto pragmático ~90-92% sem Brain B · surpresa: tornar a nota IA OS métrica versionada (anti-drift de auto-descrição).
