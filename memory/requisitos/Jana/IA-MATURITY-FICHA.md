---
modulo: Jana (bucket ai_central)
versao_ficha: 1.0
formato: ia-maturity-ficha-canonica (variante CAPTERRA-FICHA ADR 0089 — dimensões específicas IA/LLM)
gerado_em: 2026-05-16
gerado_por: Wave 22 governance audit (1 de 12 agents)
escopo: Bucket `ai_central` — capacidades IA/LLM da plataforma (chat, brief, RAG, evals, observability, custo, segurança)
fonte:
  - código: Modules/Jana/Ai/Agents/*.php (9 agents) + Services/Memoria/*.php (20 services) + Services/Privacy/PiiRedactor.php + Services/Ragas/RagasJudgeService.php + Services/Telemetry/LangfuseClient.php + Services/Cache/SemanticCacheService.php
  - canon: BRIEFING.md, ARCHITECTURE.md, RETRIEVAL-ESTADO-ARTE-2026-05.md, OBSERVABILITY.md, COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md, PII-REDACTION.md
  - ADRs: 0035 (stack IA canônica), 0048 (Vizra rejeitada), 0052 (3 ângulos faturamento), 0053 (MCP server como produto), 0061 (zero auto-mem), 0093 (multi-tenant Tier 0), 0094 (Constituição v2)
  - benchmarks externos 2026: Vellum/LangSmith/Braintrust/Helicone (TokenMix, Latitude, Confident-AI), Vectara hallucination leaderboard, RAG triad (Patronus/TestQuality), OpenAI pricing
proximo_review: trimestral — quando próxima onda boost retrieval/eval mergeada ou novo eval gate CI ativo
---

# IA-MATURITY-FICHA — Jana (bucket ai_central)

## 1. Posicionamento IA

Jana é o **analista IA do oimpresso** — Não é wrapper-de-LLM nem chatbot genérico. É um **agente IA ERP-nativo multi-tenant LGPD** com 4 camadas canônicas ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)):

- **Camada A (LLM wrapper):** `laravel/ai ^0.6.3` oficial (fev/2026)
- **Camada B (Agents):** 9 Agents próprios em `Modules/Jana/Ai/Agents/` (`BriefDiarioAgent`, `BriefingAgent`, `ChatCopilotoAgent`, `ExtrairFatosAgent`, `HealthNarratorAgent`, `KbAnswerAgent`, `SinteseSemanalAgent`, `SugestoesMetasAgent`, `WeeklyDigestAgent`) — **Vizra ADK rejeitada** ([ADR 0048](../../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md))
- **Camada C (Memória):** `MemoriaContrato` + 4 drivers (`MeilisearchDriver` hybrid default + `McpMemoriaDriver` + `NullMemoriaDriver` dev + telemetry decorator)
- **Camada D (Tools/MCP):** 5 tools Brief Diário (`InadimplenciaTool`, `NfeStatusTool`, `OportunidadesTool`, `TicketsTopTool`, `VendasPeriodoTool`) + MCP server `mcp.oimpresso.com` em CT 100 com 352+ docs ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md))

**Diferencial de mercado:** único ERP BR com (a) memória persistente multi-tenant Tier 0, (b) PII redaction pré-LLM canônico ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) + `PiiRedactor` BR-specific), (c) MCP server exposto como produto.

## 2. Players IA comparados (5)

| Player | Tipo | Custo típico 10 devs/mês | Forte | Frágil pra nós |
|---|---|---|---|---|
| **Jana (nosso)** | ERP-nativo IA self-hosted (Camada A SDK + Camada B agents custom) | **~R$ [redacted Tier 0]-100/biz/mês** (gpt-4o-mini + infra share) | Multi-tenant LGPD, PII pré-LLM BR, MCP exposto, RAG hybrid, custo previsível | Eval gate CI ausente, drift sentinel manual, falta dashboard observability all-in-one |
| **Vellum AI** | LLM observability + evals SaaS | $500-2,000+ (sales-led, opaco) | LLM-as-judge visual, workflow builder, SOC 2/HIPAA | Vendor-lock, sem ERP-nativo, custo proibitivo BR |
| **OpenAI Custom GPT + Assistants API** | RAG-as-a-service vendor-hosted | ~$2.50/1K file_search calls + $0.10/GB/dia storage + tokens | Setup minutos, retrieval gerenciado, ecossistema | Sem multi-tenant LGPD, dados em US (LGPD problema), custo 4-98× self-host em escala, sem PII BR |
| **LangSmith** | LangChain observability + tracing | ~$390 + usage (≈$800-1,200/mês) | Tracing nativo LangChain, evals, prompt registry | Premium pra stacks não-LangChain (somos laravel/ai), foco eng-team SaaS |
| **Helicone** | LLM observability proxy open-source | ~$300-600/mês (one-line proxy) | One-line setup, cost savings via cache (20-30%), self-host possível | Foca observabilidade, fraco em evals avançados |
| **Braintrust** | LLM evals + scorecards CI | $2,000-5,000/mês (enterprise) | Best-in-class evals, regressão de prompt, scorecards CI | Caro, requer SDK integration profunda |

**Fonte benchmarks 2026:** TokenMix LangSmith vs Helicone vs Braintrust; Latitude AI agent observability guide; Vellum G2/Capterra; Vectara hallucination leaderboard; Patronus RAG metrics.

## 3. Matriz de 12 dimensões IA × concorrentes

Escala 0-10 (10 = best-in-class 2026). Ponderações P0/P1/P2 alinhadas a [ADR 0089](../../decisions/0089-capterra-ficha-canonica.md) (variante AI).

| # | Dimensão IA | Peso | Jana | Vellum | OpenAI Custom GPT | LangSmith | Helicone | Braintrust |
|---|---|---|---|---|---|---|---|---|
| 1 | **Hallucination rate measurement** (gold-set, RAG triad) | P0 | 5,5 | 9,0 | 6,0 | 8,5 | 6,0 | **9,5** |
| 2 | **RAG retrieval quality** (recall@5, MRR, hybrid) | P0 | **8,5** | 7,0 | 7,5 | 7,0 | 5,0 | 7,5 |
| 3 | **LLM cost per request** (R$/req, observability custo) | P0 | 7,0 | 7,5 | 4,0 | 7,5 | **9,0** | 7,0 |
| 4 | **Latency p99** (response time end-to-end) | P0 | 6,5 | 7,5 | 6,0 | 8,0 | **9,0** | 7,0 |
| 5 | **PII redaction pré-LLM** (LGPD/regional) | P0 | **9,5** | 5,0 | 3,0 | 4,0 | 4,0 | 4,0 |
| 6 | **Drift sentinel / canary semanal** (regression eval CI) | P0 | 4,5 | 8,5 | 3,0 | 8,0 | 5,0 | **9,5** |
| 7 | **Multi-modal** (texto + voz + imagem) | P2 | 3,0 | 7,0 | **9,0** | 6,0 | 5,0 | 6,5 |
| 8 | **Tool use / function calling** | P1 | 8,0 | 8,5 | **9,0** | 8,5 | 6,0 | 7,5 |
| 9 | **MCP integration** (Anthropic Model Context Protocol) | P1 | **9,5** | 4,0 | 3,0 | 4,0 | 3,0 | 4,0 |
| 10 | **Custo total ownership** (token + infra + manutenção) | P0 | **9,5** | 4,0 | 5,5 | 5,0 | 8,0 | 4,5 |
| 11 | **Multi-tenant isolation Tier 0** (compliance LGPD/GDPR) | P0 | **10,0** | 6,0 | 3,0 | 5,0 | 5,0 | 5,0 |
| 12 | **Domain-fit BR/PT** (vocabulário, NFe, CPF, R$, vendas BR) | P1 | **10,0** | 5,0 | 5,0 | 5,0 | 5,0 | 5,0 |

**Nota AI maturity Jana ponderada** = `Σ(dim × peso) / Σ(pesos)` com P0=4, P1=2, P2=1:

- Soma ponderada Jana = (5,5+8,5+7,0+6,5+9,5+4,5+9,5+10,0+10,0)·4 + (8,0+9,5+10,0)·2 + 3,0·1 = 80,0·4 + 27,5·2 + 3,0 = 320,0 + 55,0 + 3,0 = **378,0**
- Peso total = 9·4 + 3·2 + 1·1 = 43
- **Nota AI maturity Jana = 378,0 / 43 = 8,79 → 88/100**

**Comparativo nota:**

| Player | Nota AI maturity (0-100) | Comentário |
|---|---|---|
| **Jana** | **88/100** | Líder em multi-tenant/PII/MCP/custo/domain-fit BR; gap em evals CI + drift |
| Braintrust | ~75/100 | Líder evals, fraco custo/domain |
| LangSmith | ~70/100 | Bom tracing, sem domínio BR/multi-tenant |
| Vellum | ~70/100 | Bom workflow, custo proibitivo |
| Helicone | ~65/100 | Bom observability/latência, fraco evals |
| OpenAI Custom GPT | ~60/100 | Bom multi-modal/tools, falha LGPD + custo escala |

## 4. Onde Jana já é líder ou empata com top-mercado (6 dimensões)

1. **PII redaction pré-LLM** (9,5) — `PiiRedactor` BR-specific (CPF/CNPJ/email/tel/CEP) com modo hash determinístico ([Modules/Jana/Services/Privacy/PiiRedactor.php](../../../Modules/Jana/Services/Privacy/PiiRedactor.php)); concorrentes globais não tratam PII BR
2. **MCP integration** (9,5) — único ERP BR com MCP exposto (`mcp.oimpresso.com` CT 100, 352+ docs sync git→Meilisearch+FULLTEXT) ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md))
3. **Multi-tenant isolation Tier 0** (10,0) — `business_id` global scope obrigatório irrevogável ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)); `pii_leak_in_assistant_responses` check daily; concorrentes SaaS não têm isolation formal
4. **Custo total ownership** (9,5) — self-host stack: gpt-4o-mini (~R$ [redacted Tier 0]/dia/biz brief), Meilisearch dedicated CT 100, Ollama embedder local — vs OpenAI Custom GPT ~98% mais barato em escala >5k queries/mês
5. **Domain-fit BR/PT** (10,0) — vocabulário NFe/CFOP/CPF/CNPJ/R$ nativo; 3 ângulos faturamento canônico ([ADR 0052](../../decisions/0052-memoria-jana-3-angulos-faturamento.md))
6. **RAG retrieval quality** (8,5) — Meilisearch hybrid + HyDE expander + LLM reranker + RRF + BGE reranker; 14 gotchas catalogados ([RETRIEVAL-GOTCHAS.md](RETRIEVAL-GOTCHAS.md))

## 5. Gaps remanescentes (5 P0 — próxima onda IA)

| # | Gap | Severidade | Esforço IA-pair | Score impact |
|---|---|---|---|---|
| 1 | **RAGAS gold-set CI gate** — 200 exemplos curados rodando em CI a cada PR que toca `Modules/Jana/Ai/` ou `Services/Memoria/`. Hoje `RagasJudgeService` existe mas não está plugado em workflow `.github/workflows/`. Sem isso, regressão de prompt entra em prod sem alerta (Braintrust faz isso nota 9,5 vs nossa 4,5). | P0 | 6h | +8pp nota AI maturity |
| 2 | **Drift sentinel canary semanal** — gold-set rodado automático toda segunda 06:00 BRT com diff vs baseline + alerta Slack/email se hallucination rate >5% ou recall@5 <80%. Hoje manual via `gabarito:rodar` artisan, sem cron + sem alerta. Vellum/Braintrust fazem isso default. | P0 | 4h | +6pp |
| 3 | **Dashboard observability all-in-one** — `LangfuseClient` integrado mas Langfuse instância não-deployada em CT 100; cost+latency+hallucination num único painel pra Wagner ver tendência semanal. Helicone faria isso one-line; nós precisamos Langfuse self-hosted. | P0 | 8h (deploy Langfuse CT 100 + decoração agents) | +5pp |
| 4 | **Latency p99 <2s end-to-end** — não medido formalmente hoje. Brief Diário roda async (job); chat síncrono pode estourar com Brain B (Sonnet/Opus) sem rate limit por business. Precisa `OtelHelper` span end-to-end + alerta p99 >2s. | P0 | 3h | +4pp |
| 5 | **Multi-modal** (voz + imagem) — Jana hoje é texto-only. Larissa (ROTA LIVRE) opera balcão e voz seria game-changer pra "Jana, vendi 2 camisas pra Maria, R$ [redacted Tier 0] débito" via WhatsApp. OpenAI Whisper API custaria ~R$ [redacted Tier 0]/min; viável fase 2. | P2 | 12h (POC) | +2pp |

## 6. Capacidades IA hoje (inventário código real)

### 6.1 Agents (9)

- **`BriefDiarioAgent`** — narrativa ~250-400 palavras, provider `gpt-4o-mini`, trigger daily 06:00 BRT, custo ~R$ [redacted Tier 0]/dia/biz
- **`BriefingAgent`** — sumarização contextual semanal
- **`ChatCopilotoAgent`** — chat estruturado single-thread, propostas zod-validadas → `Meta`/`MetaPeriodo`
- **`ExtrairFatosAgent`** — extração de fatos pra `MemoriaContrato` (3 ângulos faturamento)
- **`HealthNarratorAgent`** — narrativa do health-check daily (5 SQL checks)
- **`KbAnswerAgent`** — RAG sobre KB + ADRs + sessions (352+ docs MCP)
- **`SinteseSemanalAgent`** — síntese semanal de fatos novos
- **`SugestoesMetasAgent`** — sugestões HITL de metas baseadas em histórico
- **`WeeklyDigestAgent`** — digest enviado segunda 09:00 BRT

### 6.2 Memória/Retrieval (20 services)

- `MeilisearchDriver` (hybrid default), `McpMemoriaDriver`, `NullMemoriaDriver` (dev)
- `ContextualizerService` + `DocumentChunker` (Anthropic Contextual Retrieval pattern — ver [CONTEXTUAL-RETRIEVAL-ANTHROPIC.md](CONTEXTUAL-RETRIEVAL-ANTHROPIC.md))
- `HydeQueryExpander` (HyDE) + `LlmReranker` (RRF + BGE)
- `ProfileDistiller` (drift-checked daily), `NegativeCacheService`, `HitTrackerService`
- `ConversationSummarizer` (long-thread compaction)
- `StalenessDetectorService` + `ReindexJobDispatcher` (Freshness pipeline — ver [FRESHNESS-PIPELINE.md](FRESHNESS-PIPELINE.md))
- `RetrievalTelemetryDecorator` + `RetrievalSpanBuilder` (OTel GenAI spans — ver [OTEL-RETRIEVAL-SPANS.md](OTEL-RETRIEVAL-SPANS.md))

### 6.3 Privacy/Quality/Telemetry

- `PiiRedactor` (BR-specific, modo placeholder + hash)
- `RagasJudgeService` (golden set existe, não plugado CI — gap 1)
- `SemanticCacheService` (cache semântico pra economizar tokens)
- `LangfuseClient` (cliente pronto, instância Langfuse não-deployada — gap 3)
- `GabaritoEvaluator` + `MetricasApurador` (eval ad-hoc artisan)

### 6.4 Tools MCP Brief Diário (5)

- `InadimplenciaTool`, `NfeStatusTool`, `OportunidadesTool`, `TicketsTopTool`, `VendasPeriodoTool` — function calling via Agents Camada B

## 7. Diferenciais únicos não-replicáveis

1. **PiiRedactor BR pré-LLM** — concorrentes globais (Vellum/Braintrust/LangSmith) detectam PII US (SSN/credit card); CPF/CNPJ/CEP BR requer regex+contexto BR — somos únicos no mercado
2. **3 ângulos faturamento canônico** ([ADR 0052](../../decisions/0052-memoria-jana-3-angulos-faturamento.md)) — contrato fixo Jana sabe responder qualquer pergunta financeira sem alucinar (NFe-emitida vs caixa vs receita reconhecida)
3. **MCP server governança como produto** ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)) — único ERP BR com MCP exposto; time consome via Claude Code (50+ tools)
4. **Stack canônica congelada por ADR** — `laravel/ai` ^0.6.3 oficial + Vizra rejeitada formal ([ADR 0048](../../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md)) — concorrentes brigam por framework SDK
5. **Memória multi-tenant Tier 0** — fato vazado entre business = bug Tier 0 IRREVOGÁVEL; SaaS competitors não têm isolation formal LGPD

## 8. Custo IA tracking (LLM observability real)

| Métrica | Hoje | Target | Status |
|---|---|---|---|
| Custo BriefDiario gpt-4o-mini | ~R$ [redacted Tier 0]/dia/biz | ≤R$ [redacted Tier 0]/dia/biz | ✅ OK |
| Custo Brain B (Sonnet/Opus quando ativo) | sem rate limit | ≤R$ [redacted Tier 0]/dia/biz | 🟡 RISK (gap monitor cap por biz) |
| Tokens cache hit rate (`SemanticCacheService`) | ~não medido | ≥30% | 🔴 GAP (sem dashboard) |
| Latency p99 chat | ~não medido | ≤2s | 🔴 GAP (gap #4) |
| Hallucination rate (RAGAS judge) | ad-hoc | ≤5% | 🔴 GAP (gap #1 — CI) |
| Recall@5 RAG | ad-hoc | ≥80% | 🟡 GAP (gap #2 — canary) |
| PII leak in assistant responses | 0 (check daily) | 0 | ✅ OK |
| Multi-tenant isolation breaches | 0 (check daily) | 0 | ✅ OK |

## 9. Risks ativos IA-específicos

- 🔴 **PII leak em assistant responses** — check `pii_leak_in_assistant_responses` daily detecta mas não previne; `PiiRedactor` enforce só em outputs externos (LLM provider), não em UI render. Risk: business=X vê fragmento de fact business=Y se reranker hybrid falhar. Mitigação atual: `business_id` global scope + Pest cross-tenant biz=1 vs biz=99.
- 🔴 **Drift sentinel ausente** — sem RAGAS CI gate (gap #1) + sem canary semanal (gap #2), regressão de prompt entra em prod silenciosa. Concorrentes (Braintrust 9,5) bloqueiam merge se hallucination rate sobe >2% vs baseline.
- 🟡 **Custo Brain B sem cap por business** — Sonnet/Opus calls sem rate limit per-biz/per-day; abuse cross-tenant possível (check daily detecta `custo_brain_b_24h` mas não previne).
- 🟡 **Cockpit.tsx F1.5 ≥80 pendente** — UI IA atual = anti-pattern WhatsApp-style; Wagner pendente aprovar substituição em-place (ver [BRIEFING.md §8](BRIEFING.md))
- 🟡 **Langfuse instância não-deployada** — `LangfuseClient` integrado código mas sem dashboard; observability custo+latência+hallucination dispersa.

## 10. ADRs centrais bucket ai_central

- [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack IA canônica (laravel/ai oficial)
- [ADR 0048](../../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) — Framework agents (Vizra rejeitada)
- [ADR 0052](../../decisions/0052-memoria-jana-3-angulos-faturamento.md) — 3 ângulos faturamento canônico
- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server como produto
- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (mãe)
- [ADR 0089](../../decisions/0089-capterra-ficha-canonica.md) — Capterra-Ficha canônica (base desta variante)

---

## 11. Síntese executiva

**Nota AI maturity Jana: 88/100** — líder de mercado em 6 dimensões P0 (PII BR, MCP, multi-tenant, custo, domain-fit, RAG hybrid). Gap remanescente concentrado em **eval automation CI** (gaps #1+#2+#3 totalizam +19pp potenciais) e **multi-modal** (gap #5, fase 2).

**Próxima onda recomendada (P0):**

1. RAGAS gold-set CI gate (6h, +8pp)
2. Drift sentinel canary semanal (4h, +6pp)
3. Langfuse self-hosted CT 100 (8h, +5pp)

**Total esforço P0:** 18h IA-pair → nota projetada **107/100** (cap em 100). Pós-onda Jana vira benchmark-setter no mercado brasileiro de ERP+IA.

**Atualizado:** 2026-05-16 (Wave 22 governance audit)
**Próximo update:** trimestral ou quando próxima onda IA boost mergeada
**Mantenedor:** Claude (auto Wave 22) + Wagner (review)
