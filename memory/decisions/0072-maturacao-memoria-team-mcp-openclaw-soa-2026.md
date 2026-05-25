---
slug: 0072-maturacao-memoria-team-mcp-openclaw-soa-2026
number: 72
title: "Maturação memória + Team MCP — gaps identificados vs OpenClaw/Mem0/Letta/Zep/A-Mem (mai/2026)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-05"
module: copiloto
quarter: 2026-Q2
tags: [memoria, mcp, team-mcp, retrieval, governanca, roadmap]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0035-stack-canonica-ia-laravel-ai-memoria-contrato
  - 0036-replanejamento-meilisearch-first
  - 0037-roadmap-evolucao-tier-7-plus
  - 0049-camadas-memoria-agente-fase-por-fase
  - 0050-metricas-obrigatorias-memoria-table
  - 0051-schema-proprio-adapter-otel-genai
  - 0052-contextonegocio-expor-multiplos-angulos
  - 0053-mcp-server-governanca-como-produto
  - 0061-conhecimento-canonico-git-mcp-zero-automem
pii: false
review_triggers:
  - "Mem0 publicar nova versão major (>=2.0)"
  - "Letta atingir GA com sleep-time agents estáveis"
  - "Anthropic Memory Tool sair de beta"
  - "Atingir Recall@5 ≥ 0.85 em LongMemEval-PT (gate antes de P3)"
---

# ADR 0072 — Maturação memória + Team MCP

## Contexto

Wagner pediu pra "amadurecer o processo de memória estilo OpenClaw" e "amadurecer a memória do MCP do oimpresso pra entregar Team MCP — regras e conhecimento centralizado". Pesquisa **2026-05-05** validou:

