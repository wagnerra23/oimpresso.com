# Agent F · ONDA 4 — KbRagService + KbAiController (IA RAG do KB Unificado)

> **Sessão:** 2026-05-15
> **Worktree:** `practical-engelbart-8d8eb0`
> **Onda:** 4 do roadmap KB Unificado (BRIEFING §"Plano em 6 ondas")
> **Skills ativadas:** brief-first, mcp-first, multi-tenant-patterns, commit-discipline (Tier A); rules path-scoped `modules.md`, `migrations.md`

## Resumo (3-5 linhas)

ONDA 4 — IA RAG sobre o grafo de conhecimento. Criados `KbRagService` (3 métodos: `ask`/`summarize`/`suggestMeta`), `KbCorpusBuilder` (retrieval Meilisearch hybrid + serialize body_blocks + corpus_version_hash), `KbAiController` (3 endpoints REST + audit append-only + rate-limit 10/min), 3 DTOs imutáveis (`RagResult`/`SummaryResult`/`MetaSuggestion`), comando artisan `kb:reindex --business=N`, e bloco aditivo no final de `routes.php` com delimitador "============= IA RAG (Agent F · ONDA 4) =============" pra merge limpo com Agent A. Reusa `Modules/Jana/Ai/Agents/KbAnswerAgent` (laravel/ai SDK, ADR 0035) — **nenhum provider IA novo criado**.

## Arquivos criados

| Caminho                                                                              | Linhas | Função                                                                                       |
|--------------------------------------------------------------------------------------|--------|----------------------------------------------------------------------------------------------|
| `Modules/KB/Services/Dtos/RagResult.php`                                             | 96     | DTO resposta `ask` (answer + sources[] + meta com latency/tokens/cost/confidence)            |
| `Modules/KB/Services/Dtos/SummaryResult.php`                                         | 67     | DTO resposta `summarize` (tldr + bullets + audience_hint + source ref)                       |
| `Modules/KB/Services/Dtos/MetaSuggestion.php`                                        | 60     | DTO resposta `suggestMeta` (title + excerpt + tags[] + category_slug + nivel)                |
| `Modules/KB/Services/KbCorpusBuilder.php`                                            | 240    | Streaming docs pro Meilisearch + corpus_version_hash + retrieve top-K + serializeBlocks      |
| `Modules/KB/Services/KbRagService.php`                                               | 530    | Service principal — 3 métodos públicos, RAG flow completo, cache Redis, PII redact, custo BRL |
| `Modules/KB/Http/Controllers/KbAiController.php`                                     | 220    | 3 endpoints REST `/kb/ai/{ask,summarize,suggest-meta}` + audit mcp_audit_log + throttle      |
| `Modules/KB/Console/Commands/KbReindexCommand.php`                                   | 130    | `php artisan kb:reindex --business=N [--dry-run] [--detail]` → batch send ao Meilisearch     |
| `Modules/KB/Http/routes.php`                                                         | +35    | Block ADITIVO no FINAL com delimitador `// ============= IA RAG (Agent F · ONDA 4) =============` |

**Total:** 7 arquivos novos + 1 edit aditivo (~1340 linhas PHP novas).

## Contrato cumprido (vs SCHEMA-DB-V1 §11)

| Endpoint canônico                | Implementado | Permission         | Rate-limit | Audit  |
|----------------------------------|--------------|--------------------|------------|--------|
| `POST /kb/ai/ask`                | ✅            | `kb.ai.ask`*       | 10/min     | ✅      |
| `POST /kb/ai/summarize/{slug}`   | ✅            | `kb.ai.ask`*       | 10/min     | ✅      |
| `POST /kb/ai/suggest-meta`       | ✅            | `kb.ai.ask`*       | 10/min     | ✅      |

\* Fallback temporário `copiloto.mcp.memory.manage` (mesma roda do KbController) até PermissionRegistry promover `kb.ai.ask` pra Spatie real — dívida técnica preservada conforme `Resources/permissions.php` linha 14-26.

