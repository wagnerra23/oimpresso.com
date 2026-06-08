# BRIEFING — `Modules/Jana`

> **Tipo:** BRIEFING canônico do módulo — 1 página executiva atualizada por PR mergeado relevante
> **Refs:** [proibicoes.md §Sempre fazer](../../memory/proibicoes.md) — regra Tier 0 "BRIEFING.md atualizado em todo PR mergeado"
> **Skill auto-trigger:** `brief-update` (Tier B) — atualiza este BRIEFING ao terminar PR que toque `Modules/Jana/` + `resources/js/Pages/Jana/`

---

## 1. O que é

**URL principal:** `https://oimpresso.com/copiloto` (UI legacy preservada)
**Backend:** `Modules/Jana/`
**Frontend:** `resources/js/Pages/Jana/` + `resources/js/Pages/Copiloto/` (legacy admin)

Chat IA conversacional do business (ex-Copiloto, renomeado Fase 3.7 PR-2). Larissa pergunta sobre faturamento/metas/produtos vestuário e Jana responde usando dados reais (CYCLE-01 goal). Núcleo: chat + memória persistente (`MemoriaContrato`) + metas governadas + brief diário + Cockpit Saúde Brain A.

## 2. Estado consolidado

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (chat + metas + memória live prod) | 95% | 2026-05-16 (Wave 18) |
| Governance score v3 (D1-D9) | 96/100 (target ≥95) | 2026-05-16 (Wave 25 SATURATION) |
| Capterra (chat IA vs assistentes BI) | N/A em curso | — |
| Diferencial competitivo (chat ↔ ERP nativo) | alto | 2026-05-12 |
| Cobertura SPEC formal (US-COPI-*) | ~80% | 2026-05-15 |
| Documentação canon (SPEC + CHANGELOG + BRIEFING) | 100% | 2026-05-16 (Wave 18) |
| Deploy/ops (prod biz=1 Wagner) | 100% live | 2026-05-12 |

## 3. Capacidades hoje

- **Chat IA**: laravel/ai SDK + LaravelAiSdkDriver + 4 Agents (Modules/Jana/Ai/Agents/), Stack ADR 0035
- **Memória persistente**: MemoriaContrato + MeilisearchDriver default + ProfileDistiller anti-drift
- **Metas governadas**: 4 drivers (sql/event/manual/api), apuração + alertas + dashboard
- **Brief diário**: cron 06:00 BRT alimenta `mcp_briefs`, tool MCP `brief-fetch` Tier A (cache 5min)
- **Cockpit Saúde Brain A**: `NarrarSaudeEcosistemaJob` cron horário → `jana_health_narratives` (gpt-4o-mini ~R$ [redacted Tier 0]/dia)
- **MCP server canon**: `mcp.oimpresso.com` (CT 100), 352+ docs sincronizados via webhook GitHub
- **RAG enhanced**: HydeQueryExpander + LlmReranker (BGE) + NegativeCacheService + freshness loop

## 4. Diferenciais únicos (não-replicáveis BSPs)

1. **ERP nativo** — Jana lê tabelas reais (`transactions`, `contacts`, `nfe_emissoes`) sem ETL/scraping ✗ Bling/Tiny/Omie chat
2. **Memória persistente per-business** — fatos persistem em `jana_memoria_facts` cross-conversa (não só context window)
3. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — todo dado scoped `business_id`, defesa em profundidade traits
4. **Stack IA canônica** (ADR 0035) — laravel/ai oficial + agents próprios; Vizra REJEITADA
5. **Governance v3 score 95/100** — único módulo com rubrica formal D1-D9 saturada (Wave 18 — 2026-05-16)
6. **Audit triplo** — `JanaAuditService` emite Spatie ActivityLog + OTel span + log structured em 1 call
7. **OTel instrumentação Tier 0** — 40+ Services instrumentados com `OtelHelper::spanBiz` (business_id auto-resolve)
8. **LGPD config canônica** — `Modules/Jana/Config/retention.php` declara retenção por entidade (Art. 16)
9. **Brief diário Tier A always-on** — onboarding Claude/time MCP 27k tokens economizados/sessão
10. **MCP server canônico produto** — não cache derivado; tools `cycles-active`, `tasks-list`, `decisions-search` etc

## 5. Gaps remanescentes (próxima onda)

| # | PR alvo | Esforço IA-pair | Score impact |
|---|---|---|---|
| 1 | LGPD purge job real (`jana:retention-purge` artisan) | 4h | +1pp (D7) |
| 2 | OTel collector CT 100 ligado em prod (today disabled) | 2h infra | +0.5pp (D9) |
| 3 | Capterra fichas comparativas (chat IA assistentes BI BR) | 6h research | — score-agnóstico |
| 4 | RAGAS judge automatizado em CI (canary daily) | 3h | +1pp (D6.b) |
| 5 | Jana Pro upsell paywall + billing integration | 8h | — comercial |

