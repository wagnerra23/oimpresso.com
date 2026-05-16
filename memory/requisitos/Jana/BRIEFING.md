# BRIEFING — Modules/Jana

> **Tipo:** BRIEFING canônico — 1 página executiva atualizada por PR mergeado relevante
> **Refs:** [proibicoes.md §Sempre fazer](../../proibicoes.md) — regra Tier 0 "BRIEFING.md atualizado em todo PR mergeado"
> **Skill auto-trigger:** `brief-update` (Tier B)
> **Mantenedor:** Claude (auto) + Wagner (review)

---

## 1. O que é

**URL principal:** `https://oimpresso.com/copiloto` (chat) · `/jana/cockpit` (cockpit IA) · `/copiloto/admin/governanca` (governança MCP)
**Backend:** `Modules/Jana/` (13 controllers, 53 services, 40 entities/migrations, 46 Pest tests)
**Frontend:** `resources/js/Pages/Jana/` (9 .tsx, 3 charters)

**Jana** é o **analista IA do oimpresso** com memória persistente por business — entrega brief diário, monitora KPIs/anomalias, sugere ações HITL, responde via chat estruturado (single-thread por business). Núcleo do produto IA — base pra ADS (Modules/ADS — decision flow Dual-Brain) e pra Copiloto do operador.

## 2. Estado consolidado

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core: chat, metas, memória, brief, dashboard) | 85% | 2026-05-16 |
| Capterra score vs top-mercado (ChatGPT Teams, Glean, Notion AI) | ~70/100 | 2026-05-13 ([COMPARATIVO-MCP-ESTADO-DA-ARTE](COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md)) |
| Diferencial competitivo (ERP-nativo + memória multi-tenant LGPD + governança formal) | 90% | 2026-05-16 |
| Cobertura SPEC formal (done/spec'ado) | 78% | 2026-05-16 (SPEC.md US-COPI-001..220+) |
| Documentação canon (SPEC + ARCHITECTURE + RUNBOOKs + ADRs) | 92% | 2026-05-16 |
| Deploy/ops (prod biz=1 + biz=4 ROTA LIVRE canary) | 80% | 2026-05-16 |
| **Wave M boost — nota módulo** | **64 → meta 78** | 2026-05-16 |

## 3. Capacidades hoje

- **Chat estruturado**: single-thread por business, propostas zod-validadas, escolha vira `Meta` + `MetaPeriodo` + `MetaFonte` + `ApurarMetaJob`
- **Memória persistente**: `MemoriaContrato` + drivers (`MeilisearchDriver` hybrid default, `McpMemoriaDriver`, `NullMemoriaDriver` dev) — 3 ângulos faturamento ([ADR 0052](../../decisions/0052-memoria-jana-3-angulos-faturamento.md)) · HyDE expander + LLM reranker + RRF + BGE reranker
- **Stack IA canônica**: `laravel/ai` ^0.6.3 oficial fev/2026 ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) · `LaravelAiSdkDriver` wrapper · 4 Agents próprios (`BriefDiarioAgent`, `BrainB`, `Planner`, `Reviewer`) — **Vizra ADK rejeitada** ([ADR 0048](../../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md))
- **MCP server governança como produto** ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)): `mcp.oimpresso.com` em CT 100, 352+ docs sincronizados de `memory/*` via webhook GitHub, FULLTEXT + Meilisearch hybrid embedder, tokens em `/copiloto/admin/team`
- **BriefDiarioAgent**: narrativa diária ~250-400 palavras, provider OpenAI `gpt-4o-mini` (cost-optimized), trigger automático ou manual via `Modules/Jana/Ai/Agents/BriefDiarioAgent.php`
- **Telemetria/Qualidade**: `RetrievalSpan` OTel GenAI · `ProfileDistiller` · `LangfuseClient` · `RagasJudgeService` (golden set) · `SemanticCacheService` · `NegativeCacheService` · `GabaritoEvaluator` · `MetricasApurador`
- **Health checks**: `php artisan jana:health-check` (daily 06:00 BRT) — 5 checks SQL (multi-tenant isolation, brief uptime 24h, custo Brain B 24h, PII leak, profile distiller drift)

## 4. Diferenciais únicos (não-replicáveis BSPs)