## TODOs (que ficam pra Agent A/B/posterior)

| Item                                                                                                                  | Owner         | Prioridade | Notas                                                                                              |
|-----------------------------------------------------------------------------------------------------------------------|---------------|------------|----------------------------------------------------------------------------------------------------|
| Adicionar trait `Searchable` em `KbNode` (Laravel Scout) OU criar `KbCorpusDocument` shadow model                     | Agent A       | P1         | Hoje `KbCorpusBuilder::retrieve` chama cliente Meilisearch direto. Idiomático seria Scout.         |
| Adicionar entrada `kb.ai.ask` em `Modules/KB/Resources/permissions.php`                                               | Agent A       | P1         | Fallback `copiloto.mcp.memory.manage` evita 403, mas SCHEMA §12 cita `kb.ai.ask` como canon.        |
| Registrar `KbReindexCommand` em `KBServiceProvider::register()` (`$this->commands([KbReindexCommand::class])`)        | Agent A       | P1         | Sem isso `php artisan kb:reindex` retorna "Command not found".                                      |
| Adicionar `kb.usd_to_brl`, `kb.meilisearch.embedder`, `kb.meilisearch.semantic_ratio` em `Modules/KB/Config/config.php` | Agent A     | P2         | Service tem defaults sensatos (5.0 / 'openai' / 0.7). Sem config funciona.                          |
| Configurar embedder OpenAI no índice Meilisearch `kb_corpus` (admin Meilisearch)                                      | Wagner/Devops | P0         | Sem embedder no índice, search vira só full-text (sem semantic ratio). ADR 0035 + 0036.            |
| Registrar `throttle:kb-ai-ask` named bucket em `RouteServiceProvider` se quiser sobrescrever 10/min                   | Agent A       | P3         | Hoje uso `throttle:10,1` inline — funciona, mas bucket nomeado dá métrica mais limpa.               |
| Pest tests biz=1 + cross-tenant biz=99 (ADR 0101)                                                                     | Agent F (próx.) | P0       | `KbRagServiceTest` + `KbAiControllerTest` + `KbCorpusBuilderTest`. Validado depois das migrations rodadas. |
| Idempotency-Key handler — hoje cache key cobre. Header `Idempotency-Key` opcional já é lido pelo controller.          | —             | P3         | Funciona, mas TTL=1h default. Considerar TTL maior se Wagner pedir.                                 |
| Move bloco `/kb/ai/*` PARA DENTRO do group `/kb` (linhas 101-104 placeholder do Agent A)                              | Wagner (merge)| P3         | Cosmético — middleware idêntico, só limpa estrutura.                                                |

## Custo IA estimado por método

Modelo default canônico (ADR 0035 Brain A — barato): **gpt-4o-mini** via laravel/ai SDK.
Preços oficiais (Jan/2026, atualizar em `KbRagService::PRICE_*`):
- Input:  US$ 0.15 / 1M tokens
- Output: US$ 0.60 / 1M tokens
- USD→BRL fallback: 5.0 (configurável `config('kb.usd_to_brl')`)

| Método                  | Tokens IN típicos                                              | Tokens OUT típicos | Custo USD/call | Custo BRL/call | Fórmula                                                                  |
|-------------------------|----------------------------------------------------------------|--------------------|-----------------|-----------------|--------------------------------------------------------------------------|
| `ask` (sem cache)       | ~3.500 (system 400 + 6 fontes × ~500 + pergunta 100)            | ~400               | ~$0.000765      | **~R$ 0,0038**  | `(3500*0.15 + 400*0.60) / 1e6 * 5`                                       |
| `ask` (cache hit)       | 0                                                              | 0                  | $0              | **R$ 0,00**     | Redis 1h TTL — corpus_hash invalida automaticamente quando bridge atualiza |
| `summarize`             | ~3.300 (system 200 + body 3000 + meta 100)                      | ~250               | ~$0.000645      | **~R$ 0,0032**  | `(3300*0.15 + 250*0.60) / 1e6 * 5`                                       |
| `summarize` (cache hit) | 0                                                              | 0                  | $0              | **R$ 0,00**     | Redis 6h TTL — chave inclui `node.updated_at` + `sourceDoc.updated_at`    |
| `suggestMeta`           | ~2.500 (system 200 + body 2300)                                 | ~150               | ~$0.000465      | **~R$ 0,0023**  | `(2500*0.15 + 150*0.60) / 1e6 * 5`                                       |

