---
slug: 0074-temporal-validity-bi-temporal-time-travel
number: 74
title: "P1 — Temporal validity bi-temporal: event-time vs system-time + time-travel queries"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-05"
module: copiloto
quarter: 2026-Q2
tags: [memoria, temporal, bi-temporal, retrieval, p1]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0035-stack-canonica-ia-laravel-ai-memoria-contrato
  - 0036-replanejamento-meilisearch-first
  - 0049-camadas-memoria-agente-fase-por-fase
  - 0050-metricas-obrigatorias-memoria-table
  - 0052-contextonegocio-expor-multiplos-angulos
  - 0072-maturacao-memoria-team-mcp-openclaw-soa-2026
  - 0073-team-mcp-skills-policies-entidades-governadas
pii: false
review_triggers:
  - "Recall@5 não subir ≥5pp em LongMemEval-PT após 30 dias da implementação"
  - "Detecção automática de updates ter precision < 0.85 (gera supersedence falso)"
  - "Zep/Graphiti publicar nova versão major"
---

# ADR 0074 — P1 Temporal validity bi-temporal + time-travel

## Contexto

[ADR 0072](0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md) priorizou **P1 = temporal validity em `copiloto_memoria_facts`**. Investigação 2026-05-05 mudou o desenho proposto:

**Estado real descoberto (não estava claro em 0072):**

A migration [`2026_04_27_000001_create_copiloto_memoria_facts_table.php`](../../Modules/Copiloto/Database/Migrations/2026_04_27_000001_create_copiloto_memoria_facts_table.php) **já criou `valid_from` + `valid_until` uni-temporal**. [`MeilisearchDriver::buscar()`](../../Modules/Copiloto/Services/Memoria/MeilisearchDriver.php) **já filtra** `valid_until === null` no recall (linha 110). [`MeilisearchDriver::atualizar()`](../../Modules/Copiloto/Services/Memoria/MeilisearchDriver.php) **já implementa supersedence append-only** (linha 203: marca antigo + cria novo). Tabela [já tem `hits_count` + `core_memory`](../../Modules/Copiloto/Database/Migrations/2026_04_29_500002_add_promotion_to_memoria_facts.php) (auto-promotion).

ADR 0072 estimou "P1 custa 3 dias" assumindo que `valid_until` precisava ser criado. **Isso já está feito em prod.** O que falta pra "estado-da-arte Zep/Graphiti" é mais sutil — e mais valioso:

1. **Bi-temporal real** — hoje é uni-temporal (só system-time: quando o sistema marcou). Falta event-time (quando o fato passou a valer no mundo real).
2. **Detecção automática de supersedence** — `MeilisearchDriver::atualizar()` exige caller passar `$memoriaId` antigo. [`ExtrairFatosAgent`](../../Modules/Copiloto/Ai/Agents/ExtrairFatosAgent.php) hoje só insere — não detecta que "Larissa demitiu Pedro" supersede "Pedro é vendedor".
3. **Time-travel tool MCP** — não existe. Pra responder "qual era o faturamento que sabíamos em 30/04" precisa SQL ad-hoc.

