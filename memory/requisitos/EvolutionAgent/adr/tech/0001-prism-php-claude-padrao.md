# ADR TECH-0001 (EvolutionAgent) · Prism PHP + Claude default + tier por tarefa

- **Status**: accepted
- **Data**: 2026-04-26
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Vizra ADK suporta multi-LLM via **Prism PHP**. Wagner: "usando o prisma podemos conectar em varios llm , mais quero o clude como principal."

Modelos Claude disponíveis (abril 2026):
- Opus 4.7 — $15/M input, $75/M output — raciocínio pesado
- Sonnet 4.6 — $3/M input, $15/M output — balanced
- Haiku 4.5 — $0.80/M input, $4/M output — extração/tags

Embeddings:
- OpenAI text-embedding-3-small — $0.02/M
- Voyage-3-lite — $0.02/M (recomendado pela Anthropic, melhor PT-BR)
- Voyage-3-large — $0.12/M

## Decisão

### Geração (Claude por tarefa)

| Tarefa | Modelo | Razão |
|---|---|---|
| Router/triage do `EvolutionAgent` | **Sonnet 4.6** | Decisão simples; Sonnet é suficiente |
| Sub-agents (Financeiro/Ponto/etc) | **Sonnet 4.6** | 90% das queries; balanço custo/qualidade |
| LLM-as-Judge no eval | **Opus 4.7** | Modelo diferente do agente (evita viés); reasoning forte |
| Extração estruturada (chunking, tagging) | **Haiku 4.5** | Tarefa mecânica, alta volumetria |
| Escalation manual via flag `--model=opus` | **Opus 4.7** | Casos hard sob demanda |

### Embeddings

**Voyage-3-lite** ($0.02/M tokens). Razões:
- Recomendação oficial Anthropic.
- ~30% melhor recall em PT-BR vs OpenAI ada / text-embedding-3-small (benchmarks Voyage internos).
- Mesmo preço do OpenAI 3-small.
- API estável, MIT-friendly.

### Configuração (`.env`)

```
ANTHROPIC_API_KEY=sk-ant-...
VOYAGE_API_KEY=pa-...
EVOLUTION_DEFAULT_MODEL=anthropic.claude-sonnet-4-6
EVOLUTION_JUDGE_MODEL=anthropic.claude-opus-4-7
EVOLUTION_EXTRACTOR_MODEL=anthropic.claude-haiku-4-5
EVOLUTION_EMBEDDING_PROVIDER=voyage
EVOLUTION_EMBEDDING_MODEL=voyage-3-lite
EVOLUTION_MONTHLY_CAP_USD=30
```

Cap mensal hard via middleware Vizra: bloqueia geração se `vizra_evaluations` total do mês ≥ cap. Alarme via Telegram em 80%.

## Consequências

**Positivas:**
- ~5× cost saving vs Opus em tudo.
- Multi-modelo grátis via Prism (não precisamos manter cada SDK).
- LLM-as-Judge com modelo diferente reduz viés conhecido.
- Cap explícito previne surpresa em fatura.

**Negativas:**
- 3 modelos = 3 sets de quirks. Mitigação: cada agente fixa modelo via `protected string $model`.
- Voyage tem dependência externa (mais 1 chave).
- Cap rígido pode falhar eval em fim de mês — log + alarme antes.

**ROI estimado**: ~5× custo (mix Sonnet/Haiku vs Opus em tudo).

## Alternativas consideradas

| Alt | Motivo de rejeição |
|---|---|
| Opus em tudo | 5× mais caro sem ganho proporcional. |
| Sonnet em tudo (incl. judge) | Judge usar mesmo modelo do agente = viés. |
| Modelo OpenAI/Gemini default | Wagner explicit pediu Claude. |
| Voyage-3-large | 6× mais caro pra ~5% melhor recall. Não vale. |
| OpenAI text-embedding-3-small | Pior PT-BR; Wagner trabalha em PT-BR. |

## Links

- [Vizra Prism integration docs](https://docs.vizra.ai/integrations/prism)
- [Voyage embeddings models](https://docs.voyageai.com/docs/embeddings)
