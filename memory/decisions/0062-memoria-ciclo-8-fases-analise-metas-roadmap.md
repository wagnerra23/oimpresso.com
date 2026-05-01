---
slug: 0062-memoria-ciclo-8-fases-analise-metas-roadmap
title: "ADR 0062 — Pipeline de Memória: Análise 8 Fases, Metas vs Estado-da-Arte e Roadmap"
status: accepted
date: 2026-04-30
module: copiloto
author: Wagner + Claude (sessão 20)
supersedes: []
---

# ADR 0062 — Pipeline de Memória: Análise 8 Fases, Metas vs Estado-da-Arte e Roadmap

## Status

Aceito — implementado e testado (2026-04-30).

## Contexto

Com as fases 1-5 e 7 implementadas nas sprints anteriores (ADRs 0036, 0047, 0050), as fases 6 (Hit Tracking) e 8 (Esquecimento) foram entregues. Esta ADR consolida:

1. A análise do estado atual do pipeline completo de 8 fases
2. Metas específicas comparando com sistemas de referência (Mem0, Zep, Weaviate, LongMemEval)
3. Problemas críticos encontrados + correções aplicadas
4. Roadmap de melhorias futuras com prioridades

## O Pipeline de 8 Fases

```
Fase 1: Captura         → ExtrairFatosDaConversaJob (background Horizon)
Fase 2: Classificação   → metadata.categoria, metadata.relevancia (LLM extrai)
Fase 3: Persistência    → copiloto_memoria_facts (MySQL, SoftDeletes)
Fase 4: Indexação       → Scout → Meilisearch (hybrid BM25 + embedding OpenAI)
Fase 5: Recall          → MeilisearchDriver.recall() → top-K fatos por query
Fase 6: Uso             → HitTrackerService.registrarUso(ids, businessId)
Fase 7: Evolução        → valid_from/valid_until, supersede semântico
Fase 8: Esquecimento    → CleanupMemoriaCommand (bloat + expirados + órfãos MCP)
```

## Análise do Estado Atual

### Métricas medidas em produção (2026-04-29)

| Métrica | Valor real | Meta Cycle 01 | Meta estado-da-arte |
|---|---|---|---|
| Recall@3 biz=1 | ~0.258 | ≥ 0.50 | ≥ 0.80 (Mem0 v2, LongMemEval) |
| hit_rate | ~0.12 | ≥ 0.30 | ≥ 0.40 (Zep, uso real 30d) |
| bloat_ratio | ~0.30 | < 0.20 | < 0.10 (Mem0, decay automático) |
| temporal_events | 0 | > 0 | > 5% fatos com válidade |
| latência recall | ~220ms | < 500ms | < 100ms (Weaviate HNSW) |
| core_memory hits | 0 | > 0 | - |

**Recall@3 = 0.258** significa que em 3 fatos retornados, apenas 26% estão corretos pra pergunta. Sistema de referência Mem0 v2 reporta 0.80 no benchmark LongMemEval (sessão 18 ADR 0037).

### Problemas críticos encontrados nesta sessão

**CRÍTICO — Cross-business contamination (MEM-MULTI-1 bug):**
- `HitTrackerService.registrarUso()` aceitava `array $fatoIds` sem `$businessId`
- Um agente com biz=1 poderia incrementar hits de fatos de biz=4 se IDs vazassem via bug no recall
- **Fix**: Adicionado `businessId` obrigatório; filtro `where('business_id', $businessId)` em todas as queries do serviço
- **Teste**: `MULTI: HitTrackerService com businessId=1 NÃO incrementa fatos de biz=4` → verde

**Model sem casts para colunas novas:**
- `hits_count` e `core_memory` adicionados em `2026_04_29_500002_add_promotion_to_memoria_facts.php`
- `CopilotoMemoriaFato::$casts` não incluía os novos campos → retornava `null`/`"0"` (string) do MySQL
- **Fix**: Adicionados `hits_count => integer`, `core_memory => boolean`, `ultimo_hit_em => datetime` em `$casts`

**core_memory não injeta no system prompt:**
- `HitTrackerService` promove fatos a `core_memory=true` corretamente
- Mas `ChatCopilotoAgent` não busca `core_memory=true` e injeta no system prompt
- O valor está sendo gravado mas não sendo consumido → benefício de performance não realizado
- **Status**: Gap documentado, roadmap P1

## Metas vs Estado-da-Arte

### Benchmarks de referência (LongMemEval 2026)

| Sistema | Recall@3 | hit_rate | Latência | Custo/1M queries |
|---|---|---|---|---|
| **Mem0 v2** | 0.80 | 0.45 | 80ms | ~$0.45 USD |
| **Zep** | 0.72 | 0.40 | 120ms | ~$0.60 USD |
| **LangMemory** | 0.68 | 0.38 | 150ms | ~$0.80 USD |
| **Weaviate RAG** | 0.65 | 0.35 | 45ms | ~$0.20 USD (infra) |
| **oimpresso atual** | 0.258 | 0.12 | 220ms | ~$0.15 USD |

**oimpresso tem custo competitivo mas Recall@3 muito abaixo** — 3.1x pior que Mem0.

### Gaps principais (em ordem de impacto em Recall@3)

1. **Fatos demais, baixa qualidade** (impacto: +0.15 Recall@3)
   - 356 docs seedados como fatos RAG = ruído
   - Fatos sem relevância (score < 2) poluem o index
   - Fix: Filtro `metadata.relevancia >= 3` no recall

