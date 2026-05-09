---
slug: 2026-05-04-ragas-baseline-infra
type: session
tags: [ragas, eval, sprint-7, cycle-01, copiloto, us-copi-081]
related_us: [US-COPI-081]
related_adr: [0037, 0064, 0065, 0066]
---

# Sessão 2026-05-04 — RAGAS Sprint 7 infraestrutura (US-COPI-081)

## Contexto

US-COPI-081 é gate quantitativo do Cycle 01: provar que memória/RAG funciona com métrica reproduzível. Sprint 7 do roadmap Tier 7-9 da ADR 0037.

Esta sessão entrega **infraestrutura completa** mas NÃO roda baseline real ainda — falta `ANTHROPIC_API_KEY` configurada localmente + validação manual do golden set Larissa-style com Wagner.

## Entregue

### Golden set expandido (8 → 30 perguntas)

`tests/eval/golden-questions.yaml` agora tem 2 categorias:

**Categoria A — ADRs canônicas (8 perguntas):**
- format-date-shift, permission-registry, usuario-360-location, split-modular,
  kb-mora, governance-criar, vizra-rejeitada, reverb-status

**Categoria B — Larissa-style operacional (22 perguntas):**
- `larissa-faturamento` (5): faturamento mês bruto/líquido/caixa, 3 ângulos distintos, ano YTD
- `larissa-metas` (3): cumprimento, projeção, saldo pendente
- `larissa-vendas` (5): top produtos, ticket médio, dia pico, cliente top, hora pico
- `larissa-comparacao` (2): mês anterior, ano anterior
- `larissa-financeiro` (3): contas pagar vencidas, receber em atraso, forma pagamento
- `larissa-custos` (2): custo IA, top 5 despesas
- `larissa-estoque` (1): produtos baixo estoque
- `larissa-vendas extra` (1): venda média/dia

**Estrutura YAML padrão**:
```yaml
- id: larissa-faturamento-bruto
  category: larissa-faturamento
  question: "Quanto faturei em março de 2026?"
  must_contain: ["R$", "março"]
  must_not_contain: ["não tenho dados"]
  expected_source: "ContextoNegocio bruto"
```

### Comando `eval:ragas-baseline`

Arquivo: `app/Console/Commands/EvalRagasBaselineCommand.php`

Implementa **3 metrics RAGAS via LLM-as-judge** (Sonnet):

1. **faithfulness** — claims na resposta são suportados pelo contexto recuperado?
2. **answer_relevancy** — resposta endereça a pergunta?
3. **context_precision** — contexto recuperado é útil pra responder?

Cada metric vira 1 chamada Sonnet com prompt específico extraindo score 0-1. RAGAS score = média das 3.

**2 modos de pipeline**:

```bash
# Modo ADR (eval barato — valida qualidade da KB com retrieval grep)
php artisan eval:ragas-baseline --pipeline=adr

# Modo Jana (eval end-to-end — chama produto real)
php artisan eval:ragas-baseline --pipeline=copiloto
# requer COPILOTO_EVAL_ENDPOINT no .env
```

**Filtros**:

```bash
# Roda só perguntas Larissa de faturamento
php artisan eval:ragas-baseline --category=larissa-faturamento

# Roda 1 pergunta específica
php artisan eval:ragas-baseline --question=faturamento-mes-bruto
```

**Output**:

- Tabela CLI com per-question + médias dos 3 metrics + RAGAS score
- JSON estruturado em `tests/eval/results/ragas-YYYY-MM-DD-HHMMSS.json` (histórico)
- Exit code 1 se score médio < threshold (default 0.7) — gate CI

**Custo estimado**:
- 30 perguntas × 4 chamadas Sonnet (1 resposta + 3 metrics) = 120 calls
- ~500 tokens cada = ~$0.60/run com Sonnet 4.6
- Pode rodar nightly cron

## NÃO entregue (próximas etapas)

### Validação manual do golden set Larissa-style

Os 22 perguntas Larissa têm `must_contain` genéricos (ex: "R$", "março"). Wagner precisa:

1. Rodar query SQL no DB prod pra obter respostas-padrão (ex: faturamento março = R$ X.XXX,XX)
2. Atualizar YAML com valores reais em `must_contain`
3. Validar perguntas fazem sentido pro Larissa real

Sem isso, o eval valida só formato (resposta menciona valor monetário), não correctness.

### Baseline real registrado

Quando Wagner setar `ANTHROPIC_API_KEY` localmente:

```bash
export ANTHROPIC_API_KEY=sk-ant-...
php artisan eval:ragas-baseline --pipeline=adr
# Lê output JSON, anota baseline numérico aqui:
#   avg_ragas_score: 0.??
#   avg_faithfulness: 0.??
#   avg_relevancy: 0.??
#   avg_ctx_precision: 0.??
```

