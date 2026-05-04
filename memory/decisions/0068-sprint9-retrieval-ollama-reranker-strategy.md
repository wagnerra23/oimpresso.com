---
slug: 0068-sprint9-retrieval-ollama-reranker-strategy
title: "Sprint 9 — Estratégia retrieval: Ollama embedder + reranking + documentTemplate fix"
status: rascunho
authority: [Wagner]
lifecycle: active
quarter: Q2-2026
decided_at: 2026-05-04
decided_by: [Wagner, Claude]
supersedes: []
superseded_by: []
related: [0067, 0037, 0053, 0036]
tags: [retrieval, meilisearch, ollama, reranker, ragas, eval, copiloto]
---

# ADR 0068 — Sprint 9: Ollama embedder + reranking para superar baseline 0.72

## Status

Rascunho — 2026-05-04 (aprovado estrategicamente, implementação Sprint 9)

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
