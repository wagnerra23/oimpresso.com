# ADR TECH-0002 (EvolutionAgent) · Eval LLM-as-Judge em CI

- **Status**: accepted
- **Data**: 2026-04-26
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Wagner: "precisa ter testes claros de desempenho. comece pequeno e teste a evolução."

Pest tradicional (assert string equals) não funciona pra agentes — saída é texto não-determinístico. Precisamos avaliar:
- Accuracy: agente acertou a essência?
- Citations: as fontes citadas estão certas?
- Tokens: gastou demais?
- Cost: dentro do orçamento?
- Latency: <5s?

Vizra ADK tem **Evaluation Framework com LLM-as-Judge** built-in.

## Decisão

**Golden set** de **5 perguntas-fixtures** em `tests/Eval/golden.yml`, executadas por:

1. **Vizra Eval Framework** — LLM-as-Judge usa Opus 4.7 (modelo diferente do agente) pra pontuar [accuracy, citations_correct] em 0-100.
2. **Pest wrapper** (`tests/Pest/Evolution/EvalTest.php`) — executa o eval, lê resultado JSON, falha teste se score regrediu >5%.

### Fluxo

```
gh actions: PR opened
  ↓
composer install
  ↓
php artisan evolution:index --rebuild
  ↓
php artisan evolution:eval --baseline=memory/evolution/baseline.json
  ↓ (se score >= baseline - 5%)
PASS → comenta no PR com diff de score
  ↓ (senão)
FAIL → bloqueia merge
```

### Golden set inicial

| ID | Escopo | Pergunta | Esperado conter |
|---|---|---|---|
| GOLD-001 | Financeiro | "Qual próximo passo Financeiro depois da Onda 2?" | menção ao backfill purchases legadas em `due` |
| GOLD-002 | PontoWr2 | "Top 3 moves Tier A do PontoWr2?" | Dashboard vivo + ADR ui/0002 |
| GOLD-003 | Cms | "Status atual do redesign Inertia/React?" | PR1 commit aabe142d + branch claude/cms-react-redesign |
| GOLD-004 | Copiloto | "Tenancy do Copiloto é multi-tenant?" | business_id nullable + adapter LaravelAI-ou-OpenAI |
| GOLD-005 | Geral | "Qual feature de maior ROI pros próximos 6 meses?" | ADR 0026 + 3 features (PricingFpv + Copiloto v1 + CT-e) |

Baseline `memory/evolution/baseline.json` commitado, atualizado manualmente via `evolution:eval --update-baseline` quando agente melhora intencionalmente.

### Métricas registradas

- `score_accuracy_avg` (0-100, target ≥80)
- `score_citations_avg` (0-100, target ≥90)
- `tokens_input_avg`
- `tokens_output_avg`
- `cost_avg_usd`
- `latency_avg_ms`

Dashboard Vizra mostra histórico; CI comenta delta no PR.

## Consequências

**Positivas:**
- Regressão detectada antes de prod.
- Baseline versionado = auditável (PR review consegue ver "score subiu de 78 → 85").
- Custo de eval: ~$0.10/run × 30 runs/mês = ~$3/mês.
- Dois modelos diferentes (judge ≠ agente) reduz viés.

**Negativas:**
- LLM-as-Judge não-determinístico em si; mitigação = `temperature=0` + `seed=42` no judge + média de 3 runs.
- Golden set precisa manutenção quando código muda. Mitigação = revisar a cada release minor.
- Falsa-falha possível em CI; toggle `EVOLUTION_EVAL_BLOCK_PR=false` se virar dor.

**ROI estimado**: ~4× (1 regressão evitada/mês = 1 dia de debug).

## Alternativas consideradas

| Alt | Motivo de rejeição |
|---|---|
| Pest com asserts manuais | Não funciona pra texto não-determinístico. |
| Eval só local, sem CI | Wagner não vai rodar consistente. CI é o único gate confiável. |
| Score binário (pass/fail) sem judge | Perde nuance; "78 → 85" é informação útil. |
| Judge humano (Wagner) | Não escala; vira dor após 2 semanas. |
| Self-judge (mesmo modelo) | Viés conhecido; Anthropic recomenda modelo diferente. |

## Links

- [Vizra Evaluation Framework docs](https://docs.vizra.ai/evaluations)
- [SPEC §7 US-EVOL-004](../../SPEC.md#us-evol-004--eval-golden-set)
