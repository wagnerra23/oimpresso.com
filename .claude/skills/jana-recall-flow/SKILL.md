---
name: memoria-recall-flow
description: Use ao tocar Modules/Jana/Services/Memoria/, ContextSnapshotService, recall hybrid (Meilisearch + HyDE + reranker), MCP memory sync (git→DB→Scout), ou tabela copiloto_memoria_facts/memoria_metricas. Carrega contrato MemoriaContrato + 3 ângulos faturamento (ADR 0052) + os 14 gotchas catalogados em RETRIEVAL-GOTCHAS.md (Sprint 9). Skill mais específica que copiloto-arch — ativa só em retrieval/recall, não em IA-em-geral.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
tier: B
parent_adr: 0095
---

# Memória do Copiloto — fluxo de recall canônico

## Quando ativa

- Edit/Write em `Modules/Jana/Services/Memoria/` (drivers, HyDE, reranker, cache)
- Edit/Write em [`ContextSnapshotService.php`](Modules/Jana/Services/ContextSnapshotService.php)
- Mexer em `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` ou `McpSyncMemoryCommand`
- Migração / query em `copiloto_memoria_facts` ou `copiloto_memoria_metricas` ou `mcp_memory_documents`
- Mudar config Scout / Meilisearch / Ollama / qwen3-embedding
- Pergunta com termos: "recall", "hybrid search", "embedding", "HyDE", "reranker", "RAGAS", "negative cache", "ContextoNegocio"

## Para arquitetura geral do Copiloto, ver skill `copiloto-arch`

Esta skill é o **sub-fluxo de recall**. Pra stack inteira (laravel/ai, Agents, métricas, OTel), ative `copiloto-arch` em paralelo.

## Pipeline de recall (não pular nenhum estágio)

```
query → NegativeCache.miss? → HyDE.expandir → Meilisearch hybrid (semanticRatio 0.7) → LlmReranker → top-K
                  │
                  └── hit? retorna [] sem chamar Meilisearch (economia)
```

| Estágio | Service | Por quê existe |
|---|---|---|
| 1. Negative cache | [`NegativeCacheService`](Modules/Jana/Services/Memoria/NegativeCacheService.php) | Query anterior retornou 0; evita re-buscar mesmo termo em janela TTL |
| 2. HyDE | [`HydeQueryExpander`](Modules/Jana/Services/Memoria/HydeQueryExpander.php) | Gera "fato hipotético" → embedda; sobe recall@5 ~15% |
| 3. Hybrid | [`MeilisearchDriver`](Modules/Jana/Services/Memoria/MeilisearchDriver.php) | BM25 + vector via callback Scout (`semanticRatio` configurável) |
| 4. Rerank | [`LlmReranker`](Modules/Jana/Services/Memoria/LlmReranker.php) | LLM pequeno re-ordena top-K bruto pra top-K final |
| 5. Persistência | tabela `copiloto_memoria_facts` (`Searchable + SoftDeletes`) | Append-only; `valid_until` setado = superseded |

**Ingress (sync git→DB):** [`IndexarMemoryGitParaDb`](Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php) lê `memory/*` do worktree, faz upsert em `mcp_memory_documents` com PII redactor automático, dispara Scout observer pra re-embedar. Comando wrapper: [`McpSyncMemoryCommand`](Modules/Jana/Console/Commands/McpSyncMemoryCommand.php).

## §Crítico — ContextoNegocio expõe 3 ângulos (ADR 0052)

[`ContextSnapshotService::faturamento90d()`](Modules/Jana/Services/ContextSnapshotService.php) retorna **3 valores distintos** por mês — não 1:

| Ângulo | Fonte SQL | Significado |
|---|---|---|
| `bruto` | `SUM(transactions.final_total) WHERE type='sell'` | Vendi |
| `liquido` | `bruto - SUM(... WHERE type='sell_return')` | Faturamento líquido |
| `caixa` | `SUM(transaction_payments.amount) GROUP BY paid_on` | O que entrou no caixa |

**Por que importa:** Larissa (biz=4) testou 3 perguntas distintas em prod e recebeu o MESMO valor R$ [redacted Tier 0] Causa-raiz: `faturamento90d` retornava 1 número só. Fix em commit `fac96a19`. Princípio formalizado em ADR 0052 — quando métrica admite múltiplos recortes legítimos, expor TODOS.