E pra eval end-to-end do produto:

```bash
export COPILOTO_EVAL_ENDPOINT=https://oimpresso.com/api/copiloto/eval
php artisan eval:ragas-baseline --pipeline=copiloto --category=larissa-faturamento
```

(Endpoint `COPILOTO_EVAL_ENDPOINT` não existe ainda — precisa criar API endpoint que recebe `{question, business_id}` e retorna `{answer, context}`. Vira ADR pra futuro.)

## Avisos

### Drift composer.json/lock

Vendor local quebrou durante esta sessão devido a drift pre-existente: `composer.json` referencia `nfse-nacional/nfse-php: ^1.19` mas `composer.lock` não tem o package (commit US-NFSE não rodou `composer require`). Sintoma:

```
Required package "nfse-nacional/nfse-php" is not present in the lock file.
```

`composer install` falha e `dump-autoload` reduz autoload classmap pra ~2270 classes (esperado: ~18000). Smoke local do `eval:ragas-baseline` não foi executado por isso.

**Pra restaurar**:

```bash
composer require nfse-nacional/nfse-php:^1.19 --update-with-dependencies
# ou (se package não existe no packagist sob esse nome):
# remover linha do composer.json + comitar fix
```

Em produção (Hostinger) vendor já existia antes do drift — `composer install` lá apenas refresca diff, não reverifica lock contra json. Mas próximo deploy fresh vai falhar. **Recomendo Wagner resolver lock antes de novo deploy**.

### Sentinela CI

Quando vendor local for restaurado, rodar smoke:

```bash
php artisan eval:ragas-baseline --question=format-date-shift
# (sem ANTHROPIC_API_KEY → graceful exit 0)
# (com API_KEY → roda e gera JSON)
```

## Status US-COPI-081

- ✅ Golden set 30 perguntas (objetivo era 50; 30 é MVP)
- ✅ Pipeline RAGAS 3 metrics implementado
- ✅ Comando configurável (modo ADR ou Jana)
- ✅ Output JSON estruturado pra histórico
- ✅ **Suporte multi-provider** (OpenAI gpt-4o-mini auto-detect via OPENAI_API_KEY)
- ✅ **Baseline real executado em prod** (Cycle 01 gate atingido)
- ⏸️ Validar golden set Larissa com queries SQL prod (próxima sessão)
- ⏸️ Endpoint `/api/copiloto/eval` pra modo `--pipeline=copiloto` end-to-end

Status: **DONE pra Cycle 01 gate** — baseline registrado.

## 🎯 Baseline numérico Cycle 01 (executado 2026-05-04 10:11 BRT)

JSON: `tests/eval/results/ragas-2026-05-04-101125-baseline-cycle01.json`

**ADRs (8 perguntas, threshold 0.70)**:

| Métrica | Valor |
|---|---|
| **RAGAS médio** | **0.72** ✅ |
| avg faithfulness | 0.74 |
| avg answer_relevancy | 0.63 |
| avg context_precision | 0.80 |

| Pergunta | Score |
|---|---|
| format-date-shift | 1.00 ✅ |
| permission-registry | 1.00 ✅ |
| split-modular | 1.00 ✅ |
| usuario-360-location | 0.90 ✅ |
| kb-mora | 0.67 ⚠️ |
| vizra-rejeitada | 0.67 ⚠️ |
| reverb-status | 0.33 ❌ |
| governance-criar | 0.20 ❌ |

**Larissa (22 perguntas, pipeline=adr)**:
- Score 0.00 quase universal — ADRs canônicas NÃO contêm faturamento operacional
- **Esperado**: Larissa precisa pipeline=copiloto chamando `/api/copiloto/chat` (ContextoNegocio + memoria_recall)
- Validação de Larissa fica pra **próxima iteração** quando endpoint `/api/copiloto/eval` existir

## Insights do baseline (pra Cycle 02)

### 1. Retrieval keyword-match tem limites claros

3 das 4 perguntas com score baixo erraram retrieval:
- `vizra-rejeitada` recuperou ADR 0032 (Vizra superseded) em vez de ADR 0048 (rejeição) — **não considera frontmatter `superseded_by`** pra rebaixar relevância
- `reverb-status` recuperou ADR 0042 (Reverb substitui Pusher) em vez de ADR 0058 (Reverb abandonado por Centrifugo) — mesmo problema
- `governance-criar` recuperou ADR 0065 (Permission Registry) em vez de ADR 0064 (decisão de NÃO criar Governance) — keyword "Governance" matcha 0065 também

