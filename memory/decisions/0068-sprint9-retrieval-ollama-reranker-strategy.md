---
slug: 0068-sprint9-retrieval-ollama-reranker-strategy
number: 68
title: "Sprint 9 — Estratégia retrieval: Ollama embedder + reranking + documentTemplate fix"
type: adr
status: rascunho
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: "2026-05-04"
decided_by: [W]
module: copiloto
supersedes: []
superseded_by: []
related: [0067-sprint8-mcp-memory-document-searchable-retrieval, 0037-roadmap-evolucao-tier-7-plus, 0053-mcp-server-governanca-como-produto, 0036-replanejamento-meilisearch-first]
tags: [retrieval, meilisearch, ollama, reranker, ragas, eval, copiloto]
---

# ADR 0068 — Sprint 9: Ollama embedder + reranking para superar baseline 0.72

## Status

Aceito — 2026-05-04 (Sprint 9 executado — resultados documentados abaixo)

## Resultados Sprint 9 (2026-05-04)

### Score RAGAS — evolução

| Configuração | Score | vs baseline 0.72 |
|---|---|---|
| Baseline grep (original) | 0.72 | referência |
| Sprint 8 MySQL FT | 0.66 | -0.06 |
| Sprint 8 Hybrid (nomic ratio=0.5) | 0.66 | -0.06 |
| Sprint 9 nomic ratio=0.7 | 0.158 | ⛔ -0.562 |
| Sprint 9 nomic ratio=0.1 | 0.388 | -0.332 |
| Sprint 9 nomic ratio=0.0 | 0.517 | -0.203 |
| Sprint 9 MySQL FT bypass | **0.700** | **-0.02** |

### Diagnósticos críticos Sprint 9

#### Problema 1 — nomic-embed-text PT-BR: embeddings near-idênticos
`nomic-embed-text:137M` gera cosine similarity ~0.97 para TODOS os documentos
em queries PT-BR. O modelo foi treinado primariamente em inglês. Semantic search
com esse modelo torna-se aleatório para o corpus, destruindo o score.

#### Problema 2 — Meilisearch BM25 vs MySQL FT NATURAL LANGUAGE
Mesmo com `semanticRatio=0.0` (sem semantic), Meilisearch BM25 ranqueia PIOR
que MySQL FT NATURAL LANGUAGE MODE para queries PT-BR longas.
Causa: CHANGELOG é um documento muito longo com alta frequência de termos do
projeto → BM25 satura incorretamente. MySQL FT NATURAL LANGUAGE usa IDF puro,
penalizando documentos com muitas ocorrências de termos raros.
Resultado: Meilisearch keyword retorna CHANGELOG antes de ADR 0066/0065.

#### Problema 3 — Scout observer bypassava checksum
`$doc->update(['indexed_at' => now()])` no branch "sem mudança" do
`IndexarMemoryGitParaDb` disparava Scout observer via evento Eloquent `updated`,
forçando Ollama a re-embedar 383 docs a cada `mcp:sync-memory`. Fix: wrapping
em `McpMemoryDocument::withoutSyncingToSearch()`.

### Fixes implementados Sprint 9

1. **`IndexarMemoryGitParaDb.php`**: `withoutSyncingToSearch()` no branch sem
   mudança — checksum git_sha agora funciona de verdade para evitar re-embedding.
2. **`McpMemoryDocument::toSearchableArray()`**: frontmatter YAML stripping
   (`preg_replace`) antes de gerar `content_excerpt` — ADRs não geram vetores
   idênticos baseados em YAML estrutural.
3. **`EvalRagasBaselineCommand.php`**: `--semantic-ratio` option + bypass quando
   ratio < 0.25 (usa MySQL FT direto, que é mais preciso que BM25 para corpus PT-BR).

### Estado atual infra (2026-05-04 fim de Sprint 9)

- Meilisearch v1.43.0: ✅ em prod (CT 100 Docker)
- Ollama nomic-embed-text: ✅ configurado como embedder `nomic_local`
- `mcp_memory_documents`: ✅ 383 docs indexados com `content_excerpt` sem frontmatter
- Score recuperado: **0.700** (MySQL FT path, baseline era 0.72)
- Score com semantic: 0.158-0.388 (inutilizável com nomic-embed-text PT-BR)

### Sprint 9b ENTREGUE (2026-05-04) — qwen3-embedding:0.6b

CT 100 é CPU-only, então 4b foi descartado (proibitivo). Validado 0.6b:

**Smoke test cosine PT-BR:**
- 3 ADRs distintas → cosine **0.55** entre elas (vs nomic ~0.97 uniforme) ✓ semantic discrimina

**Eval matrix (qwen3 + stopwords PT-BR + localizedAttributes):**
| ratio | Score RAGAS |
|---|---|
| 0.4 | 0.637 |
| 0.5 | 0.642 |
| **0.6** | **0.692** ← vencedor |
| 0.0 (MySQL FT bypass) | 0.700 (comparativo) |

