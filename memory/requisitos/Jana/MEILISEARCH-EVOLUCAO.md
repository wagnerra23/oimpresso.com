---
id: requisitos-jana-meilisearch-evolucao
---

# Evolução Meilisearch no Projeto oimpresso

> Histórico canônico da jornada Meilisearch — do zero até hybrid search com Ollama.
> Atualizado em: 2026-05-04

---

## Linha do tempo

### Sprint 7 — 2026-04-29 (ADR 0037 / MEM-P2-1)

**Contexto:** Baseline RAGAS registrado. Retrieval era grep no filesystem
(`memory/decisions/*.md`) + keyword match simples. Score: **0.72** (8 perguntas ADR).

**Limitações identificadas:**
- Não ranqueia por relevância semântica (só substring match)
- Falha em termos hifenizados (`usuario-360`, `reverb-status`)
- Não aproveita `mcp_memory_documents` (DB cache governado, 383 docs)

---

### Sprint 8 — 2026-05-04 (ADR 0067)

**O que foi feito:**
- `McpMemoryDocument` ganhou `Laravel\Scout\Searchable` (adicionado `searchableAs()`,
  `toSearchableArray()`, `shouldBeSearchable()`)
- `shouldBeSearchable()`: exclui `superseded`, `deprecated`, `rascunho` do índice
- Pipeline `pipelineAdr()` → `retrieveKbContext()` com 2 camadas:
  1. Meilisearch hybrid (preferencial)
  2. MySQL FULLTEXT (fallback automático)
- `SCOUT_DRIVER=null` adicionado ao `phpunit.xml`

**Versão Meilisearch em prod:** v1.10.3 (out/2024)

**Embedder:** OpenAI `text-embedding-3-small` via API
- `documentTemplate`: `"{{doc.title}} | {{doc.slug}} | {{doc.type}} | {{doc.module}}"` 
- Problema: sem conteúdo textual → vetores sem semântica

**Score medido:**
| Pipeline | Score |
|---|---|
| MySQL FULLTEXT | 0.66 |
| Hybrid (openai, semanticRatio=0.5) | 0.66 |

**Causa-raiz do empate:** `documentTemplate` minimalista → embedder gera vetores
sem semântica real → hybrid não melhora sobre keyword.

---

### Sprint 9 fase 1 — 2026-05-04 (ADR 0068 — infraestrutura)

**Upgrade Meilisearch:** v1.10.3 → **v1.43.0**
- Jump de 33 versões exigiu dump+wipe do volume (incompatibilidade de formato DB)
- Processo: dump → parar container → deletar volume → criar volume novo →
  subir v1.43.0 → re-importar via `scout:import`

**Switch embedder: OpenAI → Ollama nomic-embed-text (local)**
- Razão: custo zero, LGPD (dados não saem da rede)
- Modelo: `nomic-embed-text:137M` (F16, 274MB, 768 dims)
- CT 100 (172.18.0.3 Docker network) com `MEILI_EXPERIMENTAL_ALLOWED_IP_NETWORKS`
  para bypassar SSRF protection do Meilisearch v1.43.0

**Configuração embedder `nomic_local` em prod:**
```json
{
  "nomic_local": {
    "source": "ollama",
    "url": "http://ollama-embedder:11434/api/embeddings",
    "model": "nomic-embed-text",
    "documentTemplate": "{{doc.title}}. {{doc.content_excerpt}}"
  }
}
```

**Fix `content_excerpt`:** ADRs têm frontmatter YAML (200-400 chars). Sem o strip,
todos os docs geravam vetores similares baseados em estrutura YAML.
Fix: `preg_replace('/^\s*---\n.*?\n---\n?/s', '', $content_md)` antes de `mb_substr`.

**Settings `mcp_memory_documents`:**
- `filterableAttributes`: `["status", "type", "module", "slug"]`
- `searchableAttributes`: `["title", "content_excerpt", "content_md", "slug", "tags"]`

**Problema de infra — tasks bloqueadas:** SSRF retry loop bloqueou queue de tasks.
Fix: parar Meilisearch → Alpine container para deletar `/meili_data/tasks/` → restart.

---

### Sprint 9 fase 2 — 2026-05-04 (diagnóstico e fixes)

**Diagnóstico 1: nomic-embed-text PT-BR é inútil**
- Cosine similarity ~0.97 para TODOS os documentos em queries PT-BR
- Modelo treinado primariamente em inglês
- Resultado: semantic search aleatório → score 0.158 (baseline era 0.66)

**Diagnóstico 2: Meilisearch BM25 < MySQL FT NATURAL LANGUAGE para corpus PT-BR**
- `memory-changelog` (documento muito longo) ranqueia ACIMA de ADR 0066/0065
  porque acumula alta frequência de termos do projeto
- BM25 do Meilisearch usa TF×IDF mas com saturação diferente do MySQL FT
- MySQL FT NATURAL LANGUAGE MODE usa IDF puro — raridade de "format_date" = rank alto
- Impacto: `format-date-shift` e `permission-registry` foram 0.00 com Meilisearch,
  mas 1.00 com MySQL FT

**Diagnóstico 3: Scout observer bypassa checksum**
- `$doc->update(['indexed_at' => now()])` no branch "sem mudança" do
  `IndexarMemoryGitParaDb` disparava evento Eloquent `updated`
- Scout observer captura o evento → chama `searchable()` → Meilisearch indexa
- Resultado: 383 re-embeddings por `mcp:sync-memory`, mesmo sem mudança

**Fixes implementados:**