**OpenClaw é real** ([openclaw.ai](https://openclaw.ai/)) — framework MIT local-first com 3 arquivos Markdown core (`MEMORY.md`, `memory/YYYY-MM-DD.md`, `DREAMS.md`) + plugins (Memory Wiki, Dreaming, Commitments, Honcho/QMD/LanceDB backends). **A alegação dos "8 pilares" não é doutrina oficial OpenClaw** — bate com síntese terceirizada e/ou cruzamento com [arquitetura de 12 camadas de comunidade](https://github.com/coolmanns/openclaw-memory-architecture). Útil como provocação, não como referência canônica.

**Estado-da-arte 2026 (8 frameworks comparados):**

| Stack | Movimento canônico | Benchmark | OSS |
|---|---|---|---|
| [Mem0](https://github.com/mem0ai/mem0) | Extração seletiva + graph opcional + async writes | LongMemEval **93.4** / LoCoMo 91.6 (<7k tokens/query) | Apache 2.0 |
| [Letta (ex-MemGPT)](https://github.com/letta-ai/letta) | Core/Recall/Archival; agente edita próprios memory blocks via tool calls; sleep-time agents | DMR (estabeleceu) | Apache 2.0 |
| [Zep / Graphiti](https://arxiv.org/abs/2501.13956) | Temporal knowledge graph bi-temporal (cada fato com janela validade) | DMR **94.8**; LongMemEval **+18.5%** acc, **-90%** latência | Graphiti OSS, Zep Cloud SaaS |
| [Cognee](https://github.com/topoteretes/cognee) | Graph-vector híbrido; pipelines `cognify`+`memify`; 14 modos retrieval; MCP server | LoCoMo (resultados em blog) | Apache 2.0 |
| [A-Mem](https://arxiv.org/abs/2502.12110) | Zettelkasten dinâmico — cada fato vira nota com tags/links auto-gerados, reorganização contínua | NeurIPS 2025 — supera SOTA em 6 foundation models | Sim |
| [Anthropic Memory Tool](https://platform.claude.com/docs/en/agents-and-tools/tool-use/memory-tool) | Filesystem `/memory`; agente faz tool calls, app executa local; Managed Agents beta abr/2026 | Não publicado | Spec aberta |
| OpenClaw | Markdown 3-file local-first + plugins; integra Mem0/Cognee | Não publicado | MIT |
| **oimpresso** (atual) | laravel/ai + Meilisearch hybrid + ContextoNegocio 3 ângulos + MCP server governado | Recall@5 não medido em PT-BR golden set | privado |

**8 princípios convergentes** (≥3 frameworks confirmam cada um):

1. Multi-scope isolation (user/agent/session/org) — Mem0, Letta, Zep, Cognee
2. Async writes / sleep-time consolidation — Letta, Mem0, OpenClaw (Dreaming)
3. Hybrid retrieval (vector + estrutura + grafo) — Cognee, Zep, Mem0g, Letta
4. Agente gerencia própria memória via tool-calls — Letta, Anthropic Memory Tool, A-Mem
5. **Temporal validity** (fato com janela) — Zep/Graphiti, Cognee, A-Mem
6. Compressão / consolidação dirigida por evento — OpenClaw, Letta, Mem0
7. File-based / human-editable como source-of-truth — OpenClaw, Anthropic, Letta export
8. **MCP como plano de controle inter-agente** — Cognee, Anthropic, oimpresso (já temos)

## Decisão

**Maturação em 4 movimentos priorizados**, cada um separado em ADR de implementação posterior. Sequência por dor decrescente / risco crescente:

### P0 — Skills + Policies como entidades MCP governadas (Team MCP completo)

**O que:** criar tabelas `mcp_skills` e `mcp_policies` (espelhos governados de `.claude/skills/*/SKILL.md` e regras hardcoded em `Modules/ADS/Services/PolicyEngine.php`). Sync via webhook GitHub (mesmo fluxo de `mcp_memory_documents`, ADR 0053). Tools MCP novas: `skills-search`, `skills-fetch`, `policies-active`. RBAC: leitura pra time inteiro, escrita só Wagner via PR.

**Por quê é P0:** hoje skill nova exige cada dev (Felipe/Maíra/Luiz/Eliana) fazer `git pull` + reiniciar Claude Code. PolicyEngine vive só em código — auditoria de "quem aprovou esta regra" é impossível. Bloqueia o pedido literal "Team MCP".

**Custo estimado:** 1 sprint (5 dias), reusa infra de 0053 + 0061.

### P1 — Temporal validity (estilo Zep/Graphiti) em `copiloto_memoria_facts`

**O que:** adicionar `valid_from` / `valid_until` (já existe parcial — `valid_until = NULL` significa atual; setado = superseded). Operacionalizar:
- `MeilisearchDriver::buscar()` filtra por `valid_until IS NULL OR valid_until > NOW()` por padrão
- nova tool MCP `memoria-historica` aceita parâmetro `as_of: <data>` pra time-travel queries
- `ExtrairFatosAgent` detecta atualização (mesmo subject + predicate, valor diferente) e supersede em vez de duplicar

**Por quê é P1:** [LongMemEval](https://arxiv.org/abs/2410.10813) mostra 30% queda em LLMs comerciais na capacidade "knowledge updates". Larissa pergunta "qual o faturamento" amanhã pega valor de hoje, não de 3 meses atrás. Pode-se medir.

**Custo estimado:** 3 dias, mexe em 1 service + 1 migration.

### P2 — Score por-memória + pruning inteligente

**O que:** adicionar colunas em `copiloto_memoria_facts`: `confidence` (0-1, ajustada por hits + feedback), `last_validated_at`, `usage_count`, `success_rate`. Re-rank do `LlmReranker` passa a considerar score. Command mensal `copiloto:memoria:prune` aposenta fatos com `confidence < 0.3 AND last_validated_at < 90d`.

**Por quê é P2 (não P1):** sem golden set LongMemEval-PT (gate review trigger), não dá pra calibrar threshold de confidence sem chutar. Faz mais sentido depois de ter métrica de baseline.

**Custo estimado:** 1 sprint (5 dias) + dependência de golden set 50 perguntas Larissa-style (já está na fila — MEM-MET-5).

### P3 — Action-aware retrieval + meta-memory (experimental)

**O que:** memória sugere skill/tool ao agente. Estrutura: ao recall de fato, atrelar `skill_hint: "ads-decision-flow"` ou `tool_hint: "GitInspectTool"` se padrão se repetiu. Agente recebe junto com contexto. Meta-memory = memória sobre quais memórias funcionam (`copiloto_memoria_metricas` agregado por categoria).

**Por quê é P3:** estado-da-arte em 2026 (A-Mem Zettelkasten + Cognee `memify`), mas custo/benefício depende de P1+P2. Faz pouco sentido sem temporal validity nem confidence — sugerir skill com base em padrão obsoleto piora o agente.

**Custo estimado:** sprint inteiro (10 dias), envolve mudanças no prompt do `ChatCopilotoAgent`.

### Não-decisões (deliberadamente fora)

- **Sleep-time agents estilo Letta**: revisitar quando Letta atingir GA estável. Hoje seria reinventar antes de validar.
- **Knowledge graph completo (Zep/Cognee)**: frontmatter `supersedes/superseded_by` + `mcp_memory_documents.related` já dá grafo leve. Investir em graph DB completo é prematuro.
- **Substituir Meilisearch por Mem0/Letta**: ADR 0036 já lista 5 triggers concretos pra reavaliar. Nenhum disparou.
- **Adotar OpenClaw como framework**: estamos mais maduros que ele em governança/multi-tenant. Útil como inspiração; não como dependência.

## Justificativa

**Sequência P0 → P3 é por reversibilidade × dor:**

- P0 destrava o **uso pelo time** (Wagner pediu literalmente). Reusa infra existente. Reversível.
- P1 ataca o **gap mensurável mais doloroso** (knowledge updates) com mudança cirúrgica. Reversível via migration de rollback.
- P2 e P3 dependem de **sinal medido** (LongMemEval-PT). Fazer antes = chutar threshold. ADR aceita explicitamente que P2/P3 podem mudar de forma quando o sinal chegar.

**Por que NÃO seguir os 8 pilares descritos pelo cliente literalmente:** 4 dos 8 já estão cobertos (governance, hybrid, file-based, multi-scope). Os outros 4 (separação de tipos, pipeline consolidação, score, action-aware) viram P1-P3 em sequência mensurável — não bloco único. Implementar 8 frentes paralelas é receita de drift.

**Por que NÃO copiar Mem0/Letta diretamente:** nossa multi-tenancy `business_id` + LGPD + integração com 18 ADRs do Copiloto é mais rígida que o defaults deles. Reusar primitivas (temporal validity, scoring), não a stack.

## Consequências

**Positivas:**
- Time MCP completo após P0 (3 tools novas + 2 entidades governadas).
- Capacidade de responder "qual era o faturamento em 2026-Q1" com correção temporal após P1.
- ADR 0036 fica reforçado: Meilisearch + governance vence stack monolítica de mercado em multi-tenant LGPD.
- Cada movimento testável independente (anti-monolito).

**Negativas / Trade-offs:**
- 4 ADRs de implementação adicionais a escrever (uma por P).
- Schema `copiloto_memoria_facts` cresce 4 colunas (P1+P2). Acceptable — append-only mantém auditoria.
- Risco de over-engineering em P3 se P1/P2 não mostrarem ganho mensurável. Mitigação: review trigger explícito (Recall@5 ≥ 0.85 antes de P3).

**Riscos mitigados:**
- Não vira "rewrite memória do zero". Cada P é mudança cirúrgica.
- Não compromete `business_id` scope (multi-tenant patterns mantidos).
- Não toca PolicyEngine ADS firewall (só espelha em `mcp_policies` pra leitura governada).

## Como Wagner deveria ter perguntado

Pra evitar mistura de escopo na próxima (este ADR existe porque "criar 2 skills + testes" foi misturado com "amadurecer toda memória" no mesmo turno):

```
[Contexto] Li/descobri X (link)
[Estado atual] O que JÁ TEMOS — Claude valida contra repo
[Hipótese] O que ACHO que falta
[Pedido] (a) Validar / (b) Comparar / (c) Decidir / (d) Implementar
         + escopo: ADR-proposta? Sprint? Task única?
[Restrições] Custo, prazo, quem aprova, cycle ativo?
```

## Próximos passos (não-decisões deste ADR)

- ADR 0073 (P0): especificar `mcp_skills` + `mcp_policies` schema + tools MCP
- ADR 0074 (P1): especificar temporal validity em `copiloto_memoria_facts` + tool `memoria-historica`
- ADR 0075 (P2): especificar score por-memória — DEPOIS de golden set LongMemEval-PT
- ADR 0076 (P3): action-aware retrieval — DEPOIS de gate Recall@5 ≥ 0.85

Cada ADR de implementação vira `cycles-create` separado. Não fazer tudo no mesmo cycle — perde foco.

## Erratum — 2026-05-05 (mesmo dia, levantamento exaustivo)

Wagner pediu "estude o que já foi construído e compare" antes de seguir com 0075/0076. Levantamento revelou que a ADR 0072 ficou **datada em 4 pontos**:

1. **P2 não está bloqueada por golden set inexistente.** A tabela [`copiloto_memoria_gabarito`](../../Modules/Copiloto/Database/Migrations/2026_04_29_200001_create_copiloto_memoria_gabarito_table.php) **já existe** (migration 2026-04-29) e o [`MemoriaGabaritoSeeder`](../../Modules/Copiloto/Database/Seeders/MemoriaGabaritoSeeder.php) **já popula 50 perguntas Larissa-style**. MEM-MET-5 está parcial/feito, não pendente.

2. **Baseline real medido:** Recall@3 = **0.125** em prod (2026-04-29). Distância pro gate de P3 (Recall@5 ≥ 0.85) é gigante. **Diagnóstico:** corpus de apenas 6 fatos. Problema não é retrieval — é memória vazia. Implica: antes de medir P2, é preciso popular memória (ExtrairFatosAgent rodando regular em conversas de Larissa).

3. **ADR 0054 não foi citada e é relevante.** [ADR 0054 — Pacote enterprise de busca de memória](0054-pacote-enterprise-busca-memoria-evolucao.md) formaliza Camadas A/B/C: Camada A (SemanticCache + ConversationSummarizer) ~50% wired; Camada B ([HydeQueryExpander](../../Modules/Copiloto/Services/Memoria/HydeQueryExpander.php) + [LlmReranker](../../Modules/Copiloto/Services/Memoria/LlmReranker.php)) código pronto + wiring pendente. **Boa parte do que P3 precisa já existe** — só não está conectado no pipeline.

4. **Estimativas dos movimentos desatualizadas:**
   - P0: 5 dias — **mantém** (0% implementado, mas trabalho real).
   - P1: 3 dias — **revisão: 1.5–2 dias**. `valid_from`/`valid_until` + `hits_count` + `core_memory` + filtro supersedence + append-only no [`MeilisearchDriver:110`](../../Modules/Copiloto/Services/Memoria/MeilisearchDriver.php) **já em prod** (descoberta confirmada — ver erratum em ADR 0074).
   - P2: 5 dias — **mantém**, mas **gate revisado**: depende de popular memória primeiro, não só do golden set.
   - P3: 10 dias — **revisão: 5–6 dias**. Camada B pronta + ProfileDistiller já faz quase meta-memory.

**O que NÃO muda:** 4 movimentos sequenciais P0→P3, ordem mantida. Cada P em ADR de implementação separado. Princípios canônicos preservados.

**O que muda na prática:** P0 vira o único que precisa coding novo significativo. P1 é mudança cirúrgica. P2 vira "calibração + popular corpus". P3 é "wiring de pedaços já existentes". Custo total caiu de **~28 dias** estimado em 2026-05-05 manhã para **~12-15 dias úteis** após levantamento.

**Status do ADR 0072:** mantido em `proposto`. Decisão central (sequenciamento P0→P3 + gates) sobrevive. Dados foram corrigidos.

## Erratum 2 — 2026-05-05 (mesmo dia, pesquisa de UI)

Wagner pediu UI rica pra gerenciar skills (versionamento DB+git + governance + history + rationale + testes inline). Pesquisa exaustiva ([cofre `prompt_skill_management_2026_05_05.md`](../comparativos/prompt_skill_management_2026_05_05.md)) cobriu 10 ferramentas de prompt management.

**Impacto no roadmap P0:**
- ADR 0073 ficou pequena demais pro pedido real → ADR 0075 supersede 0073 com 5 tabelas + 5 telas + approval workflow + rationale estruturado + test runner. **NÃO existe equivalente no mercado** — fica à frente do estado-da-arte 2026.
- `mcp_policies` (espelho do PolicyEngine) saiu de escopo P0. Vira ADR separada futura.
- Estimativa P0 revisada: 5 dias → 15 dias úteis (ou 7d com paralelismo). Aumento justificado pelo escopo expandido (UI completa em vez de só backend).

**P1/P2/P3 não mudam** — sequenciamento e gates preservados.

## Erratum 3 — 2026-05-05 (mesmo dia, inversão de fluxo)

Wagner pediu **inverter primary**: DB é fonte autoritativa, git é destino auditável (não git → DB). Granularidade por-skill: ele decide quais aceitam drift automático e quais exigem revisão.

**[ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md) supersede 0075** com:
- DB primary (UI edita direto, sem PR a cada experimento)
- Git destino: ação "Publish to git" separada do approve (ou auto via flag `auto_publish_to_git`)
- Drift detection por-skill via `git_sync_mode` ENUM(auto/manual/pinned) — Wagner controla onde tem fricção
- Skills criadas via UI são dinâmicas (origin=created)
- 6 tabelas (5 do 0075 + `mcp_skill_drift_alerts`), 6 telas (5 + drift queue), 4 services novos

**ADR 0061 (zero auto-mem privada / git-first canônico) preservado:** continua valendo pra ADRs/sessions/runbooks/comparativos. Skills do Claude Code são artefatos operacionais com lifecycle próprio (escapam ADR 0061 explicitamente).

Estimativa P0 mantida em ~15 dias úteis (escopo similar ao 0075, só mudou direção do fluxo).

## Referências

- [State of Agent Memory 2026 — Mem0](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [Letta v1 agent loop](https://www.letta.com/blog/letta-v1-agent)
- [Zep / Graphiti paper arXiv 2501.13956](https://arxiv.org/abs/2501.13956)
- [A-Mem paper arXiv 2502.12110](https://arxiv.org/abs/2502.12110) · [GitHub](https://github.com/agiresearch/A-mem)
- [LongMemEval paper arXiv 2410.10813](https://arxiv.org/abs/2410.10813)
- [Anthropic Memory Tool](https://platform.claude.com/docs/en/agents-and-tools/tool-use/memory-tool)
- [OpenClaw oficial](https://openclaw.ai/) · [Docs memory](https://docs.openclaw.ai/concepts/memory)
- [Cognee architecture](https://www.cognee.ai/blog/fundamentals/how-cognee-builds-ai-memory)
- [Best AI Agent Memory Systems 2026 (Vectorize)](https://vectorize.io/articles/best-ai-agent-memory-systems)
- ADRs internos: 0035, 0036, 0037, 0049, 0050, 0051, 0052, 0053, 0061 (ver frontmatter `related`)