**Decisão:** semanticRatio=0.6 + qwen3_local em prod. Score em par com MySQL FT — ganho real fica para reranker (US-COPI-087, Cycle 02).

**Configs aplicadas:**
- Meilisearch embedder `qwen3_local` (model: qwen3-embedding:0.6b, dim: 1024)
- Stopwords PT-BR (97 palavras canônicas)
- localizedAttributes `[{locales: [por], attributePatterns: [*]}]`
- `Modules/Copiloto/Config/config.php` → defaults: ratio=0.6, embedder=qwen3_local

### Próximo passo — Sprint 9c (Cycle 02, US-COPI-087)

Pesquisa estado da arte mai/2026 (documentada em
[`memory/requisitos/Jana/RETRIEVAL-ESTADO-ARTE-2026-05.md`](../requisitos/Copiloto/RETRIEVAL-ESTADO-ARTE-2026-05.md))
identifica `qwen3-embedding` (Alibaba) como #1 MTEB multilingual Jun/2025,
com 100+ idiomas e PT-BR explícito documentado.

```bash
# CT 100, container Ollama:
ollama pull qwen3-embedding:4b   # RECOMENDADO: 3.5GB VRAM, MTEB ~68
# Alternativa se VRAM apertada:
ollama pull qwen3-embedding:0.6b  # 1.5GB VRAM, MTEB ~65
```

**Pipeline recomendado pra ~400 docs PT-BR (100% local):**
```
Query → BM25 + dense (qwen3-embedding:4b) → RRF Meilisearch nativo
      → cross-encoder reranker (qwen3-reranker:0.6b ou bge-reranker-v2-m3)
      → top-3 → LLM
```

**Expectativa de ganho RAGAS:**
- BM25 only (atual): 0.700
- BM25 + qwen3 dense (semantic_ratio=0.6): **0.80-0.84**
- BM25 + dense + reranker: **0.85-0.90**

Adicionalmente: configurar stopwords PT-BR + localizedAttributes no Meilisearch
(ganho BM25 standalone +5-10%).

## Contexto

Sprint 8 (ADR 0067) entregou a infraestrutura Meilisearch hybrid mas **não superou** o baseline
grep de 0.72. Score final: **0.66** — empate técnico com MySQL FULLTEXT.

Causa-raiz identificada: `documentTemplate` do embedder OpenAI é só metadados
(`title | slug | type | module`), sem conteúdo textual. Resultado: vetores gerados sem semântica
real do documento → hybrid search não melhora sobre keyword-only.

Diagnóstico adicional (2026-05-04):
- Meilisearch em prod na v1.10.3 (out/2024) — latest é v1.43.0 (mai/2026, +33 versões)
- CT 100 tem `nomic-embed-text:137M` no Ollama (bi-encoder multilingual, F16, 274MB)
- Nenhum cross-encoder reranker instalado ainda

## Decisão

### Prioridade 1 — `documentTemplate` com excerpt do conteúdo

Adicionar `content_excerpt` em `toSearchableArray()`:
```php
'content_excerpt' => mb_substr($this->content_md ?? '', 0, 400),
```

Template no embedder:
```
"{{doc.title}}. {{doc.content_excerpt}}"
```

Antes: o embedder via ia com `title | slug | type | module` (~20 tokens). Com excerpt:
~100 tokens por doc — bem dentro do limite do nomic-embed-text (8192 tokens) e do
text-embedding-3-small.

**Impacto esperado:** `kb-mora` e `reverb-status` devem recuperar, pois o conteúdo
semântico real estará no vetor.

### Prioridade 2 — Switch embedder: OpenAI → Ollama `nomic-embed-text`

Meilisearch suporta Ollama como fonte de embedder (fonte: `ollama`):

```json
{
  "nomic_local": {
    "source": "ollama",
    "url": "http://192.168.0.50:11434/api/embed",
    "model": "nomic-embed-text",
    "documentTemplate": "{{doc.title}}. {{doc.content_excerpt}}"
  }
}
```

**Vantagens:**
- Custo: **zero** (local, substituindo ~$0.0001/query do OpenAI text-embedding-3-small)
- LGPD: dados não saem da rede interna
- nomic-embed-text é rankeado comparável ao OpenAI ada-002 em benchmarks MTEB

**Configuração no `copiloto.memoria.meilisearch.embedder`:** `nomic_local`

**Pré-requisito:** CT 100 acessível do container Meilisearch na rede Docker. Endereço:
`192.168.0.50:11434` (host Docker). Alternativa: `host.docker.internal:11434` se suportado.

### Prioridade 3 — Cross-encoder reranking via Ollama (pós-fetch)

