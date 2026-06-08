# ADR TECH-0001 (LaravelAI) · Embeddings OpenAI `text-embedding-3-small` (com fallback local)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Knowledge Graph + RAG precisa de **embeddings** (vetores 1536d ou 768d) para:
- Busca semântica em ADRs
- Busca de evidências similares
- Match de pergunta com fonte

3 opções:

| Opção | Custo | Qualidade | Latência | Privacy |
|---|---|---|---|---|
| **OpenAI text-embedding-3-small** | $0.02 / 1M tokens (~$30/mês tenant médio) | 1536d, alta qualidade | ~200ms | Dados → OpenAI |
| **OpenAI text-embedding-3-large** | $0.13 / 1M tokens | 3072d, qualidade superior | ~250ms | Mesmo |
| **sentence-transformers local** (Ollama / HF) | Zero ($ infra) | 384-768d, boa pra muitos casos | <50ms | 100% local |
| **Cohere embed v3** | $0.10 / 1M tokens | 1024d, multi-lingual | ~200ms | Cohere |

Custo OpenAI exemplo:
- 100 ADRs × 500 tokens = 50k tokens / mês
- 1k queries × 30 tokens = 30k tokens / mês
- Total ~80k tokens × $0.02/1M = **$0.0016/mês** (insignificante)
- Com escala: 50 ADRs + 10k queries = ainda < $1/mês

Vector storage:
- 1536d × 4 bytes = 6KB por embedding
- 1k embeddings = 6MB
- Trivial em pgvector ou JSON column

## Decisão

**MVP: OpenAI `text-embedding-3-small` como default.**

Razões:
- Qualidade superior comprovada em benchmarks BR (PT-BR ranks bem)
- Custo trivial (~$1/mês em volume típico)
- Setup simples: 1 API key
- Multi-modal preparation (futuro: OpenAI vision pra screenshots)
- Latência aceitável (~200ms), cache reduz a maioria

**Fallback `sentence-transformers` local** quando:
- Tenant Enterprise paranoid → "queremos zero dado externo"
- OpenAI fora por > 5 min (circuit breaker)
- Custo escalar mal (improvável até 10k+ tenants)

## Consequências

**Positivas:**
- Qualidade alta out-of-the-box
- Custo previsível e barato
- Sync trivial (regenerar embedding = 1 API call)
- Multi-tenant: cada business pode escolher provider via config

**Negativas:**
- Dependência externa: OpenAI down = embeddings novos param (cached funcionam)
- Privacy: ADRs do tenant viram tokens no OpenAI (opt-out training default em 2026)
- Custo escala se uso explode (mitigado por cache + quota)

## Pattern obrigatório

```php
interface EmbeddingProvider {
    public function embed(string $text): array;       // retorna vector
    public function dimension(): int;
    public function provider(): string;
    public function model(): string;
}

class OpenAIEmbeddingProvider implements EmbeddingProvider {
    public function embed(string $text): array {
        $cacheKey = "embed:openai:" . hash('sha256', $text);
        return Cache::remember($cacheKey, 7 * 24 * 3600, fn() =>
            $this->client->embeddings->create([
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ])->data[0]->embedding
        );
    }
    public function dimension(): int { return 1536; }
    public function provider(): string { return 'openai'; }
    public function model(): string { return 'text-embedding-3-small'; }
}

class LocalEmbeddingProvider implements EmbeddingProvider {
    // Ollama via HTTP local; sentence-transformers
}
```

Cache de 7 dias é generoso — texto raramente muda; cache miss é raro.

## Cache invalidação

- ADR muda → hash do source muda → cache key novo (não invalida explicitamente)
- Old cached entries expiram em 7 dias (acceptable lag)
- Cache backend: Redis (já temos via Horizon)

## Compliance e privacy

- **OpenAI default 2026**: dados não usam pra treinamento (a menos que opt-in explícito)
- **Tenant pode opt-out**: config `embedding_provider = local` força sentence-transformers
- **PII filtrada**: PiiMaskService aplicado antes de chamar OpenAI (R-AI-004)
- **Audit log**: cada chamada API log em `ai_query_log` (incluindo tokens used)

## Tests obrigatórios

```php
test('embedding cached por hash do texto', function () {
    Http::fake();
    $p = new OpenAIEmbeddingProvider;
    $p->embed('texto teste');
    $p->embed('texto teste');  // 2ª chamada usa cache
    Http::assertSentCount(1);
});

test('embedding diferente para textos diferentes', function () {
    Http::fake([/* mock */]);
    $p = new OpenAIEmbeddingProvider;
    $a = $p->embed('texto A');
    $b = $p->embed('texto B');
    expect($a)->not->toEqual($b);
});

test('fallback local quando OpenAI 5xx', function () {
    Http::fake([/* 503 */]);
    $service = new EmbeddingService(/* both providers */);
    $vec = $service->embed('teste');
    expect($vec)->toBeArray();  // veio do local
});
```

## Decisões em aberto

- [ ] `text-embedding-3-large` (3072d) vale upgrade? Provavelmente não pra MVP
- [ ] Multi-lingual: usar `cohere/embed-multilingual-v3.0` se atender clientes não-BR?
- [ ] Reembedding em batch quando model muda (ex: 3-small → 3-large)? Job migration script

## Alternativas consideradas

- **Local-only (Ollama)** — rejeitado MVP: setup operacional pra Enterprise; qualidade menor
- **Cohere** — rejeitado: stack OpenAI já presente em outros lugares; consolidação faz sentido
- **HuggingFace Inference API** — rejeitado: latência variável; preço similar OpenAI
- **Self-hosted via Modal/Replicate** — overkill pra MVP

## Referências

- OpenAI Embeddings docs (https://platform.openai.com/docs/guides/embeddings)
- pgvector benchmarks
- ARQ-0001 (storage do grafo)
- R-AI-004 (PII masking)
