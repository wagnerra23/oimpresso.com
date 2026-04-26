# ADR TECH-0004 (EvolutionAgent) · Roteamento multi-provider via Prism PHP

- **Status**: accepted
- **Data**: 2026-04-26
- **Decisores**: Wagner
- **Categoria**: tech
- **Supersede parcialmente**: nada (estende ADR tech/0001)

## Contexto

ADR tech/0001 fixou Claude (Anthropic) como provider padrão por tarefa.
Wagner pediu integração de **OpenAI**, **DeepSeek** e **xAI (Grok)** pra ganhos
específicos:

- **DeepSeek-V3** (`deepseek-chat`): $0.27/M in vs Haiku 4.5 a $0.80/M — **~3× mais
  barato** em extração mecânica (chunking, tagging, reformulação). Quality
  empate-tecnico em tarefas de extração benchmarkadas (MMLU 88 vs 88).
- **xAI Grok-2-1212**: acesso nativo a contexto recente (X/web atual);
  útil pra "qual versão atual do nWidart/laravel-modules?" sem precisar
  rodar tool de fetch.
- **OpenAI gpt-4o-mini**: melhor JSON-mode em PT-BR + vision; usado só
  quando uma das tools explicitamente exige vision/structured.

Prism PHP já suporta os 3 providers nativamente (`Provider::OpenAI`,
`Provider::DeepSeek`, `Provider::XAI`). Trocar provider no `BaseAgent::run()`
é uma linha.

## Decisão

**Provider override em 3 níveis de precedência** (mais alto vence):

1. **CLI flag** `--model=<provider>:<modelo>` (ex: `--model=deepseek:deepseek-chat`)
2. **Env var por agent** `EVOLUTION_DEFAULT_MODEL` / `EVOLUTION_JUDGE_MODEL` /
   `EVOLUTION_EXTRACTOR_MODEL` (com `EVOLUTION_*_PROVIDER` opcional pra
   desambiguar)
3. **Hardcoded no agent** (`protected string $model` na classe)

Formato do model string: `<provider-slug>:<model-id>`. Exemplos:
- `anthropic:claude-sonnet-4-5` (default)
- `deepseek:deepseek-chat`
- `openai:gpt-4o-mini`
- `xai:grok-2-1212`

Quando `<provider-slug>:` é omitido, default é `anthropic`.

### Alocação por tarefa (refinada)

| Tarefa | Provider:Model | $/M in | $/M out | Por que |
|---|---|---|---|---|
| Router/triage | `anthropic:claude-sonnet-4-5` | $3 | $15 | reasoning + PT-BR |
| Sub-agents | `anthropic:claude-sonnet-4-5` | $3 | $15 | 90% das queries |
| **Judge eval** | `anthropic:claude-opus-4-5` | $15 | $75 | modelo ≠ agente (anti-viés) |
| **Extração** (chunking labels, tagging, summarization) | `deepseek:deepseek-chat` | **$0.27** | **$1.10** | 3× mais barato que Haiku, qualidade igual em extração |
| Vision / structured | `openai:gpt-4o-mini` | $0.15 | $0.60 | só sob demanda da tool |
| Realtime/news | `xai:grok-2-1212` | $2 | $10 | acesso a X/web atual |
| Fallback escalation | `anthropic:claude-opus-4-5` | $15 | $75 | flag manual `--model=opus` |

### Toggle de fallback

Quando o provider primário falha (rate limit, 5xx, key inválida) e
`EVOLUTION_FALLBACK_ENABLED=true`:

- `anthropic` → `deepseek` (texto geral, qualidade aceitável, cost-effective)
- `voyageai` (embeddings) → `hash-local` (HashEmbeddingDriver, offline determinístico)

Default: `false` — evita custo surpresa em provider B sem Wagner notar.

## Consequências

**Positivas:**
- Extração em volume (chunking labels) cai de ~$0.50/run pra ~$0.17/run com DeepSeek (3× saving).
- Wagner testa novos modelos com `--model=` sem editar código.
- Cap mensal continua valendo via `EVOLUTION_MONTHLY_CAP_USD` (somatório cross-providers).
- Layer Vizra-shaped do `BaseAgent` continua compatível — só adicionamos `resolveProvider($modelString)`.

**Negativas:**
- 4 providers = 4 sets de quirks. Mitigação: cada agent fixa modelo padrão; override é raro.
- Cap somando cross-providers é heurístico (Anthropic e DeepSeek tem cobranças separadas; a app só estima). Aceitável: revisar fatura mensal de qualquer jeito.
- Wagner agora tem 4 chaves pra rotacionar. Mitigação: passwordstore/1password.

**ROI estimado**: ~3× saving em extração + opcionalidade de vision/realtime sem custo se não usar.

## Alternativas consideradas

| Alt | Motivo de rejeição |
|---|---|
| OpenRouter como único entrypoint | Markup ~5%; quebra ADR tech/0001 (Claude direto). |
| Forçar Claude em tudo (sem DeepSeek) | Perde 3× saving em extração; Wagner explicit pediu DeepSeek. |
| Agent escolhe provider via LLM call | Custo + latência extra; allowlist hardcoded é mais previsível. |
| Trocar provider default pra DeepSeek | Quebra ADR tech/0001 + qualidade pior em reasoning crítico. |

## Implementação

- `BaseAgent::resolveProvider(string $modelString): array` — retorna `[Provider, modelo]`.
- `BaseAgent::run()` — usa `resolveProvider($this->model)` em vez de hardcoded `Provider::Anthropic`.
- Comandos `evolution:query` e `evolution:eval` ganham `--model=<provider>:<modelo>`.
- Novo `ExtractorTool` (Vizra-compat) usa DeepSeek por default — pra agentes que precisem
  reformular um chunk grande ou gerar tags estruturadas em volume.

## Re-avaliação

Re-avaliar este ADR se:
- DeepSeek-V3 sair do ar / mudar pricing pra >$1/M.
- Anthropic publicar Sonnet com $0.30/M (alinhar com DeepSeek).
- Wagner quiser mais que 4 providers ativos (adicionar OpenRouter como gateway único).

## Links

- [ADR TECH-0001](0001-prism-php-claude-padrao.md) — base
- [Prism providers](https://prismphp.com/providers/anthropic.html)
- [DeepSeek pricing](https://api-docs.deepseek.com/quick_start/pricing)
- [xAI pricing](https://console.x.ai/team/default/models)