| Fix | Arquivo | Commit |
|---|---|---|
| `withoutSyncingToSearch()` no branch sem mudança | `IndexarMemoryGitParaDb.php` | ebca7a37 |
| `--semantic-ratio` option no eval | `EvalRagasBaselineCommand.php` | ebca7a37 |
| Bypass Meilisearch quando ratio < 0.25 | `EvalRagasBaselineCommand.php` | 1b33f258 |
| ADR 0068 + session log | `memory/` | d260c33a |

**Score final Sprint 9:**
| Configuração | Score |
|---|---|
| nomic ratio=0.7 (semantic dominante) | 0.158 |
| nomic ratio=0.1 (mostly keyword) | 0.388 |
| Meilisearch ratio=0.0 (keyword puro) | 0.517 |
| **MySQL FT bypass (ratio < 0.25)** | **0.700** |
| Baseline original (grep) | 0.72 |

---

## Estado atual (2026-05-04)

### Infraestrutura

| Componente | Versão | Status |
|---|---|---|
| Meilisearch | v1.43.0 | ✅ prod CT 100 |
| Ollama | latest | ✅ prod CT 100 |
| nomic-embed-text | 137M | ✅ instalado (mas ruim para PT-BR) |
| bge-reranker-v2-m3 | — | ❌ não instalado |

### Comportamento atual do retrieval (`retrieveKbContext`)

- `semanticRatio < 0.25` → **MySQL FT** (mais preciso, score 0.700)
- `semanticRatio >= 0.25` → **Meilisearch hybrid** + fallback MySQL FT
- Atualmente: ratio padrão do config é 0.7 → usa Meilisearch (mas score ruim com nomic)

### Índice `mcp_memory_documents`

- 383 docs indexados com `content_excerpt` (400 chars, sem frontmatter YAML)
- Embeddings gerados com nomic-embed-text (qualidade ruim para PT-BR)
- Re-embedding via `mcp:sync-memory` agora é incremental (checksum git_sha)

---

## Próximos passos para superar 0.72

> **Pesquisa 2026-05-04 mai/2026 documentada em [`RETRIEVAL-ESTADO-ARTE-2026-05.md`](./RETRIEVAL-ESTADO-ARTE-2026-05.md).**
> Recomendação canônica: `qwen3-embedding:4b` (Alibaba) — #1 MTEB multilingual Jun/2025,
> 100+ idiomas com PT-BR explícito, registry oficial Ollama.

### Opção A — Trocar modelo embedding (RECOMENDADO)

```bash
# CT 100, container Ollama:
ollama pull qwen3-embedding:4b   # 3.5GB VRAM, MTEB ~68
# OU se VRAM apertada:
ollama pull qwen3-embedding:0.6b  # 1.5GB VRAM, MTEB ~65
```

Após instalar, criar novo embedder Meilisearch e re-importar:
```bash
# Criar embedder qwen3_local
curl -X PATCH "$MEILI/indexes/mcp_memory_documents/settings/embedders" \
  -H "Authorization: Bearer $MEILI_KEY" -H "Content-Type: application/json" \
  -d '{
    "qwen3_local": {
      "source": "ollama",
      "url": "http://ollama-embedder:11434",
      "model": "qwen3-embedding:4b",
      "dimensions": 1024,
      "documentTemplate": "{{doc.title}}. {{doc.content_excerpt}}"
    }
  }'
# Re-importar (Ollama gera novos embeddings)
php artisan scout:import "Modules\Jana\Entities\Mcp\McpMemoryDocument"
# Atualizar EvalRagasBaselineCommand pra usar 'qwen3_local' como embedder
```

Adicionalmente, configurar stopwords PT-BR + localizedAttributes (ganho +5-10%):
```bash
# Stopwords PT-BR (lista canônica em RETRIEVAL-ESTADO-ARTE-2026-05.md)
curl -X PUT "$MEILI/indexes/mcp_memory_documents/settings/stop-words" -d '[...]'
# Localized attributes
curl -X PUT "$MEILI/indexes/mcp_memory_documents/settings/localized-attributes" \
  -d '[{"locales": ["por"], "attributePatterns": ["*"]}]'
```

### Opção B — Cross-encoder reranker (pós-fetch top-10)

```bash
ollama pull bge-reranker-v2-m3   # cross-encoder multilingual
# Fluxo: Meilisearch hybrid top-10 → reranker → top-3 → LLM
```

### Opção C — Ajustar ranking Meilisearch para PT-BR

Configurar `rankingRules` e adicionar sinônimos PT-BR:
```bash
# Ranking: priorizar words > attribute > exactness (vs default que inclui proximity)
curl -X PATCH .../settings/ranking-rules \
  -d '["words", "attribute", "exactness", "typo", "proximity"]'
```

---

## Lições aprendidas

1. **Modelos de embedding têm idioma dominante** — verificar MTEB por idioma antes de instalar
2. **BM25 ≠ MySQL FT NATURAL LANGUAGE** — para corpus PT-BR técnico com doc longo dominante,
   MySQL FT foi mais preciso por causa do IDF puro
3. **Meilisearch v1.43.0 tem SSRF protection** — URLs privadas (172.x, 192.168.x) bloqueadas
   por padrão; usar `MEILI_EXPERIMENTAL_ALLOWED_IP_NETWORKS` para Ollama local
4. **Scout observer é sensível a `update()`** — qualquer `model->update()` dispara indexação;
   usar `withoutSyncingToSearch()` para updates de controle (indexed_at, etc.)
5. **Jump de versão Meilisearch > 5 versões** — exige dump+wipe do volume de dados