## 6. Bloqueadores manuais Wagner

- Aprovação enable `JANA_RETENTION_ENABLED=true` em prod após validação canary
- Aprovação OTel collector CT 100 (custos infra + storage Jaeger)
- Confirmação Larissa (ROTA LIVRE biz=4) — feature flags Jana Pro paywall
- Curate de heurísticas summarizer (`copiloto.summarizer.threshold_turnos`)

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| Bling/Tiny/Omie chatbots | ERP nativo, memória persistente, multi-tenant Tier 0 | Marca grande, integração marketplaces |
| ChatGPT Custom GPT BR | Memória cross-conversa, governance formal, dados reais ERP | Modelo state-of-art (GPT-4.5, Claude 3.5) |
| Conta Azul Numia | Custo (Jana ~R$ [redacted Tier 0]/dia vs R$ [redacted Tier 0]/mês), modular, anti-vendor lock | Marca, marketplace |
| Microsoft Copilot for Finance | Custo, customização total, hosting BR | Integração Excel, marca enterprise |

## 8. Risks ativos

- 🟡 **Custo IA pode escalar** — Brain A NarrarSaude horário R$ [redacted Tier 0]/dia × N businesses; mitigação cap em CustosService
- 🟡 **PII vazamento em logs** — PiiRedactor canônico mas cobertura precisa audit periódico (D7.a)
- 🟢 **Drift memória persistente** — ProfileDistiller mensal regenera; jana:health-check daily 06h
- 🔴 **Dependência Meilisearch CT 100** — single-point fail; mitigação NullMemoriaDriver fallback dev

## 9. Métricas-chave (last 7d)

- Volume chat: ~50 msgs/dia biz=1 (Wagner WR2)
- Custo IA: ~R$ [redacted Tier 0]/dia (chat + brief + health narrate)
- Recall@3 memória: 0.84 (após LlmReranker live — Wave 1.6.0)
- jana:health-check: 5/5 passing daily 06:00 BRT
- Cache semantic hit rate: ~25% (economia ~R$ [redacted Tier 0]/dia)

## 10. Cliente piloto / canary

- **Atual:** Wagner WR2 biz=1 — desde 2026-04-30 (chat + metas + brief)
- **Próximo canary:** ROTA LIVRE biz=4 (Larissa) — quando Jana Pro paywall pronto + Wagner aprovar (ADR 0105 — sinal qualificado)

## 11. ADRs centrais do módulo

- [ADR 0035](../../memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack IA canônica laravel/ai + Agents próprios
- [ADR 0037](../../memory/decisions/0037-memoria-contrato-mcp.md) — MemoriaContrato + drivers (referência implícita Sprints 8-10)
- [ADR 0048](../../memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) — Vizra ADK REJEITADA
- [ADR 0053](../../memory/decisions/0053-mcp-server-governanca-como-produto.md) — MCP server canon produto
- [ADR 0061](../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0091](../../memory/decisions/0091-daily-brief.md) — Daily Brief Tier A always-on
- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (mãe)
- [ADR 0132](../../memory/decisions/0132-langfuse-self-host-ct100.md) — Langfuse self-host CT 100
- [ADR 0140](../../memory/decisions/0140-jana-pro-produto-comercial-saas.md) — Jana Pro produto comercial SaaS

## 12. Sessões e handoffs relevantes (últimos 30d)

- Wave 25 SATURATION 2026-05-16 — Jana 73 → 96 (D1.c markers REPO-WIDE 8 entities + D9 OpenAiDirectDriver OTel + D2 hallucination golden 22→30 + D3 BRIEFING/CHANGELOG)
- Wave 23 RAGAS gate + drift sentinel + hallucination 22 golden 2026-05-16
- Wave 18 SATURATION 2026-05-16 — Jana 66 → 95 (D1 comprehensive + D2 FSM N/A + D3 BRIEFING + D4 OTel audit + D7 retention + D8 FormRequests + D9 OTel batch)
- Wave 17 governance v3 2026-05-15 — D7.b LogsActivity 6 Mcp Models + D6.a Inertia::defer 4 Controllers + D8.c 3 FormRequests
- Wave 15-16 RESCUE D1 — Mcp Models trait coverage + chain 2-level via parent
- Wave 10 LGPD PiiRedactor — sanitização exception logs (D7)
- v1.7.0 Cockpit Saúde Brain A 2026-05-12 — `NarrarSaudeEcosistemaJob` live prod

---

## 13. Último update

**Atualizado:** 2026-05-16 BRT pelo PR Wave 25 — SATURATION MÁXIMA Jana (73 → ≥95) — D1.c markers + D9 OTel + D2 hallucination 30 golden
**Próximo update esperado:** quando próximo PR relevante mergear (auto-trigger `brief-update` skill)
**Mantenedor:** Claude (auto) + Wagner (review)
