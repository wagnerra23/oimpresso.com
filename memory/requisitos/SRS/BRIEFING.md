# BRIEFING — Modules/SRS

> **Estado:** 🟡 legado uso interno raro (Wagner only) | **Atualizado:** 2026-05-16 | **Owner:** [W]

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
- 🟡 Blade legacy não migrado MWART (uso raro)
- 🟡 Sem export estruturado pra LGPD audit (P3 — uso interno só)

## Estado de testes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php`
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`

## Decisões relacionadas

- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server canon (sucessor natural)
- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Conhecimento canônico via git+MCP (não mais auto-mem)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0

## Próximo passo sugerido

**Não investir em features novas.** Quando precisar mexer:
1. Avaliar se a feature não cabe melhor no MCP server canon
2. Se sim, propor ADR de deprecação do SRS
3. Se não, manter compat layer `memcofre` intacta
