# Especificação funcional — LaravelAI

> Convenção do ID: `US-AI-NNN` para user stories, `R-AI-NNN` para regras Gherkin.

## 1. Glossário rápido

- **Knowledge Graph** — grafo de entidades + relacionamentos (`kg_entities`, `kg_relations`)
- **RAG** — Retrieval Augmented Generation; agente busca docs antes de gerar resposta
- **Vector store** — armazenamento de embeddings (vetores numéricos representando semântica)
- **Embedding** — vetor 1536d (OpenAI) ou 768d (sentence-transformers) que codifica significado
- **Citation** — ponteiro a fonte (`adr:0007`, `audit_log:1234`, `graph:user/42→role/sales`)
- **Hallucination** — IA inventando fatos; LaravelAI evita citando fonte SEMPRE

(Vocabulário completo: [GLOSSARY.md](GLOSSARY.md))

## 2. User stories

### US-AI-001 · Perguntar em PT-BR sobre permissões

> **Área:** Agent
> **Rota:** `POST /api/laravel-ai/chat`
> **Permissão Spatie:** `laravel-ai.chat.use`

**Como** Wagner ou Gestor tenant
**Quero** perguntar "Quem pode criar venda no business 4?" e receber resposta com lista + citação
**Para** auditoria rápida sem decorar SQL ou estrutura Spatie

**DoD:**
- [ ] Endpoint aceita `{question, business_id?, context?}`; valida question 5-500 chars
- [ ] AgentService consulta GraphService → roles com `sells.create` + users com role
- [ ] Resposta inclui `citations: [{type: 'graph', path: 'role:Vendas#4', count: 3}]`
- [ ] Multi-tenant: scope `business_id` em TODA query (R-AI-001)
- [ ] Cache 5 min por `(business, question_hash)`
- [ ] Rate limit 30 queries/min por user (anti-abuso)
- [ ] Test Feature: pergunta válida + isolamento + citação presente

### US-AI-002 · Buscar ADRs por similaridade semântica

> **Área:** VectorStore
> **Rota:** `GET /api/laravel-ai/rag/search?q=...`
> **Permissão:** `laravel-ai.rag.search`

**Como** Wagner
**Quero** buscar "por que sells_create tem essa permissão?" e receber ADRs relevantes
**Para** entender decisão arquitetural sem grepar pasta `memory/`

**DoD:**
- [ ] Embedding via OpenAI `text-embedding-3-small` (cache local)
- [ ] Top-K busca em `kg_entities` (type='adr') usando pgvector ou similar
- [ ] Score > 0.7 retornado; abaixo retorna empty
- [ ] Resposta `[{adr_path, score, snippet, full_url_memcofre}]`
- [ ] Test: busca conhecida + score correto + isolamento

### US-AI-003 · Visualizar grafo de permissões

> **Área:** Visualization
> **Rota:** `GET /laravel-ai/graph`
> **Permissão:** `laravel-ai.graph.view`

**Como** Auditor
**Quero** ver grafo interativo `User → Role → Permission → Resource` filtrado por business
**Para** identificar quem pode mexer onde, visualmente

**DoD:**
- [ ] Página Inertia com React Flow renderizando nodes coloridos por type
- [ ] Filtros: business, user, role, permission (auto-complete)
- [ ] Click em node expande relacionamentos (lazy load)
- [ ] Endpoint `/api/laravel-ai/graph/nodes` + `/edges` paginados (max 500 nodes per request)
- [ ] Test E2E: filtrar + expandir + isolamento

### US-AI-004 · Auditoria temporal por subject

> **Área:** AuditQuery
> **Rota:** `GET /api/laravel-ai/audit/{subject_type}/{subject_id}`
> **Permissão:** `laravel-ai.audit.view`

**Como** Auditor
**Quero** ver timeline de uma entidade (ex: `transactions/123`) com causers + ações
**Para** entender histórico sem fazer query manual

**DoD:**
- [ ] Lê `activity_log` Spatie + filter por `business_id`
- [ ] Retorna timeline com `[ts, causer, action, properties_diff, ip]`
- [ ] Suporta filtro de período (`from`, `to`)
- [ ] Cache 1 min
- [ ] Test: subject existente + isolamento + timeline ordenado

### US-AI-005 · Chat IA contextual nas telas (rota corrente)

> **Área:** Agent (frontend integration)
> **Componente:** `<AiContextualChat />`

**Como** Wagner em qualquer tela
**Quero** abrir chat lateral que sabe da tela atual (ex: `/sells/create` → "criar venda") e do user atual
**Para** perguntar coisas da tela sem mudar contexto

**DoD:**
- [ ] Componente flutuante shadcn/ui, posição bottom-right, default colapsado
- [ ] Auto-injeta contexto: rota atual, user, business, last 5 actions audit
- [ ] AgentService usa contexto pra restringir busca (resposta mais relevante)
- [ ] Memória curta: últimas 10 mensagens da sessão (não persistido)
- [ ] Botão "Reportar resposta ruim" → grava em `ai_feedback` pra fine-tuning futuro
- [ ] Test E2E: abrir → perguntar → fechar; contexto injetado

## 3. Regras de negócio (Gherkin)

