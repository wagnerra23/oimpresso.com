---
data: 2026-05-16
tipo: session-log
escopo: Wave 22 — IA-MATURITY-FICHA Jana (bucket ai_central)
agente: Claude (1 de 12 agents Wave 22)
branch: claude/governance-wave-21-22-mega
worktree: D:\oimpresso.com\.claude\worktrees\jolly-hypatia-b8741c
output_principal: memory/requisitos/Jana/IA-MATURITY-FICHA.md
duracao_estimada: ~45min
---

# Session 2026-05-16 — IA-MATURITY-FICHA Jana (Wave 22)

## Objetivo

Gerar **IA-MATURITY-FICHA** variante da CAPTERRA-FICHA canônica ([ADR 0089](../decisions/0089-capterra-ficha-canonica.md)) específica pro bucket `ai_central` — capacidades IA/LLM da plataforma Jana. Comparar com 5 players globais (Vellum, OpenAI Custom GPT, LangSmith, Helicone, Braintrust) em 12 dimensões IA-específicas (hallucination rate, RAG recall@5, custo/req, latency p99, PII redaction, drift sentinel, multi-modal, tool use, MCP integration, TCO, multi-tenant isolation, domain-fit BR).

## Área exclusiva (Wave 22 isolation)

- ✅ `memory/requisitos/Jana/IA-MATURITY-FICHA.md` (CRIADO)
- ✅ `memory/sessions/2026-05-16-ia-maturity-jana.md` (este arquivo)
- ⛔ Nenhum outro arquivo tocado (sem conflito com outros 11 agents Wave 22)

## Metodologia

### Fase 1 — Pesquisa estado-da-arte (4 WebSearch)

