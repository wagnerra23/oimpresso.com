---
name: Estado do Copiloto IA em produção (2026-04-28 fim do dia)
description: Snapshot consolidado do que está rodando, o que falta, gaps de produto descobertos
type: project
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---

## ✅ Em produção (validado em 2026-04-28)

| Camada | Componente | Estado |
|---|---|---|
| A | `laravel/ai ^0.6.3` + `config/ai.php` | ✅ ativo. OpenAI gpt-4o-mini default |
| B | `LaravelAiSdkDriver` (Modules/Copiloto/Services/Ai/) | ✅ respondendo conversas reais (Wagner testou na conta Larissa biz=4) |
| C Hot | SqlDriver — conversas em DB | ✅ |
| C Cold | MeilisearchDriver + index `copiloto_memoria_facts` | ✅ hybrid embedder ativo (29-abr commit `c631042c`) |
| Embedder | OpenAI text-embedding-3-small no Meilisearch | ✅ funcional (validado curl direto: semanticHitCount=2) |

## 🟡 Gaps de produto descobertos na 1ª conversa real

### Gap 1: ~~ChatCopilotoAgent "burrinho"~~ — RESOLVIDO 2026-04-29 (Caminho A)
- ✅ commit `2be9930c` (MEM-HOT-2, ADR 0047): `ChatCopilotoAgent` recebe `?ContextoNegocio` opcional
- ✅ `LaravelAiSdkDriver::responderChat` chama `ContextSnapshotService::paraBusiness($conv->business_id)` + degradação silenciosa
- ✅ Smoke prod biz=4 ROTA LIVRE: prompt de 657 chars / **164 tokens** com:
  - EMPRESA: ROTA LIVRE (id 4)
  - CLIENTES ATIVOS: 5993
  - 4 meses faturamento real (jan→abr 2026: R$4k → R$26k → R$38k → R$31k)
- ✅ ContextSnapshotService::metasAtivas TODO virou query real (top 5 meta+período+apuração)
- ✅ 6 testes Pest novos cobrem BC-compat + formato + token economy + obs + biz null
- BC-compat: `ChatCopilotoAgent($conv)` sem ctx mantém comportamento exato anterior
- Pendente: validação real Larissa via /copiloto/chat (A4 do Cycle 01)

### Gap 2: ~~MeilisearchDriver::buscar usa Scout default~~ — RESOLVIDO 2026-04-29
- ✅ commit `c631042c` (MEM-HOT-1, ADR 0047) — Scout callback passa `hybrid:{embedder,semanticRatio}` + filter Meilisearch literal
- ✅ Smoke prod: query "qual a meta de faturamento" → 2 hits, `memoria_recall_chars: 190` (era 0)
- ✅ 2 testes Pest novos (`MeilisearchDriverHybridTest`) capturam params via engine fake
- Config defaults atualizados: `embedder='openai'`, `semantic_ratio=0.7` (sweet spot Meilisearch hybrid PT-BR)
- Follow-up: smoke teve 173s (cold start CLI bootstrap); chat real prod mostrou recall em latência razoável

## 📊 Métricas baseline (a coletar)

Ainda não rodamos LongMemEval / RAGAS / DeepEval na nova stack — Sprint 7 (gate obrigatório do ADR 0037).

## 🛣️ Próximos passos por prioridade (atualizado 2026-04-29)

1. ✅ ~~Hotfix MeilisearchDriver hybrid~~ — feito 29-abr (`c631042c`)
2. **A2 ativa: MEM-HOT-2 ContextoNegocio → ChatCopilotoAgent** (ADR 0046 Caminho A) — fix Gap 1
3. **MEM-S8-1 SemanticCacheMiddleware** (-68.8% tokens) — ADR 0047 Sprint 8
4. **MEM-S8-2 ConversationSummarizer** (>15 turnos)
5. **MEM-S8-3 ProfileDistiller** (perfil negócio compacto pro system prompt)
6. **Validar Larissa A2** — depois de MEM-HOT-2 deployado
7. **Sprint 7 RAGAS golden set** — 50 perguntas Larissa-style; baseline mensurável

## ADRs relacionados (canônicos)

- [0035](memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack canônica IA
- [0036](memory/decisions/0036-replanejamento-meilisearch-first.md) — Meilisearch first
- [0037](memory/decisions/0037-roadmap-evolucao-tier-7-plus.md) — Roadmap memória
- [0042](memory/decisions/0042-reverb-substitui-pusher-cloud.md) — Reverb
- [0045](memory/decisions/0045-hostinger-dns-api-endpoint-canonico.md) — DNS API
- [0046](memory/decisions/0046-chat-agent-gap-contexto-rico.md) — Gap ChatAgent
- [0047](memory/decisions/0047-wagner-solo-sprint-memoria-agente.md) — Wagner solo + sprint memória
- [0048](memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) — Vizra ADK rejeitada (quebrou L13); `laravel/ai` consolidado
- [0049](memory/decisions/0049-camadas-memoria-agente-fase-por-fase.md) — 6 camadas mem fase por fase + gate Recall@3>0.80
- [0050](memory/decisions/0050-metricas-obrigatorias-memoria-table.md) — 8 métricas + tabela `copiloto_memoria_metricas` + 5 tasks MEM-MET-1..5

## Sessions

- Início Reverb: `memory/sessions/2026-04-28-reverb-docker-host.md`
- Meilisearch + ativação IA: `memory/sessions/2026-04-28-meilisearch-vaultwarden.md`
