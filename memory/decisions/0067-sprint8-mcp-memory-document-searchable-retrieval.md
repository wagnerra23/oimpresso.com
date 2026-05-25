---
slug: 0067-sprint8-mcp-memory-document-searchable-retrieval
number: 67
title: "Sprint 8 — McpMemoryDocument Searchable + retrieval hybrid na pipeline RAGAS"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: "2026-05-04"
decided_by: [W]
module: copiloto
supersedes: []
superseded_by: []
related: ["0037-roadmap-evolucao-tier-7-plus", "0036-replanejamento-meilisearch-first", "0053-mcp-server-governanca-como-produto", "0063-prevenir-composer-lock-drift"]
tags: [retrieval, meilisearch, ragas, eval, copiloto, kb]
---

# ADR 0067 — Sprint 8: McpMemoryDocument Searchable + retrieval hybrid na pipeline RAGAS

## Status

Aceito — 2026-05-04

## Contexto

A pipeline `--pipeline=adr` do `eval:ragas-baseline` usava `glob(memory/decisions/*.md)` +
keyword grep no filesystem como retrieval. Baseline de 04-mai-2026 mostrou teto em **0.68 RAGAS**
(8 perguntas ADR) mesmo após melhorias de frontmatter e filtro de `status=superseded`.

Limitações identificadas do grep:

- Não ranqueia por relevância semântica (só match exato de substring)
- Falha em termos hifenizados (`usuario-360`, `reverb-status`)
- Ignora contexto semântico: "Reverb foi abandonado?" não casa com ADR sobre Centrifugo se o
  keyword for diferente
- Não aproveita `mcp_memory_documents` (DB cache governado, já em prod com 384 docs, ADR 0053)

A `McpMemoryDocument` já existia com scope `scopeBuscarTexto` (MySQL FULLTEXT), mas sem
`Searchable` — não estava integrada ao Scout/Meilisearch.

## Decisão

### 1. `McpMemoryDocument` agora implementa `Laravel\Scout\Searchable`

Métodos adicionados:

```php
public function searchableAs(): string  → 'mcp_memory_documents'

public function toSearchableArray(): array
    // slug, title, content_md, type, module, status, tags

public function shouldBeSearchable(): bool
    // false se status in [superseded, deprecated, rascunho]
    // Garante que docs não-canônicos nunca entram no índice
```

Consequência: quando `mcp:sync-memory` cria/atualiza docs, Scout observer dispara.
`SCOUT_DRIVER=null` adicionado ao `phpunit.xml` para impedir `CommunicationException`
em testes que criam `McpMemoryDocument`.

### 2. `pipelineAdr()` → `retrieveKbContext()` com 2 camadas

```
Camada 1 — Meilisearch hybrid (preferencial)
    McpMemoryDocument::search($query, callback)
    hybrid: {embedder: openai, semanticRatio: 0.5}
    filter: status NOT IN [superseded, deprecated, rascunho]
    limit: topK

Camada 2 — MySQL FULLTEXT (fallback automático)
    McpMemoryDocument::buscarTexto($query)
    + whereNotIn(status, [...])
    + orderByRaw(MATCH...AGAINST DESC)
    limit: topK
```

Fallback é silencioso em caso de `CommunicationException` (Meilisearch offline/não configurado).
Registra linha `⚠ Meilisearch indisponível (X) — fallback MySQL FULLTEXT` no CLI.

### 3. System prompt RAGAS refinado

Instrução adicionada: _"Use o contexto disponível mesmo que parcial — só diga 'não tenho info'
se o contexto for completamente vazio ou irrelevante."_

Corrige o bug 2 identificado no baseline: modelo conservador demais respondia "não tenho info
canônica" mesmo com contexto relevante presente (faithfulness OK, relevancy caía).

### 4. Fix drift composer — `nfse-nacional/nfse-php` removido

Package estava no `composer.json` mas não no lock (US-NFSE-004 adicionou sem rodar
`composer require`). ADR 0063 cobre política de prevenção; esse era o caso ativo.
`SnNfseAdapter` usa HTTP direto — TODO US-NFSE-004 mantido no adapter.

## Resultados medidos (2026-05-04)

### Comparativo ADR 8 perguntas