Instalar modelo reranker no CT 100:
```bash
ollama pull bge-reranker-v2-m3
# Ou: bge-reranker-large (maior, mais preciso)
# Ou: jina-reranker-v2-base-multilingual (multilingual, menor)
```

Fluxo pós-implementação:
```
Meilisearch hybrid (fetch top-10)
  → Ollama /api/rerank: bge-reranker-v2-m3 (cross-encoder, query × cada doc)
  → sort por score descendente
  → take(3) → LLM answer
```

**Custo:** zero. **Latência:** +100-200ms/query (aceitável em CLI eval; para chat real-time
seria bloqueante — usar assíncrono ou cache de reranking).

### Prioridade 4 — Meilisearch v1.43.0 upgrade

Upgrade no CT 100 (Docker). Processo:
1. `POST /dumps` → aguardar success (safety net)
2. Atualizar imagem: `getmeili/meilisearch:v1.43.0`
3. `docker compose pull && docker compose up -d`
4. Verificar: `curl /version`
5. Re-importar índice: `php artisan scout:import McpMemoryDocument` (se necessário)

v1.43.0 traz: melhorias de ranking híbrido, melhor integração com embedders externos,
suporte melhorado a filtros compostos.

## Parâmetro de teste

```bash
# Pipeline ADR: 8 perguntas canônicas, alvo > 0.72 (superar baseline original)
php artisan eval:ragas-baseline --pipeline=adr --category=adr

# Meta Sprint 9: score ADR > 0.72
# Meta ambiciosa: > 0.80 com cross-encoder reranking
```

Métricas adicionais a monitorar:
- **Latência retrieval:** tempo do `retrieveKbContext()` < 500ms para Meilisearch, < 700ms com reranker
- **Custo/run:** target $0.00 em embeddings (Ollama local)
- **Recall@3:** % de perguntas onde o doc correto está no top-3 (pré-judge)

## Parâmetro de teste — sistema de memória automatizado

Para validar automação de captura de sessão:

```bash
# Golden set: 20 perguntas sobre fatos de sessões passadas
# Exemplo: "Qual versão Meilisearch em prod?", "Qual score Sprint 8?"
# Categoria: infra-recall
php artisan eval:ragas-baseline --pipeline=adr --category=infra-recall
# Meta: recall@3 > 0.80
```

## Ordem de execução Sprint 9

```
[ ] 1. Meilisearch upgrade v1.43.0 (CT 100 — Wagner via Proxmox console)
[ ] 2. Switch embedder para nomic_local (Ollama) no Meilisearch settings
[ ] 3. Re-importar índice com novo content_excerpt + template
[ ] 4. Medir baseline com nomic_local (sem reranker)
[ ] 5. pull bge-reranker-v2-m3 no Ollama
[ ] 6. Implementar reranking em retrieveKbContext()
[ ] 7. Medir score final — meta > 0.72
```

## Automação de memória — estado da arte e roadmap

### Estado atual (manual)
Fim de sessão → Claude + Wagner atualizam CURRENT.md + handoff + criam session log manualmente.
Git commit → webhook → DB sync (automático).

### Estado-da-arte a implementar

**Camada A — Captura contínua (hooks Claude Code):**
- `PostToolUse` hook: quando Claude cria ADR ou decisão relevante, appenda a buffer
- Arquivo: `.claude/session-buffer.md` (gitignored, só local)

**Camada B — Extração ao fechar sessão:**
- Hook `Stop` → script Python/PHP → gpt-4o-mini extrai fatos estruturados
- Output: `memory/sessions/YYYY-MM-DD-{slug}.md` auto-commitado
- Prompt: "Extraia: decisões, fatos de infra, bugs encontrados, next steps"

**Camada C — Consolidação periódica:**
- `php artisan memory:consolidate` (semanal, Laravel Scheduler)
- LLM consolida N session logs → atualiza `memory/08-handoff.md`
- `mcp:sync-memory` → webhook → DB cache atualizado

**Ferramentas:** Claude Code hooks + gpt-4o-mini + `mcp:sync-memory` existente.

## Consequências

### Positivas
- Embeddings gratuitos (Ollama local) — elimina custo variável por query
- Cross-encoder reranking > bi-encoder por definição (vê query + doc juntos)
- documentTemplate com excerpt resolve causa-raiz do 0.66

### Negativas / Riscos
- Ollama no CT 100 precisa ser acessível do container Meilisearch (configuração de rede)
- bge-reranker-v2-m3 adiciona ~150MB de VRAM/RAM no CT 100
- Latência reranking pode ser inaceitável para chat real-time (avaliar cache)

## Alternativas descartadas

- **Manter OpenAI embedder**: funciona mas custa por query e viola LGPD princípio de minimização
- **Aumentar semanticRatio só**: sem conteúdo no template, ratio não ajuda
- **Usar grep local como baseline**: não escala com volume, não governa via DB
