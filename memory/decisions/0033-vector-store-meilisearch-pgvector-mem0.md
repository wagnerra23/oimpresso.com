# ADR 0033 — Vector store / search backend do oimpresso: pgvector vs Meilisearch+Scout vs Mem0

**Status:** ✅ Aceita
**Data decisão:** 2026-04-26
**Autor:** Wagner (dono/operador)
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`, pesquisa profunda solicitada — "procure o pgvector e o meilisearch... scout do laravel")
**Relacionado:**
- [ADR 0026 — Posicionamento "ERP gráfico com IA"](0026-posicionamento-erp-grafico-com-ia.md)
- [ADR 0031 — `MemoriaContrato` + Mem0RestDriver default](0031-memoriacontrato-mem0-default.md) (este ADR adiciona drivers alternativos)
- [ADR 0032 — Vizra ADK + Prism PHP](0032-vizra-adk-prism-php-orquestracao.md)
- [ADR 0034 — Laravel AI ecosystem 2026](0034-laravel-ai-sdk-oficial-boost-mcp.md)

---

## Contexto

ADR 0031 estabeleceu `MemoriaContrato` com `Mem0RestDriver` como default. Pesquisa profunda em 2026-04-26 mapeou **3 backends viáveis** pra vector store / memória semântica em Laravel:

### Opção 1 — `pgvector` (PostgreSQL extension)
- Laravel 12+ tem `whereVectorSimilarTo()` nativo, Laravel 13 expandiu suporte
- Driver `pgvector/pgvector-php` + pacote `pgvector-laravel-scout`
- Auto-update de embeddings via Eloquent Observers
- **Exige PostgreSQL.** Stack atual oimpresso é **MySQL** (Laragon dev + Hostinger prod) — migração custaria semanas + risco regressão.
- Refs: [Laravel News: Semantic Relationships](https://laravel-news.com/laravel-related-content), [Laravel 13 whereVectorSimilarTo guide](https://sadiqueali.medium.com/laravel-13-has-native-semantic-search-wherevectorsimilarto-pgvector-the-complete-guide-f0c866216390)

### Opção 2 — Meilisearch self-hosted (binário Go) + Laravel Scout
- Laravel Scout driver oficial Meilisearch
- **Hybrid search** (full-text + semantic) com semantic_ratio configurável — melhor que vector puro pra cenários como busca de produto, autocomplete, e recall do Copiloto
- **Built-in vector store + RAG**, conversational search beta
- <50ms p95 latência. Aceita embeddings via OpenAI ou locais. 1.19 EE com sharding horizontal.
- Self-hosted = **zero custo recorrente recurring**, single binário Go (~50MB) fácil de hospedar ao lado do MySQL no Hostinger ou em VPS dedicado
- Não exige trocar de DB. Não exige migração de schema. Funciona como **side-car** indexando rows do MySQL via Scout observers.
- Refs: [Meilisearch Laravel Scout docs](https://meilisearch.com/docs/guides/laravel_scout), [What is vector search](https://www.meilisearch.com/blog/what-is-vector-search), [Hybrid Search](https://www.meilisearch.com/solutions/hybrid-search)

### Opção 3 — Mem0 REST managed (já decidido em ADR 0031)
- Mem0 cloud managed, multi-tier (vector+graph+key-value), -91% latência vs full-context
- Custo recorrente $25-300/mês dependendo de volume
- Zero infra extra
- Limitações: dependência de internet, multi-tenancy via `user_id` workaround, cap de memórias no plano starter (~10k)

---

## Decisão

**`MemoriaContrato` ganha 2 drivers adicionais além do `Mem0RestDriver` default (ADR 0031):**

| Driver | Quando ativar | Status |
|---|---|---|
| `Mem0RestDriver` | **Default** em produção (ADR 0031). Setup zero, custo recorrente $25-300/mês | A implementar (sprints 4-5) |
| `MeilisearchDriver` | Quando Mem0 ficar caro (>$300/mês) OU exigir mais de 10k memórias OU Wagner quiser zerar custo recorrente. Lê/escreve via Laravel Scout abstraindo o cliente Meilisearch | A implementar (sprint 8-10, condicional) |
| `NullMemoriaDriver` | Dev/dry_run/CI. Devolve fixtures, não chama rede | A implementar (sprint 4) |
| `PgvectorDriver` | **REJEITADO pra v1** — exige migração MySQL→Postgres. Reavaliar se em algum momento tivermos um cliente que justifique stack Postgres | Não planejado |

### Schema (pra `MeilisearchDriver`)

```php
// database/migrations/2026_xx_xx_create_copiloto_memoria_facts_table.php
Schema::create('copiloto_memoria_facts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('business_id')->constrained();
    $table->foreignId('user_id')->constrained('users');
    $table->text('fato');
    $table->json('metadata')->nullable();
    $table->timestamp('valid_from')->useCurrent();
    $table->timestamp('valid_until')->nullable();
    $table->timestamps();
    $table->softDeletes();   // LGPD opt-out — esquecer = soft delete
    $table->index(['business_id', 'user_id']);
});
```

Eloquent model `CopilotoMemoriaFato` usa `Laravel\Scout\Searchable`. Toda escrita auto-indexa em Meilisearch via observer. `MeilisearchDriver::buscar()` faz `CopilotoMemoriaFato::search($query)->where('business_id', $bizId)->where('user_id', $uid)->take($topK)`. Hybrid search via Scout.

### Configuração Meilisearch (sprint 8 quando ativar)

```bash
# Hostinger / VPS — self-hosted single binary
curl -L https://install.meilisearch.com | sh
./meilisearch --master-key="$MEILI_MASTER_KEY" --http-addr=127.0.0.1:7700 &