**Por que importa:** [LongMemEval](https://arxiv.org/abs/2410.10813) (ICLR 2025) mede 5 capacidades; "knowledge updates" e "temporal reasoning" são onde LLMs comerciais perdem mais (queda média 30%). [Zep/Graphiti](https://arxiv.org/abs/2501.13956) (2025) reportou **+18.5% acc com -90% latência** justamente porque tem bi-temporal nativo.

**Restrições:**
- `copiloto_memoria_facts` é append-only (ADR 0049). Toda mudança = nova linha + supersedence.
- Multi-tenant `business_id` mantido sempre.
- Não tocar `core_memory` / `hits_count` (campos de promoção, ADR 0050).
- LGPD: `event_valid_from` pode ser data sensível (ex.: "demitido em X") — entra no escopo do PII redactor existente.

## Decisão

**Migrations cirúrgicas + extensão de service + 1 tool MCP nova.** Não criar tabelas novas — só estender a existente.

### 1. Migration — adicionar event-time bi-temporal

```sql
ALTER TABLE copiloto_memoria_facts
  ADD COLUMN event_valid_from TIMESTAMP NULL
    AFTER valid_until
    COMMENT 'Quando o fato passou a valer no MUNDO REAL (event-time). NULL = mesmo que valid_from',
  ADD COLUMN event_valid_until TIMESTAMP NULL
    AFTER event_valid_from
    COMMENT 'Quando o fato deixou de valer no mundo real. NULL = ainda vale (independente de valid_until)',
  ADD COLUMN supersedes_id BIGINT UNSIGNED NULL
    AFTER event_valid_until
    COMMENT 'ID do fato que ESTE supersede (audit trail explícito)',
  ADD INDEX cmf_event_validity_idx (event_valid_from, event_valid_until),
  ADD INDEX cmf_supersedes_idx (supersedes_id);
```

**Semântica das 4 dimensões temporais:**

| Coluna | Significa | Exemplo |
|---|---|---|
| `valid_from` (já existe) | Quando o **sistema** registrou | `2026-05-05 14:30:00` |
| `valid_until` (já existe) | Quando o **sistema** marcou como superseded | `2026-05-10 09:15:00` (quando o supersede foi escrito) |
| `event_valid_from` (novo) | Quando o fato passou a valer no **mundo real** | `2026-04-01` (Pedro foi contratado em 1º de abril) |
| `event_valid_until` (novo) | Quando o fato deixou de valer no mundo real | `2026-05-10` (Pedro saiu em 10 de maio) |

**Casos de uso que ficam triviais:**
- *"Qual era o faturamento que SABÍAMOS em 30/04?"* — query por `valid_from <= '2026-04-30' AND (valid_until IS NULL OR valid_until > '2026-04-30')`. (System-time travel.)
- *"Qual era a verdade do faturamento em 30/04 (revisada)?"* — query por `event_valid_from <= '2026-04-30' AND (event_valid_until IS NULL OR event_valid_until > '2026-04-30')`. (Event-time.)
- *"Pedro estava ativo em 15/04?"* — query event-time mostra Pedro contratado 01/04, demitido 10/05 → sim.
- Auditoria de retroação: fatos com `event_valid_from < valid_from` foram registrados depois do que aconteceram.

**Default seguro:** ao migrar dados existentes, `event_valid_from = valid_from` e `event_valid_until = valid_until` (sem perda de informação, comportamento atual preservado).

### 2. Estender `MeilisearchDriver::atualizar()` — preencher `supersedes_id`

```php
public function atualizar(int $memoriaId, string $novoFato, array $metadata = []): void
{
    $antigo = CopilotoMemoriaFato::findOrFail($memoriaId);
    $antigo->update(['valid_until' => now()]);  // já existia

    CopilotoMemoriaFato::create([
        'business_id'        => $antigo->business_id,
        'user_id'            => $antigo->user_id,
        'fato'               => $novoFato,
        'metadata'           => $metadata,
        'valid_from'         => now(),
        'event_valid_from'   => $metadata['event_valid_from'] ?? now(),
        'event_valid_until'  => null,
        'supersedes_id'      => $antigo->id,  // ← novo
    ]);
}
```

Caller pode passar `event_valid_from` em `$metadata` quando souber a data real. Quando não passar, default é `now()` (preserva comportamento atual).

### 3. Estender `ExtrairFatosAgent` — detecção automática de supersedence

Hoje extrai `[fato1, fato2, ...]` do contexto e insere todos. **Mudança proposta:**

1. Pra cada fato extraído, fazer recall hybrid (top-3) buscando contradições semânticas.
2. Se top-1 tem similaridade ≥ threshold (sugestão: 0.85) **e** o LLM classificar como "contradiz" (segunda chamada barata, tipo Haiku), chamar `atualizar()` em vez de `lembrar()`.
3. Caso contrário, insere normal.

**Custo extra por fato:** 1 chamada Haiku (~10 tokens out, classificação binária). Cap por turno: 5 fatos × Haiku = ~$0.0001 — rounding error.

**Mitigação de falso-positivo:** review trigger explícito ("Detecção automática de updates ter precision < 0.85 → vira ADR superseder"). Auditável via `supersedes_id` — caso a caso revisável.

**Multi-tenant:** comparação só dentro do mesmo `business_id` + `user_id`.

### 4. Tool MCP nova — `memoria-historica`

Padrão das tools de [`Modules/Copiloto/Mcp/Tools/`](../../Modules/Copiloto/Mcp/Tools/) (similar a [`MemoriaSearchTool`](../../Modules/Copiloto/Mcp/Tools/MemoriaSearchTool.php)).

| Campo schema | Tipo | Default | Descrição |
|---|---|---|---|
| `query` | string | obrig. | Termos de busca |
| `business_id` | int | obrig. | Tenant |
| `as_of` | string (date) | `null` | Time-travel — null = agora; ISO 8601 = ponto no tempo |
| `time_dim` | enum | `event` | `system` ou `event` (qual dimensão temporal usar) |
| `limit` | int | 5 | Top-N |

Filtro SQL gerado:
```sql
-- as_of preenchido + time_dim=event
WHERE business_id = ?
  AND event_valid_from <= ?as_of
  AND (event_valid_until IS NULL OR event_valid_until > ?as_of)

-- as_of preenchido + time_dim=system
WHERE business_id = ?
  AND valid_from <= ?as_of
  AND (valid_until IS NULL OR valid_until > ?as_of)

-- as_of NULL = filtros atuais (igual ao recall existente)
```

Resposta inclui `valid_from`, `valid_until`, `event_valid_from`, `event_valid_until`, `supersedes_id` pra LLM raciocinar sobre o tempo se quiser.

### 5. Testes anti-regressão (Pest, mesmo padrão das ADRs anteriores)

- `tests/Feature/Memory/BiTemporalSchemaTest.php` — colunas existem, default `event_valid_from = valid_from`, índices criados.
- `tests/Feature/Memory/SupersedeChainTest.php` — `atualizar()` preenche `supersedes_id`; encadeamento N níveis recupera via JOIN recursivo.
- `tests/Feature/Memory/EventTimeQueryTest.php` — query "Pedro em 15/04" retorna fato Pedro mesmo após demissão registrada em 10/05.
- `tests/Feature/Memory/AutomaticSupersedeTest.php` — `ExtrairFatosAgent` detecta contradição semântica e marca `supersedes_id` corretamente; precision ≥ 0.85 em fixture.
- `tests/Feature/Mcp/MemoriaHistoricaToolTest.php` — `time_dim=event` com `as_of` retorna estado correto; `business_id` scope respeitado.

## Justificativa

**Por que bi-temporal e não só uni-temporal?**

Uni-temporal já está em prod e cobre 80% dos casos. Os 20% restantes são justo onde o LongMemEval mostra que LLMs comerciais perdem — atualização retroativa de conhecimento. Exemplo concreto: Larissa registra em 30/05 que "demiti o Pedro em 01/05". Uni-temporal marca o fato com `valid_from=30/05`. Bi-temporal permite que o LLM responda corretamente "Pedro estava ativo em 15/05?" (não — ele saiu em 01/05, mesmo que sistema só ficou sabendo dia 30).

**Por que adicionar `supersedes_id` em vez de derivar de timestamp?**

Auditoria. Hoje o supersede é implícito: "olha o fato com `valid_until` setado e procura outro com `valid_from` na mesma janela e fato similar". É frágil. `supersedes_id` torna o link explícito e queryable em 1 JOIN — viabiliza UI Wagner mostrar histórico de cada fato.

**Por que detecção automática no `ExtrairFatosAgent` em vez de manual?**

Hoje quase ninguém chama `atualizar()` manualmente. Resultado: a tabela enche de fatos contraditórios coexistindo com `valid_until=NULL` em todos. O recall hybrid traz N fatos contraditórios pro contexto, e o LLM precisa decidir qual usar — gera resposta ruim. Custo de Haiku é negligível ($0.0001/turno cap), benefício é dedup-em-tempo-real.

**Por que tool MCP nova em vez de estender `memoria-search`?**

`memoria-search` tem semântica clara ("memória atual"). Adicionar `as_of` opcional polui — todo caller precisa entender bi-temporal. Tool dedicada deixa o caso comum simples e o avançado disponível sob demanda.

**Por que NÃO migrar pra Zep/Graphiti completo?**

ADR 0036 lista 5 triggers concretos pra reavaliar Meilisearch. Nenhum disparou. Zep/Graphiti tem dependência Neo4j (operações Wagner não quer manter). MySQL bi-temporal cobre 90% do ganho a 10% do custo de operação.

## Consequências

**Positivas:**
- Resolve "knowledge updates" (LongMemEval gap mais doloroso) sem trocar stack.
- `supersedes_id` viabiliza UI de auditoria de evolução de fato (futuro).
- Dedup-em-tempo-real reduz contradições no contexto → resposta LLM mais confiante.
- Tool `memoria-historica` destrava casos de uso de auditoria (ROI Larissa: "qual era o estoque dia 30/04 que SABÍAMOS").

**Negativas / Trade-offs:**
- 3 colunas + 2 índices novos em `copiloto_memoria_facts`. Tabela já é uma das maiores do Copiloto — esperado.
- Detecção automática gera ~1 chamada Haiku extra por fato extraído. Custo cap: $0.0001/turno. Acceptable.
- Falso-positivo de detecção (supersede indevido) é risco real. Mitigação: precision ≥ 0.85 em fixture, log auditável de cada decisão, review trigger formal.
- LLM precisa entender a diferença entre as 2 dimensões pra usar bem — mais peso no prompt do `ChatCopilotoAgent`. Aceitável (pequeno).

**Riscos mitigados:**
- Migration retro-compatível: defaults garantem comportamento atual quando colunas não preenchidas.
- `MeilisearchDriver::buscar()` mantém filtro de `valid_until` atual quando `as_of` não passado — recall padrão inalterado.
- `core_memory` e `hits_count` não tocados — promoção de fatos mantém funcionando.

## Como medir sucesso

Após sprint de implementação + 30 dias em prod:

| Métrica | Alvo | Como medir |
|---|---|---|
| Recall@5 LongMemEval-PT (knowledge updates) | +5pp vs baseline atual | Re-rodar `php artisan copiloto:gabarito:avaliar --business=4 --top=5` |
| Precision detecção automática supersede | ≥ 0.85 | Fixture de 50 pares fato/contradição revisado por Wagner |
| Latência p95 recall | mantém (não pode subir) | OTel `gen_ai.recall.duration_ms` |
| Custo Haiku extra (`ExtrairFatosAgent`) | ≤ $0.0001/turno | OTel `gen_ai.cost.token_out` segregado |
| Uso da tool `memoria-historica` | ≥ 1 query/semana por dev | `mcp_audit_log` filter |
| Falso-positivo supersede reportado | < 5% das supersedences | Wagner reviewa amostra mensal de `supersedes_id IS NOT NULL` |

Se Recall@5 não subir 5pp em 30 dias → ADR fica com tarefa de débito ("revisar threshold de detecção automática"). Se precision < 0.85 → desabilitar detecção automática (só uni-temporal manual fica).

## Plano de implementação (sprint, 4 dias úteis)

| Dia | Entrega | Files tocados |
|---|---|---|
| 1 | Migration bi-temporal + Entity update + 2 testes schema | 1 migration + `CopilotoMemoriaFato.php` + 2 tests |
| 2 | `MeilisearchDriver::atualizar()` preenche `supersedes_id` + `MeilisearchDriver::buscar()` aceita `as_of` opcional + 2 testes | 1 service + 2 tests |
| 3 | `ExtrairFatosAgent` ganha detecção automática (chamada Haiku + threshold 0.85) + fixture 50 pares + 1 teste | 1 agent + fixture + 1 test |
| 4 | Tool MCP `memoria-historica` + registro em `OimpressoMcpServer` + 1 teste + smoke | 1 tool + server alterado + 1 test |

**Pré-requisito:** golden set LongMemEval-PT (50 perguntas Larissa-style — MEM-MET-5) já tem que existir pra medir baseline antes de sprint. Se não existir, sprint não começa.

## Não-decisões deliberadas (fora deste P1)

- **Backfill de `event_valid_from` retroativo em fatos existentes** — caro e arriscado. Defaults preservam comportamento; backfill vira ADR específica se demanda aparecer.
- **UI `/copiloto/admin/memoria/history`** — entra com P3 (ADR 0072 P3) ou demanda Wagner.
- **Fact-checking automático contra ContextoNegocio** — não é P1. ADR de "validação semântica de fatos contra source of truth" é trabalho separado.
- **Substituir Meilisearch por knowledge graph** — ADR 0036 mantém. Reavaliar só se 5 triggers dispararem.

## Erratum — 2026-05-05 (mesmo dia, levantamento exaustivo)

Levantamento confirmou que o ADR 0074 estava **certo no diagnóstico** (uni-temporal já em prod) mas **errou na estimativa de esforço**:

1. **Esforço real é menor que estimado.** Plano original: **4 dias úteis**. Plano revisado pelo levantamento: **1.5–2 dias úteis**. Razão: as 3 colunas novas (`event_valid_from`/`event_valid_until`/`supersedes_id`) são migration trivial; estender [`MeilisearchDriver::atualizar()`](../../Modules/Copiloto/Services/Memoria/MeilisearchDriver.php) é ~5 linhas; tool `memoria-historica` é cópia de [`MemoriaSearchTool`](../../Modules/Copiloto/Mcp/Tools/MemoriaSearchTool.php). Os testes Pest (estimativa 0.5d cada × 5) seguem padrão das suites já existentes.

2. **Detecção automática no `ExtrairFatosAgent` é menor que parecia.** Levantamento mostrou que o agente já é sofisticado — extrai 5 categorias estruturadas (`meta`, `preferencia`, `restricao`, `contexto`, `acao_pendente`) com lógica anti-fabricação. A mudança é **~50 linhas + 1 chamada Haiku** no fluxo, não rewrite. Estimativa: 0.5 dia mantém.

3. **Pré-requisito do golden set** (mencionado no plano): **NÃO é mais bloqueio**. Tabela `copiloto_memoria_gabarito` + seeder de 50 perguntas Larissa-style **existem desde 2026-04-29**. ADR 0072 (mesma data, errata adicionada) já corrige isso.

4. **Métricas obrigatórias** ([ADR 0050](0050-metricas-obrigatorias-memoria-table.md)) já em prod — `memoria_recall_chars`, `recall_at_3`, `precision_at_3`, `mrr_at_10`, `latency_p50/p95_ms`, `cost_token_in/out` na tabela `copiloto_memoria_metricas`. Bi-temporal pode reusar essas métricas pra medir ganho — **não precisa criar telemetria nova**.

**O que NÃO muda:** decisão central (3 colunas + tool nova + detecção automática), arquitetura, gates de sucesso (Recall@5 +5pp em 30 dias), trade-offs.

**O que muda:** estimativa de sprint passa de **4 dias** para **2 dias**. Plano dia-a-dia compactado.

**Status:** mantido em `proposto`. Pronto para implementação após P0.

## Referências

- [ADR 0072 — Maturação memória + Team MCP (P0–P3)](0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md)
- [ADR 0073 — Team MCP P0 skills/policies](0073-team-mcp-skills-policies-entidades-governadas.md)
- [ADR 0049 — Camadas memória agente fase a fase](0049-camadas-memoria-agente-fase-por-fase.md)
- [ADR 0050 — Métricas obrigatórias memória](0050-metricas-obrigatorias-memoria-table.md)
- [ADR 0052 — ContextoNegocio expor múltiplos ângulos](0052-contextonegocio-expor-multiplos-angulos.md)
- [Zep / Graphiti paper arXiv 2501.13956](https://arxiv.org/abs/2501.13956)
- [LongMemEval paper arXiv 2410.10813](https://arxiv.org/abs/2410.10813)
- [Migration `copiloto_memoria_facts`](../../Modules/Copiloto/Database/Migrations/2026_04_27_000001_create_copiloto_memoria_facts_table.php)
- [Migration promotion](../../Modules/Copiloto/Database/Migrations/2026_04_29_500002_add_promotion_to_memoria_facts.php)
- [`MeilisearchDriver`](../../Modules/Copiloto/Services/Memoria/MeilisearchDriver.php)
- [`ExtrairFatosAgent`](../../Modules/Copiloto/Ai/Agents/ExtrairFatosAgent.php)