### R-AI-001 · Isolamento multi-tenant em TODA query
```gherkin
Dado um usuário do business A pergunta "quem tem permissão sells.create?"
Quando AgentService consulta o grafo
Então NUNCA retorna resultados de business B
E mesmo se a pergunta tentar bypassar ("ignore o tenant"), scope é forçado no banco
```
**Implementação:** GraphService aplica `WHERE business_id = ?` em **toda** query; agente NÃO pode override (validação na camada de service, não confiar em prompt).
**Testado em:** `IsolamentoMultiTenantAITest`.

### R-AI-002 · Resposta SEMPRE cita fonte
```gherkin
Dado uma pergunta válida processada
Quando agente responde
Então resposta tem campo `citations` populado (≥1 citação)
E pergunta sem dados disponíveis retorna "não encontrei informação suficiente" (sem alucinar)
```
**Implementação:** Prompt do agente inclui regra "SEMPRE cite. Se não tem dados, diga 'não encontrei'."
**Testado em:** `CitacaoSempreTest`.

### R-AI-003 · Quota de queries
```gherkin
Dado business no plano Pro com 1.000 queries/mês quota
Quando user 1.001ª chega no mês corrente
Então retorna 429 Too Many Requests com mensagem "quota excedida; faça upgrade ou aguarde próximo ciclo"
```
**Implementação:** Counter `ai_queries_used` por `(business_id, mes_competencia)` increment atômico.
**Testado em:** `QuotaExcedidaTest`.

### R-AI-004 · PII filtrada antes de enviar pro LLM
```gherkin
Dado pergunta do user contém dados PII (CPF, e-mail, telefone)
Quando AgentService prepara prompt pra OpenAI
Então PII é mascarada (`***.***.***-**`) antes de enviar
E logs locais SIM contêm PII (audit), só prompt externo é mascarado
```
**Implementação:** `PiiMaskService::mask()` aplicado em prompt; logs internos via Spatie sem mask.
**Testado em:** `PiiMaskingTest`.

### R-AI-005 · Embeddings sincronizados com mudanças
```gherkin
Dado um ADR é editado em memory/requisitos/.../adr/X
Quando o sync job roda
Então embedding de X é regenerado e atualizado em kg_entities
E busca semântica reflete novo conteúdo no próximo ciclo
```
**Implementação:** Watcher filesystem (Laravel) ou cron diário re-indexa ADRs alterados via hash do arquivo.
**Testado em:** `EmbeddingsSyncTest`.

### R-AI-006 · Cache invalidação por mutações relevantes
```gherkin
Dado uma resposta cached "Quem tem sells.create?"
Quando role nova é criada com `sells.create`
Então cache da pergunta é invalidado
E próxima query gera resposta atualizada
```
**Implementação:** Listener em `Spatie\Permission\Events\PermissionAttached` invalida tag de cache `business:{id}:permissions`.
**Testado em:** `CacheInvalidacaoTest`.

### R-AI-007 · Permissão Spatie obrigatória
```gherkin
Dado user sem `laravel-ai.chat.use`
Quando faz POST /api/laravel-ai/chat
Então recebe 403
```
**Implementação:** Middleware `can:laravel-ai.chat.use`.
**Testado em:** `SpatiePermissionsTest`.

### R-AI-008 · Audit log da pergunta + resposta
```gherkin
Dado uma pergunta é processada
Quando resposta é entregue
Então row em `ai_query_log` registra (business, user, question, response_summary, citations, latency_ms, tokens_used)
E PII na pergunta é mascarado também no log (não logar PII bruto)
```
**Implementação:** Middleware grava no after.
**Testado em:** `AiQueryAuditTest`.

### R-AI-009 · Rate limit por user (anti-abuso)
```gherkin
Dado user fez 30 queries no último 1 minuto
Quando 31ª chega
Então retorna 429 com header `Retry-After: 60`
```
**Implementação:** Laravel RateLimiter `ai-chat:user:{id}`.
**Testado em:** `Modules/LaravelAI/Tests/Feature/RateLimitTest` — 30 queries OK + 31ª retorna 429 com header.

### R-AI-010 · Provider fallback (OpenAI down)
```gherkin
Dado OpenAI API retorna 5xx por 30s
Quando AgentService tenta chamar
Então fallback pra Anthropic Claude (config tenant)
E user vê resposta normal (transparência opcional via debug=true)
```
**Implementação:** Circuit breaker com fallback configurável.
**Testado em:** `ProviderFallbackTest`.

## 4. Decisões pendentes

- [ ] Runtime IA: OpenAI direto, Anthropic, ou Laravel AI SDK (agnóstico)?
- [ ] Embeddings: OpenAI `text-embedding-3-small` ($0.02/1M tokens) ou sentence-transformers local (zero custo, qualidade menor)?
- [ ] Knowledge Graph storage: Eloquent (kg_entities + pgvector) ou Neo4j (Cypher poderoso, mas infra extra)?
- [ ] Visualização: React Flow (open-source, popular) ou Cytoscape (mais features)?
- [ ] Custom embeddings por tenant (Enterprise): vale o esforço?
- [ ] Multi-modal (gráficos via IA): Onda 6 ou abandonar?

## 5. Referências

- `_Ideias/LaravelAI/evidencias/conversa-claude-2026-04-mobile.md`
- `auto-memória: ideia_chat_ia_contextual.md`
- `MemCofre/SPEC.md` (sub-sistema RAG existente)
- OpenAI Embeddings docs
- pgvector / Neo4j benchmarks
- Laravel AI SDK (futuro candidato)