**Projeção mensal Wagner ROTA LIVRE (biz=4) — uso pesado:**
- 500 perguntas/mês → 500 × R$ 0,0038 = **R$ 1,90/mês**
- 200 summarize/mês → 200 × R$ 0,0032 = **R$ 0,64/mês**
- 100 suggestMeta/mês → 100 × R$ 0,0023 = **R$ 0,23/mês**
- **Total: ~R$ 3/mês por business em uso pesado** — desprezível vs receita.

**Alerta de explosão de custo:** monitorar `mcp_audit_log.custo_brl SUM by business_id` daily. Se um business passar R$ 30/mês, investigar (loop user / scraping / bug).

## Padrão de prompt usado

### `ask` — RAG single-shot
Reusa `Modules/Jana/Ai/Agents/KbAnswerAgent` (já existente, validado). Pattern:

```
SYSTEM (KbAnswerAgent::instructions, ~400 tokens):
  "Você é Jana, copiloto IA do oimpresso. Q&A natural sobre KB.
   FORMATO OBRIGATÓRIO: Resposta: ... / Citações: ... / Confiança: alta|média|baixa
   REGRAS: PT-BR, sem corporativês, NUNCA invente paths/slugs, redact PII."

USER (KbAnswerAgent::montarPrompt, ~3000 tokens):
  PERGUNTA: <pergunta sanitized>
  FONTES (top-N=6 nodes pós re-rank):
    [1] adr · ADR 0093 Multi-tenant Tier 0 (slug: 0093-multi-tenant-isolation-tier-0)
        Todo Eloquent Model que toca dados de negócio DEVE ter business_id global scope...
    [2] charter · Index.charter.md (slug: kb-index-charter)
        Mission: cérebro consultável da empresa...
    [3] ... (até [6])
```

**Re-rank V1** (KbRagService::rerank): score Meilisearch + bonus:
- `+0.15` se type ∈ {charter, adr, runbook} — governança canon
- `+0.10` se type = 'article' — operacional Larissa
- `-0.05` se snippet vazio

**Self-reflection / multi-step:** NÃO implementado V1. Pesquisa Reflexion (NeurIPS 2023) mostra ganho 15-30% mas dobra custo. Plano: ativar via flag `config('kb.reflexion.enabled', false)` em ONDA 7+ se Wagner pedir.

### `summarize` — single-shot reuso do KbAnswerAgent
Override de prompt: contexto = corpo do node + instrução "TL;DR + 3-5 bullets". Reusa mesmo Agent pra economizar boilerplate.

### `suggestMeta` — single-shot, estrutura `TITLE: x · EXCERPT: y · TAGS: ... · CATEGORY: ... · NIVEL: ...`
Parser regex tolerante. Fail-open: se shape inválido, retorna excerpt = primeiros 280 chars do body sanitized.

## Caching strategy detalhada

| Cache              | Backend | TTL  | Key                                                                                                  | Invalidação                                                  |
|--------------------|---------|------|------------------------------------------------------------------------------------------------------|--------------------------------------------------------------|
| `ask` cache        | Redis   | 1h   | `kb:ai:ask:{sha1(query+corpus_hash)}:biz:{N}` OR `kb:ai:ask:idem:{sha1(Idempotency-Key)}:biz:{N}`     | Corpus_version_hash muda quando `kb_nodes.updated_at` OR `mcp_memory_documents.updated_at` MAX muda |
| `summarize` cache  | Redis   | 6h   | `kb:ai:sum:biz:{N}:{slug}:{node_updated_at_ts}:{source_doc_updated_at_ts}`                            | Updated_at de node OR sourceDoc muda → cache key muda → miss |
| Embeddings         | Meilisearch | persistido | índice `kb_corpus` (per business_id filter) | `php artisan kb:reindex --business=N` (ou Scout observers quando ativados) |

