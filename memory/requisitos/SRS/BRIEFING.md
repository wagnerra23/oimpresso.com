---
module: SRS
status: parcial
status_nota: "legado uso interno raro (backoffice Wagner); deprecação PLANEJADA (DEPRECATION-PLAN 2026-05-17, Caminho 1 aprovado) mas NÃO executada — módulo 100% presente e servindo em prod. Sucessor prático: MCP server canon."
updated_at: "2026-07-18"
owner: W
related_adrs: [0053-mcp-server-governanca-como-produto, 0061-conhecimento-canonico-git-mcp-zero-automem, 0093-multi-tenant-isolation-tier-0]
lifecycle: ativo
---

# BRIEFING — Modules/SRS

> **Estado:** 🟡 legado uso interno raro (Wagner only) — deprecação PLANEJADA, não executada | **Atualizado:** 2026-07-18 | **Owner:** [W]

## O que é

**SRS = Software Requirements System.** Ferramenta interna do Wagner pra ingerir documentação (PDF/Markdown/HTML/URL), indexar em FULLTEXT MySQL, fazer search hybrid + chat assistido sobre o corpus e gerar relatórios de cobertura de requisitos. Convive com prefix `memcofre` por herança da fase anterior (cofre de docs na época em que a pasta ainda se chamava `MemCofre`, antes do rename pra `Modules/SRS`).

## Por que existe

Antes da Constituição v2 ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) + MCP server canon ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)), Wagner usava SRS pra trackear cobertura de requisitos do oimpresso. Hoje **substituído na prática** pelo MCP server (`mcp.oimpresso.com`) que sincroniza `memory/*` automaticamente.

## Capacidades hoje

- ✅ Cadastrar Doc Source (URL/file/folder)
- ✅ Ingest jobs (artisan commands `Sync*`)
- ✅ Indexação FULLTEXT MySQL nativa
- ✅ Cadastrar requirements + linkar evidências (M2M com confidence)
- ✅ Chat assistido sobre corpus (`ChatAssistant` service)
- ✅ Audit trail em `docs_validation_runs`
- ✅ Compat layer `memcofre.*` (bookmarks legados)
- ✅ Multi-tenant (`business_id` em todas tabelas)

## Diferencial vs concorrentes

- Vs Jira/Confluence/Notion: SRS roda dentro do próprio ERP — query SQL direta, sem auth externa
- Vs MCP server canon: SRS é mais antigo, menos governado; **MCP venceu na prática**

## Gaps reconhecidos

- 🟡 **Sobreposição com MCP server** — Wagner está migrando uso pra MCP via webhook GitHub; SRS pode virar fonte de leitura readonly do MCP futuramente
- 🟡 Sem reranker semântico (FULLTEXT é só lexical) — não vale investir hoje
- 🟡 Sem export estruturado pra LGPD audit (P3 — uso interno só)
- 🟢 ~~Blade legacy não migrado MWART~~ — **CORRIGIDO (stale desde 2026-05-16)**: as 6 telas já são Inertia/React (todos os controllers fazem `Inertia::render('MemCofre/*')`), não Blade.

## Mudanças desde 2026-05-16 (frescor briefing↔código)

- **#4139** — 6 telas ganharam charter **DRAFT** (`resources/js/Pages/MemCofre/{Chat,Dashboard,Inbox,Ingest,Memoria,Modulo}.charter.md`), antes sem contrato. `status: draft` — Wagner ainda aprova Non-Goals + Anti-hooks pra virar `live`.
- **#4148** — corrigido 404: frontend chamava `/docs/*`; as rotas canônicas vivem em `/memcofre/*` (chat/inbox/memoria voltaram a responder).
- **#3902** — D-14 partial reload aplicado: `Inbox.tsx` usa `router.get(..., { only: ['evidences','filtros'] })` (troca de aba/página/busca não recarrega `counts`).
- **#2666** — adoção em massa do DS (tokenização) alcançou o módulo junto dos outros 32.

## Estado de testes

10 arquivos Pest em `Modules/SRS/Tests/Feature/` (não 3 — a lista de 2026-05-16 estava stale):
- Núcleo: `MultiTenantIsolationTest` · `ScaffoldTest` · `SmokeRoutesTest` · `ObservabilityTest` · `RetentionPolicyTest`
- Saturação governance: `Wave23SaturationTest` · `Wave25CrossTenantSaturationTest` · `Wave26SaturationTest` · `Wave27PolishTest` · `Wave28SaturationTest`

## Decisões relacionadas

- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server canon (sucessor natural)
- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Conhecimento canônico via git+MCP (não mais auto-mem)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0

## Próximo passo sugerido

**Não investir em features novas.** O [DEPRECATION-PLAN.md](DEPRECATION-PLAN.md) (2026-05-17, Caminho 1 aprovado por Wagner) já mapeia 6 etapas (E0–E6) distribuindo features pra KB/Jana/Governance + MCP canon. **Estado real: nenhuma etapa de migração de código/dados executada** — o módulo segue 100% presente (8 controllers · 7 entities · 8 migrations · 10 testes) servindo em `/memcofre/*` em prod, e a ADR de deprecação continua como *proposal* não-promovida (o número `0168` já foi usado por outra decisão). Quando retomar:
1. Seguir o roadmap do plano (verificando o que já mudou desde 2026-05-17)
2. Manter compat layer `memcofre.*` + charters DRAFT intactos até a decisão

---

**Atualizado:** 2026-07-18 — refresh de frescor briefing↔código [CC]