**BC-compat:** campo `valor` mantido como alias do `bruto`. Se for adicionar nova métrica que admite recortes (custos / lucro / inadimplência), seguir mesmo padrão.

## §Crítico — 14 gotchas catalogados

Fonte: [`memory/requisitos/Jana/RETRIEVAL-GOTCHAS.md`](memory/requisitos/Jana/RETRIEVAL-GOTCHAS.md). Cada um já queimou ≥30min. Top dos perigosos:

| # | Gotcha | Sintoma | Mitigação |
|---|---|---|---|
| 1 | Embedding model com idioma dominante errado | Cosine ~0.97 uniforme em PT-BR | Validar MTEB Multilingual ANTES de instalar; usar `qwen3-embedding:4b` ou `multilingual-e5-large-instruct` |
| 2 | Scout observer em qualquer `update()` | 383 requests Ollama a cada sync | NÃO chamar `$doc->update(['indexed_at' => now()])` no branch "sem mudança" |
| 3 | Meilisearch v1.43+ SSRF protection | Bloqueia IPs privados (Ollama na LAN) | Usar Tailscale ou whitelist explícita |
| 4 | `semanticRatio=0.0` ≠ keyword puro | Recall pior que esperado | Pra keyword puro, usar driver dedicado, não ratio 0 |
| 5 | Meilisearch BM25 sem stopwords PT-BR < MySQL FT | Recall@5 cai vs FULLTEXT NL | Configurar stopwords PT-BR no índice |
| 6 | `scout:import` re-embeda TUDO | Custo OpenAI explode | Sempre usar `mcp:sync-memory` (incremental) |
| 8 | `content_excerpt` não strippa frontmatter YAML | Excerpt mostra `---\nname: ...` em vez de conteúdo | Strippar `---...---` antes do `mb_substr` |
| 10 | Filter Meilisearch — aspas em PHP | `business_id = '4'` quebra parser | Usar aspas simples DENTRO de double-quoted PHP string |
| 13 | Tasks Meilisearch travadas em SSRF retry | Fila empacada | Verificar `meilisearch tasks list` periodicamente |

Antes de mexer em retrieval, **ler RETRIEVAL-GOTCHAS.md inteiro** — 14 itens, 5min de leitura.

## §Crítico — Ingress sync git→DB (`mcp:sync-memory`)

**Fluxo:** webhook GitHub → roda `php artisan mcp:sync-memory` no CT 100 → [`IndexarMemoryGitParaDb`](Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php) → upsert em `mcp_memory_documents` → Scout observer → embedding → Meilisearch index.

| Etapa | Onde |
|---|---|
| Source-of-truth | git (`memory/*.md`) |
| Cache governado | `mcp_memory_documents` (tabela) |
| Index buscável | Meilisearch (CT 100, FrankenPHP) |
| Quota / governança | [`QuotaEnforcer`](Modules/Jana/Services/Mcp/QuotaEnforcer.php) |
| Audit | `mcp_audit_log` (IMUTÁVEL — só INSERT) |

**Latência alvo:** webhook → tools MCP enxergarem doc novo < 60s.

**ZERO auto-mem privada (ADR 0061):** hook `block-automem.ps1` BLOQUEIA `Write/Edit` em `~/.claude/projects/*/memory/*.md`. Tudo vai pra git.

## Métricas obrigatórias (ADR 0050) — apuração diária

Tabela `copiloto_memoria_metricas` (14 colunas):

**8 obrigatórias:**
- `memoria_recall_chars` (alvo: > 100 em chat real)
- `recall_at_5` (gate: ≥ 0.80)
- `precision_at_5`
- `mrr_at_10`
- `latency_p50_ms` / `latency_p95_ms`
- `cost_token_in` / `cost_token_out`

**6 RAGAS-aligned (golden set MEM-MET-5 destrava):**
- `context_relevance`
- `groundedness`
- `answer_relevance`
- + 3 derivadas

**Apurador:** [`MetricasApurador`](Modules/Jana/Services/Metricas/MetricasApurador.php) → cron 23:55 daily via [`ApurarMetricasCommand`](Modules/Jana/Console/Commands/ApurarMetricasCommand.php) `--business=all`.

