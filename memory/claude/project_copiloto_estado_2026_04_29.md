---
name: Estado do Copiloto IA em produção (2026-04-29 fim do dia)
description: Snapshot consolidado pós-sprint memória — 8 entregas em 1 dia, baseline gravado, estratégia formalizada
type: project
supersedes: project_copiloto_estado_2026_04_28.md
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
## 🎯 TL;DR

Sprint memória solo do Wagner em 1 dia (29-abr-2026): **8 entregas em produção**, suite Copiloto **50 → 77 passed (+27)**, **5 ADRs novos** (0047-0051), **baseline `copiloto_memoria_metricas` gravado em prod**, estratégia formalizada (schema próprio + adapter + OTel GenAI).

## ✅ Camadas em produção (validadas 2026-04-29)

| Camada | Componente | Estado |
|---|---|---|
| A | `laravel/ai ^0.6.3` + `config/ai.php` (gpt-4o-mini) | ✅ |
| B | `LaravelAiSdkDriver` + 4 Agents | ✅ Vizra rejeitada (ADR 0048) |
| C Hot | `SqlDriver` (conversas DB) | ✅ |
| C Cold | `MeilisearchDriver` hybrid (`semanticRatio: 0.7`) | ✅ MEM-HOT-1 — recall 0→190 |
| Working | `ContextoNegocio` no ChatCopilotoAgent (3 ângulos faturamento) | ✅ MEM-HOT-2 + MEM-FAT-1 — 270 tokens (bruto/líquido/caixa) |
| Embedder | OpenAI text-embedding-3-small | ✅ funcional |
| Métricas | tabela `copiloto_memoria_metricas` (14 colunas) + comando | ✅ MEM-MET-1+2 — baseline gravado |
| Telemetria | log channel `otel-gen-ai` (12 atributos `gen_ai.*`) | ✅ MEM-OTEL-1 |

## 🔢 Baseline 2026-04-29 (3 linhas em prod)

```
| biz_id      | p95_ms | tokens | inter | mem | bloat | contr |
|-------------|--------|--------|-------|-----|-------|-------|
| NULL (plat) |   1234 |    307 |     6 |   2 | 1.000 |  0.00 |
| 1           |   NULL |   NULL |     0 |   0 |  NULL |  NULL |
| 4 (Larissa) |   1234 |    307 |     6 |   2 | 1.000 |  0.00 |
```

RAGAS columns (recall_at_3, precision_at_3, mrr, faithfulness, answer_relevancy, context_precision) ficam NULL até golden set MEM-P2-1.

## 🛣️ Próximos passos (ordem recomendada)

1. **MEM-MET-3** — scheduler diário (15 min) — `Console/Kernel.php->daily()` chama `copiloto:metrics:apurar --all`
2. **A4** — Validar Larissa: "qual meu faturamento de março?" → R$ 38.215,07
3. **COP-002 = MEM-MET-5** — Golden set 50 perguntas Larissa-style (destrava 6 colunas RAGAS)
4. **MEM-MET-4 = COP-007** — Page `/copiloto/admin/qualidade` trend 30d
5. **MEM-S8-1** SemanticCacheMiddleware (-68.8% tokens)
6. **MEM-S8-2** ConversationSummarizer (>15 turnos)
7. **MEM-S8-3** ProfileDistiller (job diário)

## ADRs canônicos (51 total)

- [0035](memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack canônica IA
- [0036](memory/decisions/0036-replanejamento-meilisearch-first.md) — Meilisearch first + benchmark 95.2% LongMemEval
- [0037](memory/decisions/0037-roadmap-evolucao-tier-7-plus.md) — Roadmap memória
- [0046](memory/decisions/0046-chat-agent-gap-contexto-rico.md) — Gap ChatAgent (Caminho A escolhido)
- [0047](memory/decisions/0047-wagner-solo-sprint-memoria-agente.md) — Wagner solo + sprint memória
- [0048](memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) — Vizra ADK rejeitada oficialmente
- [0049](memory/decisions/0049-camadas-memoria-agente-fase-por-fase.md) — 6 camadas + gate Recall@3>0.80
- [0050](memory/decisions/0050-metricas-obrigatorias-memoria-table.md) — 8 métricas + tabela memory_metrics
- [0051](memory/decisions/0051-schema-proprio-adapter-otel-genai.md) — Schema próprio + adapter + OTel GenAI
- [0052](memory/decisions/0052-contextonegocio-expor-multiplos-angulos.md) — ContextoNegocio expor múltiplos ângulos (não 1 número) — origem MEM-FAT-1

## Sessions

- [2026-04-28-meilisearch-vaultwarden.md](memory/sessions/2026-04-28-meilisearch-vaultwarden.md) — IA real ativa
- [2026-04-29-sprint-memoria-completa.md](memory/sessions/2026-04-29-sprint-memoria-completa.md) — 8 entregas

## Triggers pra reavaliar estratégia

- `laravel/ai` lançar `tenant_id` / multi-tenancy nativa OU eval framework nativo
- OpenTelemetry GenAI sair de experimental (status "stable") com SDK PHP first-class
- Latência p99 do Meilisearch hybrid passar de 200ms sustentado
- Volume vetores >50M
- Cliente pagante #10 (sinal de tração)