| Pergunta | Baseline grep (0.72) | Pós-fix (0.68) | Sprint 8 MySQL FT | Sprint 8 Hybrid |
|---|---|---|---|---|
| format-date-shift | 1.00 | 1.00 | **1.00** | **1.00** |
| permission-registry | 1.00 | 1.00 | **1.00** | **1.00** |
| split-modular | 1.00 | 1.00 | **1.00** | **1.00** |
| usuario-360-location | 0.90 | 0.27 | **0.00** ↓ | **0.27** ↑ |
| kb-mora | 0.67 | 0.67 | **0.67** | **0.00** ↓ |
| vizra-rejeitada | 0.67 | 1.00 | **0.67** ↓ | **0.67** |
| reverb-status | 0.33 | 0.33 | **0.00** ↓ | **0.67** ↑↑ |
| governance-criar | 0.20 | 0.17 | **0.93** ↑↑ | **0.67** ↓ |
| **Média** | **0.72** | **0.68** | **0.66** | **0.66** |

### Observações

- `reverb-status` 0.00 → 0.67: Hybrid semântico encontrou relação Reverb/Centrifugo. Era o caso
  de uso central do Sprint 8 — semântico funcionou onde grep/MySQL FULLTEXT falhavam.
- `governance-criar` 0.93 → 0.67: regrediu vs MySQL FT. MySQL FULLTEXT com keyword exacto era
  mais preciso para perguntas de decisão arquitetural direta.
- `kb-mora` 0.67 → 0.00: vetores do doc KB muito fracos — documentTemplate só metadados,
  sem conteúdo textual suficiente para similaridade vetorial.
- **Empate técnico MySQL FT vs Hybrid (0.66 = 0.66)**. A raiz é o `documentTemplate` minimalista:
  `"{{doc.title}} | {{doc.slug}} | {{doc.type}} | {{doc.module}}"` — sem trecho de conteúdo,
  o embedder não gera vetores com semântica suficiente.
- **Próximo passo**: `documentTemplate` deve incluir primeiros ~300 chars do `content_md` para
  vetores com semântica real. Limitação anterior (token overflow do OpenAI) era com o doc completo.

### Resultado final Sprint 8

Score ADR hybrid: **0.66** — infra Meilisearch em prod, fallback MySQL FT funcionando.
Ganho de score pós-embedder esperado na Sprint 9 via melhoria do `documentTemplate`.

## Consequências

### Positivas

- Pipeline de retrieval RAGAS agora usa `mcp_memory_documents` (DB governado, fonte única)
- `shouldBeSearchable()` garante que docs superseded/deprecated nunca entram no índice Meilisearch
- Fallback automático garante que a pipeline nunca quebra por Meilisearch offline
- Infra preparada para ganho real quando embedder estiver configurado

### Negativas / Riscos

- `McpMemoryDocument::save()` dispara Scout observer — `mcp:sync-memory` precisa de
  `SCOUT_DRIVER=null` até o embedder Meilisearch estar configurado, senão falha
- MySQL FULLTEXT pode ter desempenho inferior ao grep para queries com termos hifenizados

### Pendências para completar Sprint 8 em produção

```bash
# Importar todos os docs pro índice Meilisearch
php artisan scout:import "Modules\Jana\Entities\Mcp\McpMemoryDocument"

# Configurar embedder OpenAI no índice mcp_memory_documents
curl -X PATCH https://meilisearch.oimpresso.com/indexes/mcp_memory_documents/settings/embedders \
  -H "Authorization: Bearer $MEILISEARCH_KEY" \
  -H "Content-Type: application/json" \
  -d '{"openai":{"source":"openAi","model":"text-embedding-3-small","apiKey":"$OPENAI_API_KEY"}}'

# Re-rodar baseline com Meilisearch ativo → medir delta vs 0.68
php artisan eval:ragas-baseline --pipeline=adr --category=
```

Expectativa pós-embedder: `usuario-360-location` e `reverb-status` devem se recuperar via
similaridade vetorial, empurrando o score total acima de 0.72.

## Alternativas descartadas

- **Manter grep puro**: não escala com volume de docs, não governa via DB, não filtra por status
- **MySQL FULLTEXT sem fallback Meilisearch**: funciona mas tem teto abaixo do hybrid
- **Reescrever pipelineAdr sem fallback**: frágil em dev local e ambientes sem Meilisearch