## Pegadinhas críticas

- ❌ **NÃO chamar Meilisearch sem passar por NegativeCache.** Query "vendi quanto" repetida 5×/dia = 5 chamadas inúteis se cache miss.
- ❌ **NÃO setar `semanticRatio=1.0` esperando "puro vetorial".** A camada lexical complementa em queries com nome próprio (Larissa, ROTA LIVRE) — perde precisão.
- ❌ **NÃO confiar que LLM deriva matemática que ContextoNegocio não fornece.** Princípio ADR 0052: expor todos os ângulos legítimos como dados; o LLM só precisa escolher qual citar.
- ❌ **NÃO fazer `DELETE` em `copiloto_memoria_facts`.** Append-only — usar `valid_until = now()`. Esquecer LGPD = SoftDelete (`esquecer()`).
- ❌ **NÃO testar com `RefreshDatabase`** — migrations core UltimatePOS quebram em SQLite. Usar `beforeEach` com `Schema::create` da tabela alvo.
- ❌ **NÃO mascarar PII na ponta do LLM e esquecer no log.** Sanitizar via [`LaravelAiSdkDriver::mascararDocumentos()`](Modules/Jana/Services/Ai/LaravelAiSdkDriver.php) ANTES de qualquer call (LLM, log, audit).

## Multi-tenant (skill `multi-tenant-patterns`)

Todo recall é **scoped por business_id + user_id**:
- `MeilisearchDriver::buscar(int $businessId, int $userId, ...)` — assinatura impõe scope
- Filtro Meilisearch: `business_id = X AND user_id = Y`
- Validação Pest: 1 fato de biz=4 nunca aparece em recall de biz=1

## Validação local antes de comitar mudança em retrieval

```bash
# 1. Lint
php -l Modules/Jana/Services/Memoria/MeilisearchDriver.php

# 2. Suite Copiloto inteira
vendor/bin/pest Modules/Jana/Tests/

# 3. Recall sanity check (precisa de Meilisearch + golden set)
php artisan copiloto:gabarito:avaliar --business=4 --top=5

# 4. Teste embedding model NÃO degradou (gotcha #1)
# 2 docs muito diferentes devem ter cosine < 0.9
php artisan tinker --execute="..." # ver RETRIEVAL-GOTCHAS.md §1
```

## Documentos-fonte canônicos

- [`memory/requisitos/Jana/RETRIEVAL-ESTADO-ARTE-2026-05.md`](memory/requisitos/Jana/RETRIEVAL-ESTADO-ARTE-2026-05.md) — pesquisa mai/2026 (versionar por data ao atualizar)
- [`memory/requisitos/Jana/RETRIEVAL-GOTCHAS.md`](memory/requisitos/Jana/RETRIEVAL-GOTCHAS.md) — 14 armadilhas (apenda; não remova sem ADR)
- [`memory/requisitos/Jana/MEILISEARCH-EVOLUCAO.md`](memory/requisitos/Jana/MEILISEARCH-EVOLUCAO.md) — sprint 7→9 timeline (não reescrever passado)

## ADRs canônicos (alta densidade)

- [ADR 0036](memory/decisions/0036-replanejamento-meilisearch-first.md) — Meilisearch first; benchmark 95.2% LongMemEval; 5 triggers reavaliar
- [ADR 0037](memory/decisions/0037-roadmap-evolucao-tier-7-plus.md) — Roadmap memória (Tier 7+)
- [ADR 0049](memory/decisions/0049-camadas-memoria-agente-fase-por-fase.md) — 6 camadas memória (Working/ConvHist/Episodic/Semantic/Procedural/Reflective) + gate Recall@3>0.80
- [ADR 0050](memory/decisions/0050-metricas-obrigatorias-memoria-table.md) — 8 métricas obrigatórias + tabela `copiloto_memoria_metricas`
- [ADR 0051](memory/decisions/0051-schema-proprio-adapter-otel-genai.md) — schema próprio + adapter sobre `Laravel\Ai\Contracts\ConversationStore` + OTel GenAI
- [ADR 0052](memory/decisions/0052-contextonegocio-expor-multiplos-angulos.md) — ContextoNegocio múltiplos ângulos
- [ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — zero auto-mem privada (todo conhecimento → git → MCP)