# Laravel
composer require laravel/scout meilisearch/meilisearch-php http-interop/http-factory-guzzle
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=...
```

### Search engine pro **resto** do oimpresso (não-Copiloto)

Independente do `MemoriaContrato`, **Laravel Scout database driver** (`SCOUT_DRIVER=database`) deve ser o default pra busca full-text em features que não exigem semantic — produtos, contatos, vendas. Migrar pra Meilisearch/Typesense **só** quando volume justificar (>100k rows com latência ruim em LIKE).

| Feature | Engine sugerido | Por quê |
|---|---|---|
| Busca de produto/cliente/venda no UltimatePOS | Scout database driver (default) | Volume típico < 100k rows; LIKE é OK |
| Memória semantica do Copiloto | Mem0 REST (default) ou Meilisearch self-hosted | Exige semantic + dedup + temporal |
| Knowledge base / docs internos (futuro MemCofre v2) | Meilisearch self-hosted | Volume cresce; hybrid search vence LIKE |
| Search no produto final ROTA LIVRE (e-commerce) | Meilisearch (se viver) ou Algolia (se contratual) | UX de instant-search exige <50ms |

---

## Justificativa

- **Stack MySQL é restrição forte.** pgvector exige PostgreSQL — migrar custaria semanas + risco regressão sem retorno claro. Reavaliar só se cliente comercial exigir.
- **Mem0 default = velocidade + Tier 6-7 LongMemEval imediato** com $25-300/mês. ADR 0031 já tomou essa decisão.
- **Meilisearch como fallback/upgrade** dá saída de longo prazo sem trocar DB: hybrid search, OSS, single binário, escala via sharding 1.19 EE. Custo de implementar é médio (~3 sprints quando ativarmos).
- **Scout database driver** pro resto da app evita over-engineering — não há nem 100k rows na maioria das tabelas; LIKE com índice resolve.

## Consequências

✅ Caminho claro pra reduzir custo Mem0 quando volume crescer (sprint 8-10 = `MeilisearchDriver`).
✅ Decisão de **não migrar pra PostgreSQL** documentada — evita iniciativas concorrentes.
✅ Search engine canônico pro resto do oimpresso (Scout database) está formalizado.
✅ Knowledge base futuro do MemCofre tem destino arquitetural (Meilisearch self-hosted).
⚠️ Operar Meilisearch self-hosted exige process supervisor (systemd/supervisor) em produção. Hostinger compartilhado tem limitação — pode exigir VPS dedicado quando ativar.
⚠️ Hybrid search Meilisearch precisa de embedder configurado (OpenAI ou local) — adiciona dep transitiva.
⚠️ Multi-tenancy em Meilisearch via filter (`business_id=...`) é seguro mas exige validação de input em queries — sprint 8 inclui auditoria.

## Alternativas consideradas

- **Migrar oimpresso pra PostgreSQL pra usar pgvector nativo:** rejeitado — custo > benefício hoje. UltimatePOS usa MySQL há anos; mover é projeto isolado, não escopo de Copiloto. Reavaliar em 12-18m se demanda comercial existir.
- **Typesense em vez de Meilisearch:** considerado. Typesense também tem vector search nativo + Scout driver, e é "lightning-fast". Meilisearch ganha em hybrid search documentado + Laravel Sail builtin + comunidade Laravel maior. Mantém Typesense como segunda alternativa documentada.
- **Algolia:** rejeitado — paid-only sem tier OSS, escala cara depois do free tier (~10k records).
- **Pinecone/Qdrant/Weaviate gerenciado:** rejeitado pra v1 — todos exigem REST igual ao Mem0 mas sem features específicas (Mem0 tem dedup automático, summary, conflict resolution). Se for pagar managed, Mem0 entrega mais.
- **Construir vector store em PHP nativo do zero:** rejeitado — embedding similarity em PHP puro é lento e sem índice. Reinventa roda.

## Refs externas

- [Laravel 13 Search docs](https://laravel.com/docs/13.x/search) — full-text + semantic + vector
- [Laravel News: Semantic Relationships using pgvector](https://laravel-news.com/laravel-related-content)
- [Hafiz.dev: Laravel Search in 2026](https://hafiz.dev/blog/laravel-search-in-2026-full-text-semantic-and-vector-search-explained)
- [Building Hybrid Search System with Laravel + OpenAI + PostgreSQL](https://brudtkuhl.com/blog/building-hybrid-search-system-laravel-ai-postgresql/)
- [Meilisearch Laravel Scout guide](https://meilisearch.com/docs/guides/laravel_scout)
- [Meilisearch: What is vector search](https://www.meilisearch.com/blog/what-is-vector-search)
- [Meilisearch Hybrid Search](https://www.meilisearch.com/solutions/hybrid-search)
- [pgvector-php GitHub](https://github.com/pgvector/pgvector-php)
- [pgvector for Laravel Scout (Ben Bjurstrom)](https://benbjurstrom.com/pgvector-for-laravel-scout)
- [Laravel Scout official docs](https://laravel.com/docs/13.x/scout)

## Roadmap concreto

- **Sprints 4-5** (já planejados em ADR 0031): `Mem0RestDriver` ativo em produção. Coletar métricas: custo/mês, queries/mês, top-k satisfaction.
- **Sprint 8-10** (condicional): se Mem0 mensal >$300 OU >10k memórias OU Wagner pedir, implementar `MeilisearchDriver`. Self-host Meilisearch em VPS dedicado (Hostinger compartilhado provavelmente não suporta long-running daemon). Migrar fatos de Mem0 pra Meilisearch.
- **Sempre:** Scout database driver é default pro resto do oimpresso. Não migrar features para Meilisearch sem volume real.
