---
slug: session-2026-05-04-sprint9-retrieval-diagnostico
title: "Sessão 2026-05-04 — Sprint 9 retrieval: diagnóstico nomic-embed-text + fixes"
type: session
tags: [retrieval, meilisearch, ollama, ragas, eval, sprint9, checksum, bm25]
date: 2026-05-04
---

# Sessão 2026-05-04 — Sprint 9: Retrieval diagnóstico + fixes

## O que foi feito

Continuação direta do Sprint 9. O Meilisearch v1.43.0 + nomic-embed-text já estava
configurado e indexado (Sprint 9 fase 1). Essa sessão focou em entender por que o
score caiu de 0.66 → 0.158 e recuperar.

## Bugs encontrados e corrigidos

### Bug 1 — Scout observer bypassa checksum (crítico para eficiência)

**Arquivo:** `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` linha ~325

**Problema:** No branch "sem mudança" (git_sha idêntico), o código fazia:
```php
$doc->update(['indexed_at' => now()]);
```
Isso disparava o evento Eloquent `updated` → Scout observer → Meilisearch indexing
→ Ollama re-embeda o doc. Com 383 docs, CADA `mcp:sync-memory` mandava todos ao
Ollama, mesmo que nenhum arquivo tivesse mudado.

**Fix:**
```php
McpMemoryDocument::withoutSyncingToSearch(fn () => $doc->update(['indexed_at' => now()]));
```

### Bug 2 — nomic-embed-text PT-BR: embeddings inutilizáveis

**Diagnóstico:** O modelo `nomic-embed-text:137M` no CT 100 gera cosine similarity
~0.97 para TODOS os documentos PT-BR. O modelo é primariamente em inglês.
Semantic search vira ruído aleatório → score caiu de 0.66 → 0.158.

**Fix temporário:** Adicionado `--semantic-ratio` ao eval command + bypass de
Meilisearch quando ratio < 0.25 (usa MySQL FT diretamente).

### Bug 3 — Meilisearch BM25 < MySQL FT para corpus PT-BR

**Diagnóstico:** Mesmo com semanticRatio=0.0 (pure keyword), Meilisearch ranqueia
`memory-changelog` antes de ADR 0066/0065. O CHANGELOG é um documento muito longo
com alta frequência de termos do projeto → BM25 satura incorretamente.
MySQL FT NATURAL LANGUAGE usa IDF e ranqueia corretamente por raridade do termo.

**Confirmado via:**
```bash
# Meilisearch keyword (sem filter): CHANGELOG primeiro
# MySQL FT: ADR 0066 primeiro
```

**Fix:** Bypass Meilisearch quando `semanticRatio < 0.25` no `retrieveKbContext()`.

## Score final Sprint 9

| Configuração | Score |
|---|---|
| Sprint 9 nomic ratio=0.7 | 0.158 |
| Sprint 9 nomic ratio=0.1 | 0.388 |
| Sprint 9 nomic ratio=0.0 | 0.517 |
| **Sprint 9 MySQL FT bypass** | **0.700** |
| Baseline original | 0.72 |

Recuperado para 0.700 — praticamente baseline.

## Próximo passo

Instalar `multilingual-e5-large` no Ollama (CT 100) para ter semantic
real em PT-BR. Esse modelo é top-ranked no MTEB multilingual e deve
gerar embeddings discriminativos para o corpus de ADRs.

## Commits

- `ebca7a37` — fix(mcp): withoutSyncingToSearch + --semantic-ratio no eval
- `1b33f258` — fix(eval): bypass Meilisearch BM25 quando semanticRatio < 0.25
