# RUNBOOK — Rotina Semanal de Melhoria da Memória do Copiloto

> **Propósito:** Guia operacional para evoluir o pipeline de memória do Copiloto semanalmente, comparando com o estado da arte e aplicando melhorias incrementais mensuráveis.
>
> **Cadência:** Toda sexta-feira, ~30 minutos. Wagner ou agente IA via `/schedule`.
>
> **Meta de chegada:** Recall@3 ≥ 0.80 (paridade Mem0 v2 / LongMemEval benchmark).
>
> **Baseline atual (2026-04-30):** Recall@3 ~0.258 | hit_rate ~0.12 | bloat_ratio ~0.30

---

## Visão geral do ciclo semanal

```
Planejar → Executar → Analisar
   ↑                        ↓
   └────── próxima semana ──┘
```

Cada semana: **1 melhoria**, medida antes e depois com `copiloto:eval`.

---

## Fase 1 — Planejar (10 min)

### 1.1 Medir o estado atual

```bash
# Do servidor de produção (CT 100) ou dev local
php artisan copiloto:eval --business=1 --persist --verbose
```

Registra no log: `Recall@3`, `hit_rate`, `bloat_ratio`, `latência_recall_ms`.

Se em local (dev):
```bash
DB_CONNECTION=mysql DB_DATABASE=oimpresso php artisan copiloto:eval --business=1
```

### 1.2 Consultar o Playbook de Melhorias

Ver §3 abaixo — lista de alavancas ordenadas por impacto estimado em Recall@3.
Pegar a próxima não implementada com maior ROI.

### 1.3 Pesquisar o estado da arte (opcional, a cada 4 semanas)

Buscar em arxiv/HuggingFace:
- LongMemEval leaderboard (atualizado mensalmente)
- Papers recentes sobre: memory augmented LLMs, hybrid retrieval PT-BR, episodic memory

Keywords úteis: `"memory augmented" LLM 2026`, `episodic memory retrieval`, `HyDE retrieval`, `RAPTOR hierarchical`.

Registrar novas referências em `memory/decisions/` se mudarem a direção arquitetural.

---

## Fase 2 — Executar (15 min)

### Regra: uma melhoria por semana, medida

Nunca fazer 2 mudanças juntas — impossibilita atribuir causalidade.

Ordem de implementação recomendada (ver Playbook §3):

| Semana | Melhoria | Impacto estimado |
|---|---|---|
| 1 | Filtro `relevancia >= 3` no recall | +0.15 Recall@3 |
| 2 | `core_memory` → injetar no system prompt | -30% latência |
| 3 | A/B semantic_ratio 0.55 vs 0.70 | +0.12 Recall@3 |
| 4 | HyDE habilitado | +0.10 Recall@3 |
| 5 | Reranker LLM (gpt-4o-mini) | +0.05 Recall@3 |
| 6+ | Medir → identificar próximo gap | — |

### Comandos de suporte

```bash
# Ver distribuição de relevância dos fatos atuais
php artisan tinker --execute="
    DB::table('copiloto_memoria_facts')
        ->where('business_id', 1)
        ->selectRaw('JSON_EXTRACT(metadata, \"$.relevancia\") as rel, count(*) as n')
        ->groupBy('rel')->orderBy('rel')->get()
"

# Ver hits_count distribution
php artisan tinker --execute="
    DB::table('copiloto_memoria_facts')
        ->where('business_id', 1)
        ->selectRaw('CASE WHEN hits_count=0 THEN \"zero\" WHEN hits_count<5 THEN \"1-4\" ELSE \"5+\" END as tier, count(*) as n')
        ->groupBy('tier')->get()
"

# Ver core_memory count
php artisan tinker --execute="
    DB::table('copiloto_memoria_facts')
        ->where('business_id', 1)
        ->where('core_memory', true)
        ->count()
"

# Rodar cleanup manual e ver o que seria removido (dry-run)
php artisan copiloto:cleanup-memoria --business=1 --dry-run
```

---

## Fase 3 — Analisar (5 min)

### 3.1 Comparar métricas antes × depois

```bash
php artisan copiloto:eval --business=1 --persist
```

Registrar resultado na tabela de histórico abaixo (§4).

### 3.2 Critério de sucesso da semana

| Resultado | Ação |
|---|---|
| Recall@3 melhorou ≥ 0.02 | ✅ Manter, próxima semana: próxima melhoria |
| Recall@3 melhorou < 0.02 | ⚠️ Rollback env var, investigar antes de avançar |
| Recall@3 regrediu | 🔴 Rollback imediato, criar ADR com post-mortem |
| Recall@3 ≥ 0.70 por 2 semanas | 🏁 Considerar arquitetura Mem0 REST (ADR 0062 Cycle 03) |

### 3.3 Atualizar este RUNBOOK

Adicionar linha na tabela §4 com: data, melhoria aplicada, Recall@3 antes/depois, decisão.

---

## Playbook de Melhorias — Alavancas por impacto {#playbook}

### Tier 1 — Alto impacto, baixo risco (implementar primeiro)

#### P1-A: Filtro `relevancia >= 3` no recall
- **Onde**: `Modules/Copiloto/Services/Memoria/MeilisearchDriver.php`, método `recall()`
- **O que**: Adicionar `filter: 'metadata_relevancia >= 3'` na query Meilisearch
- **Por que**: 356 docs seedados sem relevância alta poluem os resultados
- **Rollback**: Remover o filtro
- **Impacto estimado**: +0.15 Recall@3 (maior ganho isolado)

