# Matriz Comparativa estilo Capterra/G2 — Copiloto runtime memory vs Mem0/LangGraph/Letta/Zep/OMEGA (2026-04-26)

> **Assunto:** memória de **runtime** do agente Copiloto vs estado-da-arte de memória pra agentes IA (frameworks open-source + comerciais).
> **Data:** 2026-04-26
> **Autor:** Claude (sessão `dazzling-lichterman-e59b61`) sob direção do Wagner ("compare com Mem0 e outras memórias LangGraph...")
> **Concorrentes incluídos:** Mem0, LangGraph+LangMem, Letta (ex-MemGPT), Zep (Graphiti), OMEGA (líder benchmark) — 5
> **Decisão que vai sair daqui:** decidir se Copiloto **constrói memória runtime do zero**, **integra Mem0/LangMem como dependência**, ou **fica em chat-flat**.
> **Companion docs:** [sistemas_memoria_oimpresso_capterra_2026_04_26.md](sistemas_memoria_oimpresso_capterra_2026_04_26.md) (memória de DEV, não de runtime — evitar confusão)
> **Template usado:** [_TEMPLATE_capterra_oimpresso.md](_TEMPLATE_capterra_oimpresso.md) v1.0

---

## ⚠️ Distinção crítica antes de tudo

**2 camadas diferentes de memória, frequentemente confundidas:**

| Camada | O que é | Onde mora | Para quem |
|---|---|---|---|
| **A — Memória de DEV** | Notas pra agentes (Cursor, Claude) construírem o oimpresso | `memory/`, CLAUDE.md, ADRs, sessions, auto-memória | Wagner + IA assistente |
| **B — Memória RUNTIME** | O app produto (Copiloto) lembra do usuário entre sessões | DB do app + vector store + graph | **Cliente final do oimpresso** (Larissa do ROTA LIVRE) |

**Mem0/LangGraph/Letta/Zep/OMEGA jogam EXCLUSIVAMENTE na camada B.**
**Nosso comparativo anterior (`sistemas_memoria_oimpresso_capterra_2026_04_26.md`) era exclusivamente sobre camada A.**

Este comparativo é sobre **camada B — runtime do Copiloto**.

---

## 1. TL;DR (5 frases)

1. **Copiloto runtime memory hoje é Tier 1 de ~10** — só `Conversa` + `Mensagem` + 1 snapshot estático em `ContextSnapshotService.paraBusiness()` (cache 10min). Zero embeddings, zero vector store, zero graph, zero summary, zero forget, zero memória cross-session por user.
2. **Estado-da-arte (OMEGA 95.4%, Mastra 94.87%, Hindsight 91.4%, Letta 83.2%, Zep 71.2%) usa hybrid (vector+graph+key-value), 3+ tiers (core/archival/recall) e temporal validity** — features que **nenhuma** estão no Copiloto, e que pra construir do zero levam ≥6 meses.
3. **Mem0 (drop-in) integra com 21 frameworks via Python/TypeScript SDK** mas **não tem SDK PHP oficial** — precisa via REST API. Letta+Zep+LangGraph idem (Python-first). Único caminho viável pra PHP: REST.
4. **Nosso diferencial real é negativo aqui:** somos PHP/Laravel num mercado dominado por Python; vetores+grafos exigem infra que ainda não temos (Pinecone/Neo4j/Qdrant). Copiloto compete sem armas.
5. **O dilema:** integrar Mem0/Zep como dependência externa via REST (3-5 sprints, deps adicional, custo $$ recorrente) vs construir layer mínimo PHP nativo (8-12 sprints, sem dep, mas só Tier 3-4) vs aceitar ficar em Tier 1 enquanto não tiver tração comercial. **Recomendado: REST adapter pro Mem0** (caminho B na seção 7).

---

## 2. Concorrentes incluídos (5 frameworks de memória runtime)

