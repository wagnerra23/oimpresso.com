# Sessão 2026-04-29 — Sprint memória completa (8 entregas em 1 dia)

**Branch:** `main` · **Cycle:** 01 (29-abr → 12-mai) · **Modo:** Wagner solo (ADR 0047)
**Continuação de:** `2026-04-28-meilisearch-vaultwarden.md` (Copiloto IA real entrou em prod fim de tarde)
**Implementador:** Wagner [W] + Claude (1M context)

---

## Pretensão de entrada

Wagner pediu (cedo da manhã): *"gere as próximas tarefas/sprint comita e merge. gostaria de melhorar a memória do agente, assim economiza tokens e melhora a acertividade. procura na memória sobre o assunto e mova todas as tarefas da equipe para eu fazer. prepare as prioridades."*

Estado de entrada:
- ✅ IA real respondendo Larissa em prod desde 28-abr (gpt-4o-mini)
- ⚠️ Gap 1: ChatCopilotoAgent "burrinho" — sem ContextoNegocio (ADR 0046)
- ⚠️ Gap 2: MeilisearchDriver::buscar usa Scout default — full-text only, recall=0 mesmo com fato indexado
- 50 testes Pest passando, suite Copiloto inteira

---

## ✅ 8 entregas em prod (em ordem cronológica)

### 1. ADR 0047 — Wagner solo + sprint memória (`da6ce166`)

Formaliza modo solo (todos os donos F/M/L/E → W) + sprint priorizado por impacto×esforço:

- **P0 hotfixes** esta semana: MEM-HOT-1 (hybrid embedder) + MEM-HOT-2 (ContextoNegocio)
- **P1 Sprint 8** semana 2: SemanticCacheMiddleware, ConversationSummarizer, ProfileDistiller
- **P2** semana 3: Golden set RAGAS + RRF tuning

### 2. MEM-HOT-1 — Hybrid embedder no MeilisearchDriver (`c631042c`)

Substituiu Scout default por callback que sobrescreve search params com:
```php
'hybrid' => ['embedder' => 'openai', 'semanticRatio' => 0.7],
'filter' => "business_id = X AND user_id = Y",
'limit'  => $topK,
```

Config defaults atualizados pra bater com prod (`embedder='openai'`, `semantic_ratio=0.7`).
2 testes Pest novos com engine fake capturando params.

**Validação prod:** smoke `buscar(4, 9, 'qual a meta de faturamento')` retornou **2 hits** (era 0). Log conversa real Larissa: `memoria_recall_chars: 190` (era sempre 0).

### 3. MEM-HOT-2 — ContextoNegocio no ChatCopilotoAgent (`2be9930c`)

`ChatCopilotoAgent($conv, $memoria, ?ContextoNegocio $ctx)` — ctx opcional, BC-compat. `instructions()` formata bloco compacto (~150-250 tokens):

```
CONTEXTO DO NEGÓCIO (dados reais — use estes números, não invente):
EMPRESA: ROTA LIVRE (id 4)
DATA HOJE: 2026-04-29
CLIENTES ATIVOS: 5993
FATURAMENTO ÚLTIMOS 90 DIAS (por mês):
  2026-01: R$  4.140,82
  2026-02: R$ 26.045,44
  2026-03: R$ 38.215,07
  2026-04: R$ 31.513,29
```

`LaravelAiSdkDriver::responderChat` chama `ContextSnapshotService::paraBusiness` com degradação silenciosa.
`ContextSnapshotService::metasAtivas()` saiu de TODO pra query real (top 5 meta+período+apuração).

**Validação prod:** prompt biz=4 = 657 chars / **164 tokens** (target 150-250 ✅). 6 testes Pest novos.

### 4. ADRs 0048-0050 — Pesquisa Wagner consolidada (`793f3efa`)

Wagner enviou ZIP `files.zip` com 6 ADRs do módulo IA do **WR2/UltimatePOS** (sistema separado mas mesma stack). Análise de aproveitamento:

| Wagner | oimpresso | Aproveitamento |
|---|---|---|
| ADR-001 Vizra rejeitada | **0048** | 100% — Vizra quebrou no L13, **COP-015 cancelada**, `laravel/ai` consolidado |
| ADR-002 6 camadas memória | **0049** | Conceito + gate Recall@3>0.80 antes de evoluir camada |
| ADR-003 MySQL+Meilisearch | **0036 estendida** | Benchmark **BM25+vetor=95.2% LongMemEval** (supera Mem0 93.4%, Zep 71.2%) + 5 triggers concretos |
| ADR-004 8 métricas + memory_metrics | **0050** | 100% — tabela + comando + 5 tasks MEM-MET-1..5 |
| ADR-005 Assistente WR2 | NÃO importado | Produto separado (WhatsApp+browser) |
| ADR-006 MCP server | Backlog P3 | `laravel/mcp` futuro |

### 5. ADR 0051 + MEM-MET-1 — Schema próprio + tabela métricas (`21644f4e`)

Após pergunta do Wagner *"a tendência é sempre ir para o padrão correto?"*, disparei pesquisa de tendências. Achados:

- `laravel/ai` 1.0 (17-mar-2026) **NÃO oferece** eval framework nem multi-tenancy nativos (zero issues abertas)
- Comunidade Laravel multi-tenant (Spatie/Stancl) usa **adapter pattern** sobre o SDK
- **Padrão 2026 emergente**: schema próprio NO BANCO + emissão **OpenTelemetry GenAI** (`gen_ai.*` semantic conventions) — Datadog, Langfuse, Arize plugam por traces, não por schema

**ADR 0051** formaliza estratégia híbrida com 4 pilares + triggers trimestrais pra reavaliar:
1. Schema próprio (multi-tenant + LGPD)
2. Adapter sobre `Laravel\Ai\Contracts\ConversationStore` quando precisar
3. Métricas RAGAS-aligned (faithfulness, answer_relevancy, context_precision)
4. Emissão `gen_ai.*` OTel — observability free

**MEM-MET-1** criou `copiloto_memoria_metricas` em prod com 14 colunas:
- 8 obrigatórias (ADR 0050): recall_at_3, precision_at_3, mrr, latencia_p95_ms, tokens_medio_interacao, memory_bloat_ratio, taxa_contradicoes_pct, cross_tenant_violations
- 3 RAGAS-aligned (ADR 0051): faithfulness, answer_relevancy, context_precision
- 3 contexto: total_interacoes_dia, total_memorias_ativas, detalhes JSON

Entity `MemoriaMetrica` com cast `date:Y-m-d`, scopes `doBusinessOuPlataforma`/`ultimosDias`, helpers `metricasObrigatorias()`/`metricasRagas()`. 7 testes Pest (com `beforeEach Schema::create` em vez de `RefreshDatabase` — migrations core UltimatePOS quebram em SQLite).

### 6. MEM-OTEL-1 — Emissão OpenTelemetry GenAI (`5acf27de`)

Log channel `otel-gen-ai` (daily, 30d) + método `emitirOtelGenAi()` no driver. Cada call ao `responderChat` gera 1 evento estruturado com 12 atributos OTel-compliant:

```json
{
  "gen_ai.system":"openai",
  "gen_ai.request.model":"gpt-4o-mini",
  "gen_ai.operation.name":"chat",
  "gen_ai.response.duration_ms":1234,
  "gen_ai.business_id":4,
  "gen_ai.user.id":9,
  "gen_ai.conversation.id":42,
  "gen_ai.copiloto.prompt_chars":21,
  "gen_ai.copiloto.driver":"laravel_ai_sdk",
  "gen_ai.usage.input_tokens":350,
  "gen_ai.usage.output_tokens":120,
  "gen_ai.response.finish_reason":"stop"
}
```