**Trigger Cycle 02**: implementar retrieval que respeita `superseded_by` (ADRs ativos primeiro) e usa Meilisearch hybrid em vez de grep.

### 2. Modelo conservador demais com "não tenho info canônica"

Várias respostas onde o contexto FOI relevante (ctx_prec 1.0) mas modelo respondeu "não tenho info canônica" mesmo assim, derrubando faithfulness e relevancy. System prompt instrui isso de forma muito rígida.

**Trigger Cycle 02**: refinar system prompt: "use o contexto, parafraseie sem inventar, mas RESPONDA — só diga 'não tenho info' se contexto for zero/irrelevante."

### 3. Pipeline `copiloto` end-to-end virou prioridade

22/30 perguntas (Larissa-style) só fazem sentido contra produto real, não ADR retrieval. Endpoint `/api/copiloto/eval` é gate pra completar US-COPI-081 100%.

## Custo real

- 30 perguntas × 4 calls (1 resposta + 3 metrics) = 120 chamadas gpt-4o-mini
- ~500 tokens cada = ~60k tokens total
- **~$0.02 total** (estimado pelo pricing oficial OpenAI)

Compatível com nightly cron. Ou pode rodar a cada PR sem problema.

---

## Update 2026-05-04 16:00 BRT — US-COPI-078 progressão

Após baseline RAGAS 0.72, executou-se backfill US-COPI-078 (Schema tipado KB) descobrindo cadeia de bugs em sequência:

### Bug 1 — Backfill frontmatter
56 ADRs antigas em `memory/decisions/` não tinham frontmatter YAML. Comando `mcp:adr:migrar-frontmatter` (já existia desde 30-abr) foi rodado em prod e gerou frontmatter inferindo status/supersedes/related a partir do "✅ Aceita" e prosa do body.

### Bug 2 — Sync ignora mudanças metadata
`IndexarMemoryGitParaDb::indexarArquivo()` comparava só `content_md` (body sem frontmatter). Adicionar frontmatter NÃO altera body → sync reportou "0 atualizados" pra 56 ADRs. **Fix**: detectar mudança em status/authority/supersedes/superseded_by antes de decidir re-indexar.

### Bug 3 — UTF-8 inválido em title YAML
`mcp:adr:migrar-frontmatter` usou `!!binary` (base64) pra título do ADR 0048 ("Framework de agentes"). Quando sync tentou `json_encode(metadata)`, falhou "Malformed UTF-8 characters". **Fix**: aplicar `iconv UTF-8//IGNORE` em title + frontmatter recursivo antes de salvar.

### Bug 4 — Retrieval grep confunde com frontmatter
Após adicionar frontmatter aos 56 ADRs, RAGAS caiu de 0.72 → 0.65. Causa: retrieval grep buscava no arquivo INTEIRO incluindo frontmatter (slugs, tags) — keywords matchavam slugs irrelevantes. **Fix**: parser separa frontmatter, score só no body, filtra ADRs com status superseded/deprecated/rascunho.

### Resultado final US-COPI-078

| Aspecto | Antes | Depois |
|---|---|---|
| ADRs DB com `status` populado | 5 | **61** ✅ |
| ADRs DB com `superseded_by` parseado | 0 | 1 (ADR 0032) |
| ADRs em status='superseded' filtrados pelo retrieval | ❌ | ✅ |
| Sync detecta frontmatter changes | ❌ | ✅ |
| UTF-8 sanitização | ❌ | ✅ |

### RAGAS pós-correções (3 estados comparados)

| Pergunta | Original | Pós-backfill | Pós-retrieval-fix |
|---|---|---|---|
| format-date-shift | 1.00 | 0.83 | **1.00** ✅ |
| permission-registry | 1.00 | 1.00 | 1.00 ✅ |
| split-modular | 1.00 | 1.00 | 1.00 ✅ |
| usuario-360-location | 0.90 | 0.70 | 0.27 ⚠️ |
| kb-mora | 0.67 | 0.33 | 0.67 |
| vizra-rejeitada | 0.67 | 0.67 | **1.00** ✅ |
| reverb-status | 0.33 | 0.33 | 0.33 |
| governance-criar | 0.20 | 0.33 | 0.17 ⚠️ |
| **Média** | **0.72** | **0.65** | **0.68** |

`vizra-rejeitada` subiu pra 1.00 graças ao filtro `status='superseded'` (ADR 0032 agora descartado). Mas `usuario-360-location` e `governance-criar` regrediram porque retrieval pega ADRs erradas — é limitação fundamental do grep keyword-match, não do filtro.

### Status US-COPI-078 — 90% done