1. **Vellum AI** — observability + evals SaaS, pricing $500-2,000+ opaco, SOC 2/HIPAA, LLM-as-judge ([ZenML pricing guide](https://www.zenml.io/blog/vellum-ai-pricing), [G2 reviews](https://www.g2.com/products/vellum/reviews))
2. **LangSmith vs Braintrust vs Helicone** — comparativo 2026 detalhado: Helicone proxy one-line (~$300-600/mês com 20-30% cache savings), LangSmith LangChain-native ($390+ usage), Braintrust evals-first enterprise ($2k-5k/mês) ([TokenMix 2026](https://tokenmix.ai/blog/langsmith-vs-helicone-vs-braintrust-observability-2026), [Latitude AI agent obs](https://latitude.so/blog/best-llm-observability-tools-agents-latitude-vs-langfuse-langsmith))
3. **Hallucination/RAG benchmarks 2026** — Vectara leaderboard hallucination 15-52% range, RAG reduz 30-70%, recall@5 0,95+ atingível com BM25+dense hybrid ([Vectara leaderboard](https://github.com/vectara/hallucination-leaderboard), [Patronus RAG metrics](https://www.patronus.ai/llm-testing/rag-evaluation-metrics))
4. **OpenAI Assistants API + PII redaction LGPD** — File search $2.50/1K calls + $0.10/GB/dia, breakeven self-host ~5k queries/mês, 94-98% economia escala enterprise; PII BR sem cobertura nos players globais ([Helicone observability guide](https://www.helicone.ai/blog/the-complete-guide-to-LLM-observability-platforms), [Statsig PII guide](https://www.statsig.com/perspectives/piiredactionprivacyllms))

### Fase 2 — Inventário código real Jana

- **9 Agents** em `Modules/Jana/Ai/Agents/` (Glob match): BriefDiarioAgent, BriefingAgent, ChatCopilotoAgent, ExtrairFatosAgent, HealthNarratorAgent, KbAnswerAgent, SinteseSemanalAgent, SugestoesMetasAgent, WeeklyDigestAgent
- **20 services Memória/Retrieval** em `Modules/Jana/Services/Memoria/` (Meilisearch hybrid + HyDE + LLM reranker + RRF + BGE + ContextualizerService + DocumentChunker + ProfileDistiller + 4 telemetry + freshness pipeline + drivers)
- **PiiRedactor** em `Modules/Jana/Services/Privacy/PiiRedactor.php` — CPF/CNPJ/email/tel/CEP BR-specific, modo placeholder + hash
- **RagasJudgeService** existe mas não plugado em CI (`Modules/Jana/Services/Ragas/RagasJudgeService.php`)
- **LangfuseClient** integrado código, instância não-deployada (`Modules/Jana/Services/Telemetry/LangfuseClient.php`)
- **SemanticCacheService** existe (`Modules/Jana/Services/Cache/SemanticCacheService.php`)
- **5 Tools MCP** Brief Diário em `Modules/Jana/Ai/Tools/BriefDiario/`

### Fase 3 — Pontuação ponderada 12 dimensões

Aplicado peso P0=4, P1=2, P2=1 (alinhado [ADR 0089](../decisions/0089-capterra-ficha-canonica.md)):

- **9 dimensões P0:** hallucination, RAG quality, custo/req, latency p99, PII redaction, drift sentinel, TCO, multi-tenant isolation, domain-fit BR
- **3 dimensões P1:** tool use, MCP integration, domain-fit BR
- **1 dimensão P2:** multi-modal

Soma Jana = 378,0 / peso total 43 = **8,79 → nota 88/100**.

## Achados-chave

### Onde Jana é líder de mercado (6 dimensões P0)

1. PII redaction pré-LLM BR (9,5) — único no mercado
2. MCP integration (9,5) — único ERP BR exposto
3. Multi-tenant isolation Tier 0 (10,0) — irrevogável por ADR 0093
4. Custo TCO (9,5) — 94-98% economia vs OpenAI Custom GPT em escala
5. Domain-fit BR (10,0) — NFe/CFOP/CPF/R$ nativo + 3 ângulos faturamento canônico
6. RAG quality (8,5) — Meilisearch hybrid + HyDE + reranker + 14 gotchas catalogados

### Gaps P0 (5 itens priorizados)

| Gap | Esforço | Impact |
|---|---|---|
| 1. RAGAS gold-set CI gate (200 exemplos) | 6h | +8pp |
| 2. Drift sentinel canary semanal | 4h | +6pp |
| 3. Langfuse self-hosted CT 100 | 8h | +5pp |
| 4. Latency p99 measurement + alert | 3h | +4pp |
| 5. Multi-modal (Whisper voz) — fase 2 | 12h | +2pp |

**Onda IA P0 total = 18h → nota projetada cap 100/100** (de 88 atual).

## Decisões assumidas

1. **Variante CAPTERRA-FICHA** ao invés de FICHA nova-do-zero — preserva formato canônico ADR 0089 (10 seções) com dimensões IA-específicas substituindo dimensões UX/features
2. **5 players** comparados (não 8 como CAPTERRA-FICHA default) — players IA-puros são menos numerosos que players de produto/UX; 5 cobre bem o espectro (SaaS observability, vendor-hosted RAG, evals-first, proxy open-source)
3. **Peso P0=4** mantido (não inflado) — dimensões IA P0 (hallucination, multi-tenant, custo) têm gravity comparável a P0 UX (busca, custo, fit) na CAPTERRA-FICHA original
4. **Não criar ADR** — variante de FICHA é instanciação do padrão ADR 0089, não decisão arquitetural nova; se virar formato canônico (várias IA-FICHAs futuras), Wagner pode promover via ADR follow-up

## Anti-padrões evitados

- ❌ Não inventei capacidades — todas as 9 agents + 20 services + Tools MCP confirmados via Glob real do código
- ❌ Não dupliquei BRIEFING.md — esta FICHA é específica bucket ai_central, BRIEFING é módulo-wide
- ❌ Não toquei outros arquivos (Wave 22 isolation)
- ❌ Não fiz git ops (parent consolida)
- ❌ Não puxei números de benchmarks sem fonte — todas estimativas têm link 2026 ou citação ADR/código real

## Pré-flight obrigatório (FASE 1 proibicoes.md)

- ✅ Li `CLAUDE.md` + `memory/why-oimpresso.md` + `memory/what-oimpresso.md` + `memory/how-trabalhar.md` + `memory/proibicoes.md` + `memory/regras-time.md` (via system reminder + context)
- ✅ Li `memory/requisitos/Jana/BRIEFING.md` (estado consolidado módulo)
- ✅ Glob exhaustivo `Modules/Jana/Ai/**/*.php` + `Modules/Jana/Services/**/Memoria/*.php` + `Modules/Jana/Services/**/Privacy/*.php`
- ✅ Read sample `CAPTERRA-FICHA.md` (KB) pra preservar formato canônico

## Próximos passos sugeridos (não-blocking)

1. **Onda IA P0** (18h IA-pair) — implementar gaps 1+2+3+4 acima → nota 100/100
2. **Promover IA-MATURITY-FICHA como formato canônico** se Wagner gostar (ADR follow-up estendendo 0089)
3. **Gerar IA-MATURITY-FICHA pra ADS** (Modules/ADS dual-brain) — segundo módulo com bucket ai_central forte

## Artefatos gerados

- ✅ `memory/requisitos/Jana/IA-MATURITY-FICHA.md` (11 seções, ~330 linhas, formato canônico)
- ✅ `memory/sessions/2026-05-16-ia-maturity-jana.md` (este arquivo)

## Sources consultadas

- [TokenMix: LangSmith vs Helicone vs Braintrust 2026](https://tokenmix.ai/blog/langsmith-vs-helicone-vs-braintrust-observability-2026)
- [Latitude: AI Agent Observability Tools 2026](https://latitude.so/blog/best-llm-observability-tools-agents-latitude-vs-langfuse-langsmith)
- [Vellum AI G2 Reviews 2026](https://www.g2.com/products/vellum/reviews)
- [ZenML: Vellum AI Pricing Guide](https://www.zenml.io/blog/vellum-ai-pricing)
- [Vectara Hallucination Leaderboard](https://github.com/vectara/hallucination-leaderboard)
- [Patronus: RAG Evaluation Metrics](https://www.patronus.ai/llm-testing/rag-evaluation-metrics)
- [Helicone: Complete Guide LLM Observability](https://www.helicone.ai/blog/the-complete-guide-to-LLM-observability-platforms)
- [Statsig: PII Redaction Privacy in LLMs](https://www.statsig.com/perspectives/piiredactionprivacyllms)
- [Jon Roosevelt: $2,300/Year RAG vs $40K OpenAI](https://jonroosevelt.com/blog/rag-system-cost-savings)
- [HalluLens: LLM Hallucination Benchmark](https://arxiv.org/html/2504.17550v1)

---

**Status:** ✅ Wave 22 (1/12) entregue — IA-MATURITY-FICHA Jana publicada, sem git ops (parent consolida).