Em erro: `gen_ai.error.type` + `gen_ai.error.message` + `finish_reason="error"`. Falha silente (Log channel error não quebra chat). 5 testes Pest.

### 7. MEM-MET-2 — Comando `copiloto:metrics:apurar` (`6d2dc7eb`)

Service `MetricasApurador` com 7 métodos isolados (uma fonte por métrica):

| Métrica | Fonte |
|---|---|
| `latencia_p95_ms` | Log `otel-gen-ai` (parse JSON lines, p95 de `gen_ai.response.duration_ms` filtrado por business) |
| `tokens_medio_interacao` | DB `copiloto_mensagens.tokens_in + tokens_out` (assistant) |
| `total_interacoes_dia` | DB `copiloto_mensagens.role='user'` count no dia |
| `total_memorias_ativas` | DB `copiloto_memoria_facts.valid_until IS NULL` count |
| `memory_bloat_ratio` | Heurística: % fatos com valid_from <= 30d / total ativos |
| `taxa_contradicoes_pct` | Heurística: pares ativos com prefixo dup no mesmo (biz, user) |
| `cross_tenant_violations` | 0 default (audit trimestral red-team) |

Comando: `php artisan copiloto:metrics:apurar [--date=YYYY-MM-DD] [--business={ID|all|plataforma}]`. Idempotente via `updateOrCreate(apurado_em, business_id)`. Métricas RAGAS ficam NULL até golden set MEM-P2-1.

9 testes Pest cobrindo cada apurador isolado + integração + idempotência.

**Baseline 2026-04-29 gravado em prod** (3 linhas):

```
| id | apurado_em | biz | p95_ms | tokens | inter | mem | bloat | contr |
|----|------------|-----|--------|--------|-------|-----|-------|-------|
|  1 | 2026-04-29 |NULL |   1234 |    307 |     6 |   2 | 1.000 |  0.00 |
|  2 | 2026-04-29 |   1 |   NULL |   NULL |     0 |   0 |  NULL |  NULL |
|  3 | 2026-04-29 |   4 |   1234 |    307 |     6 |   2 | 1.000 |  0.00 |
```

### 8. Comandos auxiliares + commits status (`da6ce166` → `6aa9b524`)

- `da6ce166` — feat memória solo + ADR 0047
- `21eb23c1` — A1 status fechado
- `2be9930c` — A2 MEM-HOT-2
- `64df2da4` — A2 status fechado
- `793f3efa` — ADRs 0048-0050 + 0036 estendida
- `21644f4e` — MEM-MET-1 + ADR 0051
- `81777d29` — MEM-MET-1 status fechado
- `5acf27de` — MEM-OTEL-1
- `4a9753f8` — MEM-OTEL-1 status fechado
- `6d2dc7eb` — MEM-MET-2
- `6aa9b524` — MEM-MET-2 baseline confirmado em prod

---

## 📊 Métricas da sessão

| Métrica | Antes (28-abr) | Depois (29-abr) |
|---|---|---|
| Suite Copiloto | 50 passed | **77 passed (+27)** |
| Testes skipped | 3 | 3 (mesmos) |
| Regressões | — | **0** |
| ADRs no repo | 47 (0001-0047) | **51 (0001-0051)** |
| Tabelas Copiloto | 8 | 9 (`copiloto_memoria_metricas` nova) |
| Comandos artisan | 0 do Copiloto | 1 (`copiloto:metrics:apurar`) |
| Log channels | 1 (`copiloto-ai`) | 2 (+ `otel-gen-ai`) |
| `memoria_recall_chars` em prod | 0 | **190** ✅ |
| Tokens injetados system prompt | 0 (genérico) | **164** com dados reais ROTA LIVRE |

---

## 🎯 Estado das 6 camadas de memória (ADR 0049)