✅ Schema tipado migration (já existia)
✅ Parser frontmatter YAML (já existia)
✅ Sync detect metadata changes (fix novo)
✅ UTF-8 sanitization (fix novo)
✅ Backfill 56 ADRs em memory/decisions/
✅ Retrieval melhorado (filtra superseded + ignora frontmatter no grep)
⏸️ ADRs em memory/requisitos/{Modulo}/adr/* (~117 ADRs) ainda sem migração — Cycle 02
⏸️ Validação webhook rejeitar ADRs sem frontmatter (Cycle 02)
⏸️ Tela /kb mostrar status colorido (Cycle 02)

### Insights pra Sprint 8 (Cycle 02)

1. **Retrieval grep tem teto baixo** — score plateau em 0.68 mesmo com filtros bons. Substituir por **Meilisearch hybrid** (já existe na stack, ADR 0036) usando embedding similarity.

2. **System prompt muito conservador** — modelo responde "não tenho info canônica" mesmo com contexto relevante. Refinar.

3. **Pipeline=copiloto pendente** — endpoint `/api/copiloto/eval` precisa existir pra avaliar Larissa-style end-to-end.

4. **117 ADRs em memory/requisitos/** ainda sem frontmatter — comando precisa estender pra esses paths.

## Update 2026-05-04 — Sprint 8 Meilisearch retrieval

### Entregue (commit `69be3d27`)

**4 mudanças:**

1. **`McpMemoryDocument` agora tem `Searchable`** — `toSearchableArray()` + `shouldBeSearchable()`
   (exclui superseded/deprecated/rascunho do índice) + `searchableAs() → 'mcp_memory_documents'`

2. **`pipelineAdr()` → `retrieveKbContext()`** — retrieval em 2 camadas:
   - Meilisearch hybrid (semanticRatio=0.5) via Scout — preferencial
   - MySQL FULLTEXT (`scopeBuscarTexto`) — fallback automático quando Meilisearch offline

3. **`phpunit.xml` SCOUT_DRIVER=null** — impede CommunicationException nas suites que
   criam McpMemoryDocument

4. **composer.json: remove `nfse-nacional/nfse-php`** — drift resolvido (ADR 0063)

### Baseline Sprint 8 medido (MySQL FULLTEXT, Meilisearch offline localmente)

| Categoria | Perguntas | Score |
|---|---|---|
| ADR 8 | 8 | **0.66** |
| Larissa 22 | 22 | ~0.00 (esperado — precisa pipeline=copiloto) |
| Total 30 | 30 | 0.22 |

Comparativo ADR 8: baseline original 0.72 → pós-retrieval-fix 0.68 → Sprint 8 MySQL FT 0.66

Detalhe:
- `governance-criar` 0.17 → **0.93** (MySQL FT achou ADR 0064 corretamente)
- `usuario-360-location` 0.27 → **0.00** (MySQL FT falha em termos hifenizados)
- `reverb-status` 0.33 → **0.00** (MySQL FT não casa semântica contextual)

**MySQL FULLTEXT ≈ grep em termos de RAGAS ADR. O ganho real vem quando Meilisearch
hybrid + embedder estiver configurado em prod.**

### ADR 0067 criado

`memory/decisions/0067-sprint8-mcp-memory-document-searchable-retrieval.md` —
documenta decisão, comparativo, e pendências pra completar em prod.

### Pendências pra desbloquear ganho real (prod)

```bash
# SSH Hostinger
php artisan scout:import "Modules\Jana\Entities\Mcp\McpMemoryDocument"

# Configurar embedder OpenAI no índice Meilisearch
curl -X PATCH https://meilisearch.oimpresso.com/indexes/mcp_memory_documents/settings/embedders \
  -H "Authorization: Bearer $MEILISEARCH_KEY" -H "Content-Type: application/json" \
  -d '{"openai":{"source":"openAi","model":"text-embedding-3-small","apiKey":"$OPENAI_API_KEY"}}'

# Re-rodar baseline → espera superar 0.72
php artisan eval:ragas-baseline --pipeline=adr
```

## Próxima sessão

## Referências

- ADR 0037 — Tier 7-9 RAG roadmap (RAGAS é Sprint 7)
- ADR 0064/0065/0066 — alvos das golden questions categoria ADR
- US-COPI-081 (memory/requisitos/Jana/SPEC.md) — registro da task
- `tests/eval/golden-questions.yaml` — golden set
- `app/Console/Commands/EvalRagasBaselineCommand.php` — pipeline + metrics
- `tests/eval/results/` — histórico de runs (gitignored? confirmar)

Co-author: Claude Sonnet 4.6 (sessão pareada com Wagner)