```php
// Em MeilisearchDriver::recall()
$params = [
    'q' => $query,
    'limit' => $limit * 2,
    'filter' => "business_id = {$businessId} AND metadata_relevancia >= 3",
    'hybrid' => ['semanticRatio' => config('copiloto.memoria.semantic_ratio', 0.7)],
];
```

> **Nota**: `metadata_relevancia` precisa ser campo filtrável no índice Meilisearch.
> Verificar com: `curl http://localhost:7700/indexes/copiloto_memoria_facts/settings/filterable-attributes`
> Se ausente: adicionar via `PUT /indexes/.../settings/filterable-attributes`

#### P1-B: core_memory → injetar no system prompt
- **Onde**: `Modules/Copiloto/Agents/ChatCopilotoAgent.php` (ou equivalente)
- **O que**: Buscar `CopilotoMemoriaFato::where('core_memory', true)->where('business_id', $biz)` e injetar no system prompt antes do recall Scout
- **Por que**: Fatos com 5+ hits são recorrentes — não precisam passar pelo recall toda mensagem
- **Impacto estimado**: -30% latência recall (100-150ms poupados)
- **Rollback**: Remover a busca e injeção

```php
// No início do handling da mensagem, antes de chamar recall()
$coreMemory = CopilotoMemoriaFato::where('business_id', $businessId)
    ->where('core_memory', true)
    ->ativos()
    ->orderByDesc('hits_count')
    ->limit(10)
    ->pluck('fato')
    ->implode("\n- ");

if ($coreMemory) {
    $systemPrompt .= "\n\n## Fatos sempre relevantes (alta frequência):\n- " . $coreMemory;
}
```

---

### Tier 2 — Médio impacto, requer calibração

#### P2-A: semantic_ratio 0.55 (A/B test)
- **Onde**: `.env` → `COPILOTO_MEMORIA_SEMANTIC_RATIO=0.55`
- **O que**: Reduzir peso semântico de 0.70 → 0.55 no hybrid search
- **Por que**: PT-BR tem cross-phrasing menor que inglês; BM25 ajuda mais
- **Impacto estimado**: +0.12 Recall@3
- **Rollback**: Reverter env var para 0.70
- **Como A/B**: Semana ímpar = 0.55, semana par = 0.70. Medir Recall@3 em cada.

#### P2-B: HyDE (Hypothetical Document Embeddings)
- **Onde**: `COPILOTO_HYDE_ENABLED=true` + implementação em `MeilisearchDriver`
- **O que**: Antes de buscar, gerar documento hipotético que responderia a query → usar seu embedding
- **ADR referência**: ADR 0054 (já especificado)
- **Impacto estimado**: +0.10 Recall@3
- **Custo extra**: 1 LLM call por recall (~$0.001)
- **Rollback**: `COPILOTO_HYDE_ENABLED=false`

#### P2-C: Reranker LLM
- **Onde**: `COPILOTO_RERANKER_ENABLED=true` + implementação pós-recall
- **O que**: Reordenar top-K candidatos com gpt-4o-mini usando cross-encoder prompt
- **ADR referência**: ADR 0054
- **Impacto estimado**: +0.05 Recall@3
- **Custo extra**: ~150 tokens por rerank
- **Rollback**: `COPILOTO_RERANKER_ENABLED=false`

---

### Tier 3 — Alto impacto, alto esforço (Cycle 03+)

#### P3-A: Mem0 REST driver
- **Trigger**: Recall@3 < 0.70 após implementar Tier 1+2 completos
- **O que**: Driver alternativo que usa Mem0 API em vez de Meilisearch local
- **ADR referência**: ADR 0036 (MemoriaContrato — driver swap sem mudar callers)
- **Estimativa**: 2-3 dias, requer conta Mem0

#### P3-B: Graph memory (entidades + relações)
- **O que**: Extrair entidades (produto, cliente, valor) e persistir relações semânticas
- **Quando**: Após Mem0 driver validado OU Recall@3 ≥ 0.70 por 4 semanas

#### P3-C: Multi-modal memory (imagens, layouts)
- **O que**: Fatos com imagens de produto, screenshots de layouts, evidências visuais
- **Quando**: Após graph memory OU demanda específica de cliente

---

## Histórico de execuções {#historico}

| Data | Melhoria | Recall@3 antes | Recall@3 depois | Decisão |
|---|---|---|---|---|
| 2026-04-30 | Baseline (8 fases completas + 51 testes) | — | 0.258 | ✅ Base documentada |
| _próxima semana_ | P1-A: filtro relevancia >= 3 | 0.258 | ? | — |

---

## Como rodar o agente de melhoria via Claude Code

```bash
# No repo local, com MySQL ativo:
claude --print "Leia RUNBOOK-MEMORIA-SEMANAL.md em memory/requisitos/Copiloto/ e execute a rotina da semana: meça Recall@3 com copiloto:eval, identifique a próxima melhoria no Playbook, implemente, meça de novo e atualize o RUNBOOK com o resultado."
```

Ou agendar via `/schedule` no Claude Code:
```
/schedule weekly friday Executar rotina semanal de melhoria da memória Copiloto conforme RUNBOOK-MEMORIA-SEMANAL.md
```

---

## Referências

- ADR 0062: Análise completa 8 fases + metas vs estado da arte + roadmap
- ADR 0054: HyDE + Reranker + Negative Cache (especificação técnica)
- ADR 0036: MemoriaContrato + MeilisearchDriver (interface de driver swap)
- ADR 0050: Métricas de memória (8 métricas + RAGAS)
- LongMemEval benchmark: https://arxiv.org/abs/2407.02582
- Mem0 v2 paper: https://mem0.ai/research (Recall@3=0.80 no LongMemEval)