| Camada | Estado 28-abr | Estado 29-abr |
|--------|---------------|---------------|
| Working | ⚠️ ContextoNegocio existia mas não usado | ✅ Injetado via MEM-HOT-2 |
| Conversation History | ✅ últimos 20 msgs | ✅ (sem mudança) |
| Episodic | ⚠️ parcial (valid_from/valid_until) | ⚠️ parcial (sem mudança) |
| Semantic / RAG | ⚠️ MeilisearchDriver com bug Scout | ✅ Hybrid embedder ativo via MEM-HOT-1 |
| Procedural | ✅ system prompts + agents | ✅ (sem mudança) |
| Reflective | ❌ não-iniciado | ❌ (gate em ADR 0049 — só após 100+ interações) |

---

## 🔜 Pendências pós-sessão (ordem recomendada)

| Próximo | Esforço | DoD |
|---|---|---|
| **MEM-MET-3** Scheduler diário | 0.25d | `Console/Kernel.php->daily()` chama `copiloto:metrics:apurar --all` |
| **A4** Validar Larissa | 0.5d | "qual meu faturamento de março?" → R$ 38.215,07 |
| **MEM-MET-5 = COP-002** Golden set 50 perguntas Larissa-style | 1.5d | CSV em `tests/fixtures/copiloto/golden_set_v1.csv`; destrava 6 colunas RAGAS |
| **MEM-MET-4 = COP-007 ampl.** Page `/copiloto/admin/qualidade` trend 30d | 2d | Lê `copiloto_memoria_metricas` + plota tendência |
| **MEM-S8-1** SemanticCacheMiddleware (-68.8% tokens) | 1.5d | Cache hit rate >30% após 10 convs similares |
| **MEM-S8-2** ConversationSummarizer (>15 turnos) | 1.5d | Conv 20 turnos usa <2.000 tokens contexto |
| **MEM-S8-3** ProfileDistiller (job diário <300 tokens) | 1d | Profile aparece no system prompt |

---

## 🧠 Decisões consolidadas (referência rápida)

- **Vizra ADK rejeitada** oficialmente (ADR 0048) — quebrou L13, fica em `laravel/ai`
- **Schema próprio + adapter + OTel GenAI** (ADR 0051) — confirmada como estratégia 2026 sustentável
- **Recall@3 > 0.80 com gabarito real** (ADR 0049) — gate obrigatório pra evoluir camada
- **Métricas RAGAS-aligned**: `faithfulness`, `answer_relevancy`, `context_precision` reconhecidas nativamente por Langfuse/RAGAS
- **OpenTelemetry GenAI** (`gen_ai.*`) emitindo desde MEM-OTEL-1 — Datadog/Arize plugam de graça depois

---

## 💬 Quotes Wagner (transcrição parcial)

- *"sim aprovado"* (após análise de aproveitamento dos 6 ADRs)
- *"a tendência é sempre ir para o padrão correto?"* (gatilho da pesquisa de tendências)
- *"acho que aqui pode pesquisar e ver as tendências"* (autorização da pesquisa)
- *"acho que teremos que evoluir em algum ponto. o que recomenda."* (gatilho da decisão estratégica formal)
- *"ok"* (após cada propostas concreta)

---

## Pendências críticas pra sex 02-mai (Cycle 01)

| # | Item | Estado |
|---|------|--------|
| A4 | Validar Larissa "qual meu faturamento" → R$ 38.215,07 | ⏳ aguarda Larissa |
| Goal #1 do Cycle | Larissa pergunta faturamento e recebe resposta correta | 🟡 código deployed; aguarda validação real |
| Goal #2 do Cycle | `memoria_recall_chars > 0` | ✅ bateu 190 em prod |
| Goal #3 do Cycle | Dashboard `/copiloto/admin/custos` validado + merged | ⏳ |

---

> **Próxima sessão:** abrir `CURRENT.md` + este session log + escolher próximo da fila (recomendado: MEM-MET-3 + A4).