**Corpus version hash** (`KbCorpusBuilder::corpusVersionHash`):
```
sha1("biz:{N}|kb:{max(kb_nodes.updated_at)}|mcp:{max(mcp_memory_documents.updated_at)}")
```
2 queries SQL agregadas ~5-15ms. Não cacheado entre requests pois corpus pode mudar.

**Defesa em profundidade PII (ADR 0094 §LGPD / COPI-43):**
1. Query sanitized ANTES de cache key (CPF do usuário nunca vira parte da chave)
2. Query sanitized ANTES de log (audit_log nunca grava CPF cliente)
3. Body sanitized ANTES de virar parte do prompt LLM (`KbRagService::redactPii` via `PiiRedactor` canônico — Modules/Jana/Services/Privacy/PiiRedactor.php)

## Próximos passos canônicos

1. **Agent A entrega Models + permissions + provider registration** (ONDA 1 pendente)
   - Trait `Searchable` em KbNode
   - `kb.ai.ask` em permissions.php
   - `$this->commands([KbReindexCommand::class])` no provider
2. **Wagner / Devops configura embedder Meilisearch** (admin Meilisearch CT 100)
3. **Migrations + seeders rodam** (`php artisan migrate && php artisan db:seed --class=KbBridgeFromMcpSeeder`)
4. **Reindex inicial:** `php artisan kb:reindex --business=1 --dry-run` → conferir count → `--business=1` real
5. **Smoke test manual** (curl ou Postman):
   ```bash
   curl -X POST https://oimpresso.com/kb/ai/ask \
     -H "Cookie: oimpresso_session=..." \
     -H "Content-Type: application/json" \
     -d '{"query":"qual ADR rege multi-tenant Tier 0?"}'
   ```
   Esperado: answer cita "ADR 0093" + sources[] inclui kb_node com slug `0093-*`.
6. **Pest tests biz=1 + cross-tenant biz=99** (Agent F próxima sessão OR sucessor)
7. **Agent B/E plugar frontend** (`Pages/kb/Index.tsx`) nos endpoints
8. **Monitorar custo daily** via `SELECT business_id, SUM(custo_brl) FROM mcp_audit_log WHERE endpoint LIKE 'kb.ai.%' GROUP BY business_id`

## Estado MCP no momento do fechamento

> ⏳ Não foi feito MCP-first checklist pré-handoff (sou subagent, não sessão principal). Parent agent (Wagner ou Claude raiz) deve rodar `brief-fetch` antes de commit/PR.

## Notas de aderência ao escopo

- ✅ Apenas paths permitidos foram tocados (lista §"PODE criar" do prompt)
- ✅ Zero git ops (nenhum `git add/commit/push`)
- ✅ Reusa `Modules/Jana/Ai/Agents/KbAnswerAgent` — não criou provider novo
- ✅ `business_id` parâmetro EXPLÍCITO em todos os métodos públicos do service
- ✅ PII redacted via `Modules/Jana/Services/Privacy/PiiRedactor`
- ✅ Custo logado em `mcp_audit_log` (append-only via `McpAuditLog::registrar`)
- ✅ Citações obrigatórias — `RagResult::notFound()` factory quando 0 sources
- ✅ Idempotência opcional via header `Idempotency-Key`
- ✅ Restrição Tier 0 IRREVOGÁVEL: `assertBusinessId` lança se ≤0
- ✅ Bloco `// ============= IA RAG (Agent F · ONDA 4) =============` no FINAL do routes.php (não tocou o que está acima)
- ⚠️ Não rodei `php artisan kb:reindex` (instruído NÃO fazer — só criei o command)
- ⚠️ Não rodei `composer install` (instruído NÃO fazer — usei só o que tem)
- ⚠️ Não criei Pest tests (escopo era código + RUNBOOK — tests ficam pra próxima sessão)