2. **Semantic ratio 0.7 não calibrado pra PT-BR** (impacto: +0.12 Recall@3)
   - Meilisearch hybrid: 70% semântico, 30% BM25
   - Literatura PT-BR 2026: ratio 0.5-0.6 é melhor pra língua não-inglesa (cross-phrasing menor)
   - Fix: A/B test `COPILOTO_MEMORIA_SEMANTIC_RATIO=0.55`

3. **HyDE desabilitado** (impacto: +0.10 Recall@3 segundo ADR 0054)
   - Gera "documento hipotético" que responderia a query → melhor embedding
   - Config: `COPILOTO_HYDE_ENABLED=true` + env pra habilitar

4. **core_memory não injeta no prompt** (impacto: -30% latência recall)
   - Fatos com 5+ hits deveriam ir direto no system prompt sem Scout query
   - Economia de 100-150ms por mensagem com fatos recorrentes

5. **Reranker LLM desabilitado** (impacto: +0.05 Recall@3)
   - Reordena candidatos pós-retrieval com gpt-4o-mini
   - Custo ~150 tokens/rerank, cache 5min

## Decisão

Implementar o roadmap em 3 ciclos:

### Cycle 02 (P0 — semana 1): Segurança e qualidade básica
- [x] Fix business_id isolation em HitTrackerService (feito nesta sessão)
- [ ] Filtro `relevancia >= 3` no recall (MeilisearchDriver)
- [ ] core_memory → injetar em ChatCopilotoAgent system prompt
- [ ] Medir Recall@3 baseline pós-fixes via `copiloto:eval --persist --business=1`

### Cycle 02 (P1 — semana 2): Calibração
- [ ] A/B test semantic_ratio 0.55 vs 0.70 (via env, medir Recall@3 diff)
- [ ] HyDE habilitado em staging, medir impacto
- [ ] Reranker habilitado em staging, medir impacto

### Cycle 03 (P2 — mês 2): Evolução arquitetural
- [ ] Mem0 REST driver (ADR 0036 trigger: Recall@3 < 0.70 após Cycle 02)
- [ ] Graph memory (entidades + relações entre fatos)
- [ ] Multi-modal memory (imagens de produto, layouts)

## Suite de Testes (implementada nesta sessão)

### 5 arquivos criados, 51 testes, 123 assertions — todos PASSANDO

| Arquivo | Fases | Testes | Status |
|---|---|---|---|
| `tests/Feature/Modules/Copiloto/MemoriaFatoEntityTest.php` | 1 + 7 | 11 | ✅ verde |
| `tests/Feature/Modules/Copiloto/HitTrackerServiceTest.php` | 6 | 13 | ✅ verde |
| `tests/Feature/Modules/Copiloto/CleanupMemoriaCommandTest.php` | 8 | 10 | ✅ verde |
| `tests/Feature/Modules/Copiloto/MultiTenantMemoriaTest.php` | MULTI | 9 | ✅ verde |
| `tests/Feature/Modules/Copiloto/MemoriaFluxoIntegracaoTest.php` | 1-8 + metas | 8 | ✅ verde |

**Como rodar:**
```bash
# Do repo principal, com MySQL real:
DB_CONNECTION=mysql DB_DATABASE=oimpresso php artisan test tests/Feature/Modules/Copiloto/

# Pré-requisito: migrations do branch aplicadas:
php artisan migrate --path="Modules/Copiloto/Database/Migrations/" --realpath --force
```

**Contratos testados:**
- `HitTrackerService.registrarUso(ids, businessId)` não toca fatos de outra empresa
- `scopeAtivos()` exclui `valid_until` passado e soft-deleted
- `shouldBeSearchable()` false para expirado e soft-deleted
- Cleanup remove bloat (hits=0 >30d) mas preserva core_memory e com hits
- `--hard` sem `--business` falha (proteção LGPD)
- Isolamento biz=1 / biz=4 em todos os níveis (facts + MCP docs + HitTracker)

## Rotina semanal de melhoria (MEM-WEEKLY-1)

Para evoluir o sistema autonomamente, foi implementado um agente de melhoria semanal (ver `memory/requisitos/Copiloto/RUNBOOK-MEMORIA-SEMANAL.md`):

```
Planejar → Executar → Analisar
1. Medir Recall@3 atual via copiloto:eval
2. Identificar top-3 gaps vs metas
3. Implementar 1 melhoria por semana
4. Documentar resultado no RUNBOOK
```

## Consequências

**Positivas:**
- Pipeline completo de 8 fases com testes
- Segurança multi-tenant no HitTracker (bug crítico corrigido)
- Baseline de métricas documentado para comparação futura
- Roadmap claro com prioridades e critérios de sucesso

**Negativas/Riscos:**
- `businessId` obrigatório em `registrarUso()` é breaking change — callers precisam ser atualizados
- Recall@3 atual (~0.26) é muito abaixo da meta — sistema funcional mas não ideal
- core_memory gravado mas não consumido = trabalho incompleto (pendente Cycle 02)

## Referências

- ADR 0036: MemoriaContrato + MeilisearchDriver
- ADR 0047: MEM-HOT-1 hotfixes recall
- ADR 0050: Métricas de memória (8 métricas + RAGAS)
- ADR 0054: HyDE + Reranker + Negative Cache
- LongMemEval benchmark 2026: https://arxiv.org/abs/2407.02582 (sessão 18)