1. **Memória multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — fato vazado entre business = bug Tier 0; ChatGPT/Glean/Notion AI não têm isolamento formal
2. **3 ângulos faturamento canônico** ([ADR 0052](../../decisions/0052-memoria-jana-3-angulos-faturamento.md)) — contrato fixo Jana sabe responder qualquer pergunta financeira sem alucinar
3. **MCP server governança como produto** ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)) — único ERP BR com MCP exposto (CT 100); time consome via Claude Code
4. **Hybrid retrieval estado-da-arte** — Meilisearch + HyDE + LLM reranker + RRF + BGE — 14 gotchas catalogados ([RETRIEVAL-GOTCHAS.md](RETRIEVAL-GOTCHAS.md))
5. **Governança formal** — Constituição v2 ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)), 8 princípios duros, append-only ADRs, CI `governance-gate.yml`
6. **ERP-nativo** — Jana lê dados reais multi-tenant (transactions, contacts, products) via `ContextSnapshotService` — concorrentes consultam doc estático

## 5. Gaps remanescentes (próxima onda)

| # | PR alvo | Esforço IA-pair | Score impact |
|---|---|---|---|
| 1 | Charters faltantes (Dashboard, Memoria, Governanca) | 30min | +3pp (D3.b) |
| 2 | BRIEFING.md (este arquivo) | 30min | +5pp (D1) |
| 3 | Service facade `JanaChatFacadeService` thin (ratio Services/Controllers) | 15min | +1pp (D4.a) |
| 4 | Cockpit.tsx F1.5 ≥80 (charter spec-ahead-of-impl) | 4h | +4pp (Capterra) |
| 5 | RAGAS golden set 200 exemplos (eval gate CI) | 6h | +5pp (qualidade) |

## 6. Bloqueadores manuais Wagner

- Approval cutover Cockpit.tsx F1.5 (substituição em-place atual MVP)
- Curate de heurísticas Brain B (custo Sonnet/Opus)
- Confirmação ROTA LIVRE pré-canary qualquer mudança chat

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| ChatGPT Teams (~$30/usr/mês) | ERP-nativo, multi-tenant LGPD, MCP governança | UI polish, plugins ecosystem |
| Glean ($30-100/usr/mês) | 10× preço, ERP nativo, BR-tax | Search universal SaaS (Slack/GDocs/Jira) |
| Notion AI ($10/usr/mês) | Memória persistente real (não doc-bound), ERP | Notion-base ecosistema |
| Bling/Tiny/Omie IA | 5 anos à frente em memória + retrieval | — |

## 8. Risks ativos

- 🟡 Custo Brain B (Sonnet/Opus) sem rate limit por business — `custo_brain_b_24h` check daily
- 🟡 Cockpit.tsx atual = anti-pattern WhatsApp-style ([Cockpit.charter.md](../../../resources/js/Pages/Jana/Cockpit.charter.md)) — F1.5 ≥80 + screenshot Wagner pendente
- 🔴 PII leak em assistant responses — check `pii_leak_in_assistant_responses` daily + `PiiRedactor` enforce
- 🟡 Profile distiller drift — check daily detecta mudança não-aprovada do perfil business

## 9. Métricas-chave (last 7d)

- Volume chat: ~50 msgs/dia biz=1 (Wagner uso interno)
- Custo OpenAI gpt-4o-mini (BriefDiarioAgent): ~R$ [redacted Tier 0]/dia/business
- Brief uptime 24h: 100% (check daily)
- Multi-tenant isolation: 0 vazamentos (check daily)
- Cobertura Pest: 46 testes Modules/Jana/Tests/

## 10. Cliente piloto / canary

- **Atual:** ROTA LIVRE (biz=4) — uso passivo (read-only dashboard) desde 2026-04
- **Próximo canary:** biz=1 (Wagner) — uso ativo chat + memória + brief diário
- **Próxima onda:** Felipe/Maiara/Eliana via MCP server tools (não UI direta)

## 11. ADRs centrais do módulo

- [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack IA canônica (laravel/ai oficial)
- [ADR 0048](../../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) — Framework agents (Vizra rejeitada)
- [ADR 0052](../../decisions/0052-memoria-jana-3-angulos-faturamento.md) — 3 ângulos faturamento canônico
- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server como produto
- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2

## 12. Sessões e handoffs relevantes (últimos 30d)

- 2026-05-13 — [ONDA-5-DOSSIER](ONDA-5-DOSSIER-2026-05-13.md) — boost retrieval estado-da-arte
- 2026-05-13 — [GAP-ANALYSIS-91-100](GAP-ANALYSIS-91-100-2026-05-13.md) — gap pra alcançar 100/100
- 2026-05-13 — [COMPARATIVO-MCP-ESTADO-DA-ARTE](COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) — vs Glean/ChatGPT/Notion
- 2026-05-13 — [AUDITORIA-KNOWLEDGE-ARCHITECTURE](AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) — migração auto-mem→canon

---

## 13. Último update

**Atualizado:** 2026-05-16 (Wave M boost — Modules/Jana 64→78)
**Próximo update esperado:** quando próximo PR relevante mergear (auto-trigger `brief-update` skill)
**Mantenedor:** Claude (auto) + Wagner (review)