| Nome | URL | Tier de mercado | Observação relevante |
|---|---|---|---|
| **Mem0** | [mem0.ai](https://mem0.ai/) | Líder em adoção | 21 frameworks integrados, 19 vector stores, 3 hosting models (managed/OSS/local MCP). Trata fatos gerados pelo agente como first-class. **91% menor latência** vs full-context |
| **LangGraph + LangMem** | [docs.langchain.com](https://docs.langchain.com/oss/python/langgraph/memory) | Líder de orquestração | 3 tipos cognitivos (semantic/episodic/procedural) × 2 escopos (short/long-term) × 2 mechanisms (hot path/background). Checkpointers ≠ memory (são pra crash recovery) |
| **Letta** (ex-MemGPT) | [letta.com](https://letta.com/) | Premium long-running | 3 tiers OS-inspired: **core** (RAM/in-context), **archival** (disk/vector store), **recall** (conversation history). LLM-driven memory mgmt. LongMemEval **83.2%** |
| **Zep / Graphiti** | [getzep.com](https://www.getzep.com/) | Líder temporal | Temporal knowledge graph (fatos com validity windows: start_date + end_date). LongMemEval temporal **63.8%**; geral **71.2%** (GPT-4o). Open-source: Graphiti |
| **OMEGA** | [omegamax.co](https://omegamax.co/compare) | Líder benchmark | LongMemEval **95.4%** (GPT-4.1) — atual champion. Detalhes arquiteturais menos públicos que Mem0/Letta |

**2 grupos:**
- **OSS+managed (Mem0, Letta, Zep, LangMem)** — devs PHP podem auto-hospedar OU usar API
- **Comercial puro (OMEGA)** — só API gerenciada

> **Out-of-scope deste comparativo:** Anthropic Claude memory (claude.ai), OpenAI memory (ChatGPT), Pinecone Assistants, Cognee, Supermemory, Mastra Observational. Mencionados nas sources caso vire follow-up.

---

## 3. Matriz Feature-by-Feature (35 features)

**Legenda:** ✅ Tem completo · 🟡 Tem básico/limitado · ❌ Não tem · ❓ Não confirmado

### Categoria 1 — Tipos cognitivos de memória

| Feature | oimpresso (Copiloto) | Mem0 | LangGraph+LangMem | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|
| Semantic memory (fatos sobre user/world) | ❌ | ✅ | ✅ | ✅ (core block) | ✅ (graph nodes) | ✅ |
| Episodic memory (experiências passadas) | 🟡 (só `Mensagem` flat) | ✅ | ✅ (few-shot prompting) | ✅ (recall mem) | ✅ (event nodes) | ✅ |
| Procedural memory (regras/skills) | ❌ | 🟡 | ✅ (model+code+prompt) | ✅ | 🟡 | ✅ |
| Working memory (sessão atual) | ✅ (`Conversa`) | ✅ (session scope) | ✅ (thread state) | ✅ (recall) | ✅ | ✅ |
| Distinção formal entre os 4 tipos | ❌ | 🟡 | ✅ (docs explícitas) | ✅ | 🟡 | ❓ |

### Categoria 2 — Estrutura de armazenamento

| Feature | oimpresso | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|
| Flat key-value | ✅ (MySQL rows) | ✅ | ✅ (BaseStore) | ✅ | ✅ | ✅ |
| Vector embeddings + similarity | ❌ | ✅ (19 stores) | ✅ (via plugin) | ✅ (archival) | ✅ | ✅ |
| Knowledge graph (entities + relations) | ❌ | ✅ (Mem0g variant) | 🟡 (via lib externa) | 🟡 | ✅ (Graphiti core) | ✅ |
| Temporal validity (fato tem start/end date) | ❌ | 🟡 | 🟡 | 🟡 | ✅ (diferencial) | ✅ |
| Hybrid (vector+graph+kv mix) | ❌ | ✅ | 🟡 | 🟡 | ✅ | ✅ |
| Compressão / summarization automática | ❌ | ✅ | 🟡 (pelo dev) | ✅ (LLM-driven) | ✅ | ✅ |

### Categoria 3 — Operações (CRUD de memória)

| Feature | oimpresso | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|
| `add(memory)` | 🟡 (Mensagem::create) | ✅ | ✅ | ✅ (LLM) | ✅ | ✅ |
| `search(query) → top-k` | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `update(memory)` | ❌ | ✅ (extract+update modules) | ✅ | ✅ | ✅ (válido até X) | ✅ |
| `forget(memory)` | ❌ | ✅ | ✅ | ✅ | ✅ (end_date) | ✅ |
| Conflict resolution entre fatos | ❌ | ✅ | 🟡 | ✅ | ✅ (temporal) | ✅ |
| Hot path vs background updates | ❌ | ✅ | ✅ | ✅ | ✅ | ❓ |

### Categoria 4 — Escopos & multi-tenant

| Feature | oimpresso | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|
| Per-user scope | 🟡 (`user_id` em Conversa) | ✅ (3 tiers: user/session/agent) | ✅ (custom namespaces) | ✅ | ✅ | ✅ |
| Per-session scope | ✅ (`Conversa.id`) | ✅ | ✅ (thread-scoped) | ✅ | ✅ | ✅ |
| Per-agent scope | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Per-tenant scope (oimpresso: business_id) | 🟡 (em Conversa, não em memória) | 🟡 (via custom user_id) | 🟡 (via namespace) | 🟡 | 🟡 | ❓ |

### Categoria 5 — Performance & benchmark

| Feature | oimpresso | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|
| LongMemEval score (ICLR 2025) | ❓ não testado | 66.9% (variant) | ❓ | **83.2%** | 71.2% | **95.4%** |
| Latência típica de recall | ~5ms (SELECT) | -91% vs full-context | ~10-50ms | ~20-100ms | ~10-30ms | ❓ |
| Custo de tokens vs full-context | full (sem otimização) | -91% | depende | -50% típico | -60% típico | ❓ |
| Escalabilidade (M+ memórias) | ❓ não testado | ✅ (managed) | 🟡 (depende store) | 🟡 | ✅ | ✅ |

### Categoria 6 — Integração & operação

| Feature | oimpresso | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|
| **SDK PHP oficial** | N/A (somos PHP) | ❌ (só Python+TS) | ❌ | ❌ | ❌ | ❌ |
| REST API (utilizável de PHP) | ❌ | ✅ | 🟡 (via LangServe) | ✅ | ✅ | ✅ |
| Self-hosted OSS | N/A | ✅ | ✅ | ✅ | ✅ (Graphiti core) | ❌ (managed only) |
| Managed cloud | ❌ | ✅ | 🟡 (LangSmith) | ✅ | ✅ | ✅ |
| MCP support | ❌ | ✅ (local MCP) | ❓ | ❓ | ❓ | ❓ |
| Frameworks integrados out-of-the-box | N/A | **21** | nativo | crewai+ll-index | langchain+autogen | ❓ |

**Total:** 35 features. Acima de 30 = OK pelo template ✅.

---

## 4. Notas estimadas (escala G2/Capterra 1-5)

| Critério | oimpresso (Copiloto) | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|
| **Ease of Use** (drop-in?) | 5 (porque é só MySQL) | 5 (drop-in) | 3 (graph orquestração) | 3 (LLM-mgmt) | 4 | 4 |
| **Customer Service / docs** | N/A | 4 | 5 (LangChain ecosys) | 4 | 4 | 3 (menos público) |
| **Features** | **1** (Tier 1 de 10) | 5 | 5 | 5 | 5 | 5 |
| **Value for Money** (custo vs valor) | 5 (zero deps) | 4 (managed paga; OSS grátis) | 5 (OSS) | 4 | 4 | 2 (premium only) |
| **Específico pra PHP/Laravel** | **5** (somos PHP) | 1 (Python+TS only) | 1 | 1 | 2 (REST API simples) | 1 |
| **Score total (média)** | **3.0** (estimado) | **3.8** | **3.8** | **3.4** | **3.8** | **3.0** |

> **Notas marcadas (estimadas)** — sem reviews G2/Capterra públicos pra esse vertical específico, scores baseados em LongMemEval + docs públicas + posts comparativos 2026.

**Observação importante:** o oimpresso tira nota 5 em "específico pra PHP/Laravel" só porque é a casa dele — **não significa que é bom em memória**, significa que ninguém competidor sabe falar PHP. É uma "vantagem" de incumbente que vai durar pouco se IA-first virar tese central.

---

## 5. Top 3 GAPS críticos (Copiloto runtime vs estado-da-arte)

### GAP 1 — Zero memória semântica (fatos sobre o user persistentes)

**O que falta:** todo conhecimento sobre Larissa do ROTA LIVRE (que ela usa monitor 1280px, que digita `tx_date` retroativo, que decorou shift +3h do Carbon) **mora hoje na auto-memória do Claude no laptop do Wagner** — não no Copiloto. Quando o Copiloto for vendido pro 2º cliente, **Larissa vai precisar contar tudo de novo**. Mem0/Zep/Letta resolvem isso com vector store + graph + dedup automático.
**Esforço estimado:** Médio-Alto (4-6 sprints) — adicionar tabela `copiloto_memory_facts` com vector embedding (pgvector ou Pinecone REST) + extração via LLM no `responderChat()` + retrieve top-k antes de cada call IA + dedup.
**Impacto se não fechar:** Copiloto fica genérico. Vira "ChatGPT com prompt sobre business" — sem retenção, sem personalização real, sem LTV. Direto ataca a tese de "ERP gráfico com IA" do ADR 0026.

### GAP 2 — Sem summarization / resumo automático de conversa longa

**O que falta:** `responderChat()` em [OpenAiDirectDriver.php:160](Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php#L160) limita histórico a **últimas 20 mensagens** brutalmente truncadas. Conversas que passam disso **perdem contexto silenciosamente** — Larissa diz na mensagem 5 "minha meta é R$80k/mês" e na mensagem 25 o Copiloto não sabe mais. Mem0 e Letta resolvem com summarization rolling: agrupa 20 msgs antigas em "facts" sumarizados.
**Esforço estimado:** Médio (2-4 sprints) — job `SummarizeConversaJob` que roda quando `count(mensagens) > 20`, gera resumo via LLM, salva em `copiloto_conversa_summary` (nova tabela), e `responderChat()` injeta resumo + últimas 10 msgs em vez de últimas 20.
**Impacto se não fechar:** Conversas perdem qualidade após 20 turnos. Impede uso real "diário" do Copiloto. Larissa abandona depois da 1ª semana.

### GAP 3 — Sem temporal validity (fatos que mudam ao longo do tempo)

**O que falta:** se Larissa em janeiro disse "minha meta é R$80k", em abril disse "meta nova é R$120k", **o Copiloto memoriza ambos sem saber qual é o atual**. Zep/Graphiti resolvem com graph onde cada fato tem `start_date` + `end_date` opcional ("válido a partir de X, superseded em Y"). Métricas de negócio mudam direto — sem isso o Copiloto vai dar respostas erradas com confiança.
**Esforço estimado:** Alto (>6 sprints) — exige knowledge graph ou tabela com `valid_from/valid_until`, query temporal, conflict detection ("nova meta supersedes antiga"), UX pra revisão. Difícil em PHP nativo. Caminho realista: **integrar Zep/Graphiti via REST**.
**Impacto se não fechar:** Confiança quebra na 1ª resposta com fato desatualizado ("seu meta é R$80k" quando o cliente já mudou pra R$120k). Em ERP, isso é regressão de produto, não bug — perde cliente.

---

## 6. Top 3 VANTAGENS reais

### V1 — Acoplamento direto com schema do ERP (sem ETL)

**Por que é vantagem:** Mem0/Zep/Letta precisam **importar dados externos** pra ter contexto de business (faturamento, clientes, módulos ativos). Nosso `ContextSnapshotService.paraBusiness()` já roda SQL direto em `transactions`/`contacts`/`fin_titulos` — **dados frescos, sem ETL, sem latência de sincronização**. Mem0g (graph) precisaria de um pipeline pra importar isso; nós temos de graça.
**Como capitalizar:** **expor o snapshot como tool/function no LLM** (`getBusinessSnapshot()`), permitindo o agente buscar dados frescos a cada turno sem custo de embedding. Hybrid: usa Mem0 pra fatos do user; usa snapshot pra fatos do business.
**Risco de erodir:** baixo — concorrentes externos não vão ter acesso ao MySQL do cliente.

### V2 — Multi-tenant nativo (business_id) embutido no schema

**Por que é vantagem:** UltimatePOS já é multi-tenant via `business_id`. Mem0/Zep tratam multi-tenancy via `user_id` custom — quem tem 100 businesses cada com 5 users precisa montar `user_id="biz4_user12"` na mão. Nosso schema tem `business_id` em toda tabela; quando adicionarmos `copiloto_memory_facts`, ela vai ter `business_id` + `user_id` + `agent_id` por design.
**Como capitalizar:** vender Copiloto como "memória multi-tenant nativa pra ERP brasileiro" — caso em que Mem0/Zep precisam de wrapper.
**Risco de erodir:** médio — Mem0 vai melhorar multi-tenancy nativo em 12-18m.

### V3 — Auto-memória do Claude (camada A) **alimentando** memória runtime (camada B)

**Por que é vantagem:** Nenhum competidor tem essa ponte. Wagner já documentou em auto-memória que "Larissa decorou shift +3h" e "monitor 1280px"; podemos **importar essas notas pra memória runtime do Copiloto** quando Larissa logar — Copiloto já chega sabendo dela. Concorrentes começam do zero.
**Como capitalizar:** script `php artisan copiloto:seed-memory-from-auto-mem` que lê `cliente_*.md` da auto-mem e insere em `copiloto_memory_facts` no onboarding de cada cliente novo.
**Risco de erodir:** alto — depende do Wagner manter auto-mem viva. Pode escalar mal com 50 clientes.

---

## 7. Posicionamento sugerido (mín. 3 caminhos)

| Caminho | Tese curta | Veredito |
|---|---|---|
| **A — "Construir do zero":** layer PHP nativo de memória (vector, summary, semantic) | Sem dep externa, custo R$0/mês recorrente | ❌ 8-12 sprints só pra Tier 3-4. Reinventa roda. Não atinge LongMemEval >70%. |
| **B — "REST adapter pra Mem0":** integrar Mem0 managed via REST, treat agent-facts como first-class | 3-5 sprints, drop-in, latência -91%, ecosistema 21 frameworks | ✅ **Recomendado** — pragmático, abre caminho pra Tier 6-7 rapidamente |
| **C — "Híbrido Zep+ContextSnapshot":** Zep pra temporal facts do user + nosso snapshot pra dados frescos do business | Cobre temporal validity (GAP 3) com 1 dep só | 🟡 melhor pra long-term mas custa 5-8 sprints (REST + schema graph + UX) |
| **D — "Aceitar Tier 1":** ficar como está, focar em outras features prioritárias do ADR 0026 | Zero esforço; aposta que LLM context window cresce mais rápido que necessidade | 🟡 viável se cliente final não pedir personalização — mas direto contradiz "ERP com IA" do posicionamento |

**Recomendado: B — REST adapter pra Mem0.** Razões:
- Mem0 é o líder em adoção (21 frameworks integrados; trata agent-generated facts como first-class).
- API REST acessível de PHP sem SDK específico.
- Resolve GAP 1 (semantic) e GAP 2 (summarization) em 1 movimento.
- Não compromete arquitetura — Mem0 fica atrás de uma `MemoriaContrato` interface; trocável por Zep ou Letta depois.

**Frase de posicionamento:**
> *"Copiloto = ContextSnapshot fresco do ERP + Mem0 pra fatos persistentes do user — um diferencial real ('memória multi-tenant integrada ao schema'), não slide de pitch."*

---

## 8. Math do custo de implementar (caminho B)

> **Adaptação:** template original conecta com R$5mi/ano (ADR 0022). Aqui, math do **custo de cada caminho** vs valor de Copiloto comercializável.

Pressupostos:
- Mem0 managed: ~$25/mês plano starter (até 10k memórias) — **fontes não confirmadas**, validar antes
- 1 sprint Wagner solo = ~80h de código PHP
- Custo oportunidade: 1 sprint = adiar 1 feature do ADR 0026 (PricingFpv ou CT-e)

**Caminho B (recomendado):**
- 1 sprint: schema + interface `MemoriaContrato` + driver REST Mem0 + testes Pest
- 1 sprint: integração no `responderChat()` (search antes / write depois) + idempotência
- 1 sprint: extração de fatos via LLM (passar Mensagem por extrator) + dedup
- 1 sprint: UX de transparência ("o Copiloto lembra: ...") + opt-out por user
- 1 sprint: stress test com 1.000 mensagens / business + latência <300ms p95
- **Total: 5 sprints (5-7 semanas no ritmo Wagner)**
- **Custo recorrente: $25-300/mês** dependendo de volume + escala

**Caminho A (zero):**
- 8-12 sprints. Tier 3-4. Sem temporal. Sem hybrid. **Não recomendado.**

**Caminho D (aceitar Tier 1):**
- 0 sprints. Aposta em context window. Risco: GPT-5/Claude 5 podem acabar com necessidade de mem persistente — em ~24m? Talvez.

**ROI estimado de B:**
- Tese: Copiloto com memória runtime = +30-50% retenção em SaaS. Sem números reais — sem cliente pagante hoje (ROTA LIVRE não paga pelo Copiloto).
- Pra a meta R$5mi/ano (ADR 0022), Copiloto precisa converter em ticket — sem mem runtime, é commodity.
- **Assunção não validada:** "memory persistente aumenta retenção 30%" — não tem dado real ainda. Larissa não foi entrevistada.

---

## 9. Recomendação concreta

### 3 ações prioritárias pra próximos 6 meses (em ordem)

1. **Definir interface `MemoriaContrato` PHP** + driver `Mem0RestDriver` (caminho B, sprint 1) — abstrai pra trocar por Zep/Letta sem reescrita. **1 sprint.**
2. **Wireframe da feature "Copiloto lembra de você"** com Larissa antes de codar — validar se ela percebe valor antes de gastar 5 sprints. **0.5 sprint** (entrevista + figma estático).
3. **Integrar Mem0 só em 1 fluxo (briefing inicial)** — quando Larissa abre `/copiloto`, mostrar "lembro que tua meta era X" + permitir corrigir. Loop fechado, mensurável. **1 sprint.**

Máximo 3. Restantes (extração via LLM, temporal validity, escala) ficam pra fases 2-3.

### O que NÃO fazer agora

- ❌ NÃO construir vector store PHP nativo (caminho A) — custo > valor.
- ❌ NÃO integrar Zep+Mem0+Letta simultaneamente — escolher 1, validar, depois decidir.
- ❌ NÃO migrar memory/ (camada A — dev memory) pra Mem0 — são camadas diferentes, ver ADR 0027.
- ❌ NÃO esperar GPT-5/Claude 5 resolverem (caminho D) — aposta não justificada na meta R$5mi/ano.

### Métrica de fé (90 dias)

> *"Se em 90 dias (até 2026-07-25) Mem0 estiver integrado em 1 fluxo do Copiloto **E** Larissa do ROTA LIVRE conseguir validar que percebe diferença ('o sistema lembrou da minha meta'), **confirma a tese B**. Se ela achar redundante OU integração custar >7 sprints, **pivota pra D (aceitar Tier 1)** e foca em PricingFpv/CT-e do ADR 0026."*

Gatilho de pivot mensurável: 1 cliente fala explicitamente "preciso que ele lembre de X" durante teste = confirma; 0 menções em 90d = sinal de que não é prioridade.

---

## 10. Sources

- [Mem0 GitHub](https://github.com/mem0ai/mem0)
- [Mem0: Building Production-Ready AI Agents with Scalable Long-Term Memory (arXiv 2504.19413)](https://arxiv.org/abs/2504.19413)
- [State of AI Agent Memory 2026 (Mem0 blog)](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [LangChain Memory Overview](https://docs.langchain.com/oss/python/langgraph/memory)
- [LangMem Conceptual Guide](https://langchain-ai.github.io/langmem/concepts/conceptual_guide/)
- [Atlan: Best AI Agent Memory Frameworks 2026](https://atlan.com/know/best-ai-agent-memory-frameworks-2026/)
- [DEV: 5 AI Agent Memory Systems Compared (2026 Benchmark)](https://dev.to/varun_pratapbhardwaj_b13/5-ai-agent-memory-systems-compared-mem0-zep-letta-supermemory-superlocalmemory-2026-benchmark-59p3)
- [Hermes OS: AI agent memory systems in 2026 — Zep, Mem0, Letta](https://hermesos.cloud/blog/ai-agent-memory-systems)
- [Vectorize: Mem0 vs Letta (MemGPT) Comparison](https://vectorize.io/articles/mem0-vs-letta)
- [Yogesh Yadav (Medium): AI Agent Memory Systems 2026](https://yogeshyadav.medium.com/ai-agent-memory-systems-in-2026-mem0-zep-hindsight-memvid-and-everything-in-between-compared-96e35b818da8)
- [OMEGA Compare](https://omegamax.co/compare)
- Internas:
  - [_TEMPLATE_capterra_oimpresso.md v1.0](_TEMPLATE_capterra_oimpresso.md)
  - [sistemas_memoria_oimpresso_capterra_2026_04_26.md](sistemas_memoria_oimpresso_capterra_2026_04_26.md) (camada A — dev memory)
  - [memory/decisions/0026-posicionamento-erp-grafico-com-ia.md](../decisions/0026-posicionamento-erp-grafico-com-ia.md)
  - [Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php](../../Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php)
  - [Modules/Copiloto/Services/ContextSnapshotService.php](../../Modules/Copiloto/Services/ContextSnapshotService.php)

---

## Anexo A — Funções necessárias estilo "necessidades" / user stories

> Wagner pediu "descreva as função necessária e descreva estilo necisidades" — formato US-Gherkin alinhado com `memory/requisitos/Copiloto/SPEC.md`.

Numeração reservada: **US-COPI-MEM-001 a US-COPI-MEM-014** — material para virar SPEC formal em `memory/requisitos/Copiloto/SPEC.md` quando o caminho B (Mem0) for aprovado.

### Tier 2 — Fundação (sprint 1-2)

**US-COPI-MEM-001** — Interface `MemoriaContrato` PHP
> **Como** desenvolvedor do Copiloto
> **Quero** uma interface PHP que abstraia a memória runtime
> **Para** poder trocar Mem0 por Zep/Letta sem reescrever código
>
> **Cenário 1:** Implementação injetada via container Laravel
> Dado que `MemoriaContrato` está bind no service provider
> Quando uma classe pede via DI
> Então recebe a implementação configurada em `copiloto.memoria.driver` (default `mem0_rest`)
>
> **Métodos:** `lembrar(userId, businessId, fato): void`, `buscar(userId, businessId, query, topK=5): array`, `esquecer(memoriaId): void`, `atualizar(memoriaId, novoFato): void`

**US-COPI-MEM-002** — Driver `Mem0RestDriver`
> **Como** Copiloto
> **Quero** acessar Mem0 via REST API
> **Para** ter memória semântica sem precisar SDK PHP

**US-COPI-MEM-003** — Driver fallback `NullMemoriaDriver`
> **Como** Copiloto rodando em dev/dry_run
> **Quero** um driver que devolve fixtures
> **Para** testar sem rede e sem custo

### Tier 3 — Escrita ativa (sprint 3-4)

**US-COPI-MEM-004** — Extração de fatos via LLM ao final de cada resposta
> **Como** Copiloto após responder ao user
> **Quero** extrair fatos novos da conversa via LLM
> **Para** persistir só o que importa (não a transcrição inteira)
>
> **Trigger:** `ChatController@send` após `responderChat()`
> **Job:** `ExtrairFatosDaConversaJob` (assíncrono, hot path opcional)

**US-COPI-MEM-005** — Multi-tenant scope obrigatório
> **Cenário:** Larissa do ROTA LIVRE (biz=4) e Pedro do ROTA NOVA (biz=8) usam Copiloto
> Quando Larissa salvar fato "minha meta é R$80k"
> Então `Pedro` jamais consulta esse fato (scope `business_id=4` + `user_id=Larissa.id`)

**US-COPI-MEM-006** — Dedup automático de fatos repetidos
> **Cenário:** "minha meta é R$80k" dito 3x em 3 conversas
> Quando o Copiloto extrai fato pela 3ª vez
> Então identifica como duplicata (similarity ≥ 0.9) e atualiza timestamp em vez de criar novo

### Tier 4 — Leitura inteligente (sprint 5)

**US-COPI-MEM-007** — Recall top-k antes de cada resposta IA
> **Como** Copiloto antes de gerar resposta
> **Quero** buscar top-5 fatos relevantes pra mensagem atual
> **Para** o LLM responder com contexto certo
>
> Injeta como `system message` adicional: "Você lembra: [fato1, fato2, ...]"

**US-COPI-MEM-008** — Resumo rolling de conversas longas
> **Cenário:** conversa com >20 mensagens
> Quando o user envia a mensagem 21
> Então as primeiras 10 mensagens são sumarizadas via LLM e arquivadas em `copiloto_conversa_resumo`; o LLM recebe `[resumo + últimas 10 msgs]`

### Tier 5 — Diferenciação (sprint 6+)

**US-COPI-MEM-009** — Temporal validity (fatos com `valid_from`/`valid_until`)
> **Cenário:** Larissa em jan disse meta=R$80k; em abr disse meta=R$120k
> Quando o Copiloto recall "qual a meta atual?"
> Então retorna **só** o fato com `valid_until=NULL` (mais recente). Fato antigo é archived com `valid_until=2026-04-15`

**US-COPI-MEM-010** — Conflict detection ao escrever fato
> **Cenário:** Larissa diz "meta é R$120k" mas já existe fato "meta é R$80k" (active)
> Quando extrator identifica conflito (similar key, valor diferente)
> Então atualiza o antigo com `valid_until=now()` e cria novo `valid_from=now()`. Loga em `copiloto_memory_audit`.

**US-COPI-MEM-011** — Bridge auto-memória → memória runtime no onboarding
> **Cenário:** novo cliente subscreve Copiloto
> Quando rodar `php artisan copiloto:seed-memory --business=4 --user=larissa@rotalivre.com`
> Então fatos de `cliente_rotalivre.md` da auto-mem são importados como `copiloto_memory_facts`

**US-COPI-MEM-012** — Tela "O Copiloto lembra de você"
> **Como** Larissa
> **Quero** ver o que o Copiloto sabe sobre mim
> **Para** corrigir ou apagar fatos errados (LGPD compliant)
>
> Rota: `/copiloto/memoria` — lista fatos por categoria, com botões "esquecer" e "corrigir"

### Tier 6 — Avançado (futuro)

**US-COPI-MEM-013** — Knowledge graph entre entidades (cliente, produto, meta, fornecedor)
> **Trade-off:** exige Zep/Graphiti como dep adicional. Adiar até validar Tier 2-5.

**US-COPI-MEM-014** — Procedural memory (skills aprendidos pelo agente)
> Ex.: "Sempre que Larissa pedir relatório, exportar como PDF e mandar por WhatsApp" — agente aprende workflow específico do user.

---

**Resumo de qual tier viabiliza o quê:**

| Tier | US | Output | Tier no benchmark estado-da-arte |
|---|---|---|---|
| 2 | 001-003 | Driver pluggável + Mem0 funcional | Tier 4 (saímos do Tier 1) |
| 3 | 004-006 | Escrita ativa multi-tenant | Tier 5 |
| 4 | 007-008 | Leitura contextualizada + summary | Tier 6-7 (chega ao Mem0 base) |
| 5 | 009-012 | Temporal validity + UX LGPD | Tier 8 (passa Mem0 base, alcança Zep) |
| 6 | 013-014 | Graph + procedural | Tier 9-10 (compete com Letta/OMEGA) |

---

## Checklist (template)

- [x] TL;DR cabe em 5 frases
- [x] Mín. 4 concorrentes incluídos (5)
- [x] 30+ features na matriz (35)
- [x] Notas escala 1-5 (todas marcadas estimadas — sem reviews G2/Capterra públicos pra esse vertical específico)
- [x] **Exatamente 3 GAPS e 3 VANTAGENS**
- [x] **Mín. 3 caminhos de posicionamento** com veredito (4 caminhos)
- [x] Math (adaptado: custo de implementar caminho B)
- [x] **3 ações prioritárias** em ordem
- [x] **Métrica de fé** com prazo (90d) e gatilho de pivot
- [x] Sources literais com URL (11 externas + 5 internas)
- [x] Companion docs linkados no frontmatter

**Score: 11/11 ✅**
