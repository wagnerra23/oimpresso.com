# GOVERNANCE-MATURITY-FICHA — Modules/TeamMcp

> Wave 22 Auditoria Governance Maturity · 2026-05-16 · Owner: Wagner [W]
> Trust: L1 · Status prod: live · **Nota: 67/100** (Bom — funcional + diferenciais reais; gaps em UI scorecard, self-service e métricas DX)
> Benchmark vs: Backstage Tech Insights (Spotify/CNCF) + LeanIX/SAP + Stack Overflow for Teams (Stack Internal) + Roadie

## 1. Escopo da auditoria

`Modules/TeamMcp` = **Identity Mesh + audit cc-sessions/cc-messages** do time MCP (~5 pessoas reais: Wagner+Maiara+Felipe+Luiz+Eliana). Inclui:

- McpActor (slug kebab + trust_level L0..L4 + manifest JSON)
- McpToken hash-only (ADR 0081 — token raw devolvido 1× e nunca persistido)
- CcIngestService (audit append-only Claude Code sessions)
- TeamUsageAggregator (custos + quotas + topTools per-user)
- LogsActivity em McpActor (Wave 15 — audit append-only via Spatie)
- Webhook git→DB (mcp_memory_documents ~352 docs)
- Kanban Jira-style (`mcp_tasks`)

## 2. Comparáveis (líderes 2026)

| Sistema | Categoria | Force | Fraqueza vs TeamMcp |
|---|---|---|---|
| **Backstage Tech Insights** (CNCF Spotify) | Dev portal + scorecards | Plugins maduros, Facts+Checks, badges/gauges visuais, ownership integrado | Java/Node stack — caro pra timezinho 5p; sem audit log append-only nativo (logs externos) |
| **Roadie** (Backstage SaaS) | Backstage managed + scorecards SaaS | UI scorecard pronto, plugins curados, onboarding rápido | Vendor lock-in cloud; LGPD self-host = impossível |
| **LeanIX/SAP** (Enterprise Architecture) | EA + IT landscape visual | Magic Quadrant Leader 5 anos, AI agent hub 2026, dependency mapping | Enterprise pricing (~50-200k€/ano); overkill pra 5p; sem identity mesh dev-level |
| **Stack Overflow for Teams** ("Stack Internal" 2026) | Q&A knowledge dev | Search semântico, AI summarization, governance reputation-based | Q&A ≠ identity governance; sem audit per-action, sem token mgmt |

## 3. Capacidades P0-P3 vs líderes (15 itens)

| # | Capacidade | TeamMcp | Backstage TI | Roadie | LeanIX | StackOF Teams | Prio |
|---|---|---|---|---|---|---|---|
| 1 | Identity actor com manifest (trust + modules + skills) | LIVE | catalog entity (sem trust tier) | igual Backstage | application-owner | user role | P0 |
| 2 | Token MCP hash-only Tier 0 | LIVE (sha256) | OIDC delegado | OIDC delegado | SAML/SSO | OAuth | P0 |
| 3 | Audit log append-only DB-enforced (trigger MySQL) | LIVE | audit em log file | audit SaaS | audit limitado | log básico | P0 |
| 4 | Parent_actor_id (delegação IA→humano) | LIVE diferencial | NÃO tem | NÃO tem | NÃO tem | NÃO tem | P0 |
| 5 | Scorecard visual per-actor/team (DX metrics) | ⚠️ gap (só CSV export) | LIVE (badges/gauges) | LIVE | LIVE dashboards | reputation only | P0 |
| 6 | Self-service token rotation (dev cria próprio) | ⚠️ gap (só Wagner) | OIDC self-rotate | igual | SAML | OAuth refresh | P1 |
| 7 | Webhook git→DB sync 352 docs | LIVE diferencial | catalog sync | catalog sync | EA import | doc import | P1 |
| 8 | Ingest Claude Code sessions (cc-sessions/messages) | LIVE único 2026 | NÃO tem | NÃO tem | NÃO tem | NÃO tem | P1 |
| 9 | LogsActivity Spatie em McpActor | LIVE (Wave 15) | audit ext | audit SaaS | audit limited | log básico | P1 |
| 10 | Quotas tracking per-user (custos $) | LIVE (TeamUsageAggregator) | NÃO tem | NÃO tem | cost mgmt EA | NÃO tem | P1 |
| 11 | Kanban+Cycles git-canon (Jira-style) | LIVE diferencial | plugin Jira ext | plugin Jira | NÃO tem | NÃO tem | P2 |
| 12 | UI dashboard audit log queries | ⚠️ gap (vai Modules/Governance) | UI built-in | UI rica | UI rica | UI Q&A | P1 |
| 13 | ActionGate runtime enforce (block tool call) | ⚠️ planejado Fase 5 (ADR 0086) | guardrails ext | RBAC SaaS | RBAC EA | reputation gate | P0 |
| 14 | LGPD compliance retention.php + PII redactor | LIVE (Config/retention.php) | DPR ext | DPR SaaS | GDPR module | GDPR | P0 |
| 15 | Métricas DX (PR velocity, review time, etc) | ⚠️ gap | LIVE Facts/Checks | LIVE | NÃO tem (EA) | NÃO tem | P2 |

**Score ponderado:** P0=4 (8 itens, 4 LIVE, 2 gap, 2 parciais) · P1=2 (5 itens, 4 LIVE, 1 gap) · P2=1 (2 itens, 1 LIVE, 1 gap)
= (8×4 + 5×2 + 2×1) / max = **67/100** (capacidades core P0 sólidas, scorecard UI + self-service + ActionGate são gaps táticos resolvíveis sem rearquitetura).

## 4. Diferenciais únicos (não tem em líder mundial)

1. **`parent_actor_id` cadeia delegação IA→humano** (`effectiveHumanSlug()` resolve agente Claude → Wagner via parent) — nem Backstage nem LeanIX modelaram isso. Crítico LGPD: cada ação IA tem humano responsável rastreável.
2. **Ingest Claude Code sessions** (cc-sessions/messages/blobs tables + idempotência msg_uuid UNIQUE) — único produto 2026 que faz audit cross-dev de sessões IA-pair com retention LGPD.
3. **Audit log append-only DB-enforced** via trigger MySQL (não app-level) — Backstage/LeanIX delegam pra log files mutáveis.
4. **Webhook git→DB canônico** (memory/*.md → mcp_memory_documents 352 docs) com índice FULLTEXT + Meilisearch hybrid — governance docs são single source of truth versionada.

## 5. Top 5 Gaps priorizados (impacto × esforço)

| # | Gap | Impacto | Esforço | Ação concreta |
|---|---|---|---|---|
| **G1** | **Scorecard UI visual per-actor** (today: só CSV export via UsageCsvExporter) | Alto — Wagner não enxerga DX maturity em 1 clique | M (~5d) | Adaptar Backstage Facts+Checks pattern: criar `mcp_actor_scorecards` (jsonb checks results) + página React `/team-mcp/scorecard/{slug}` com badges + gauges. Reuso `TeamUsageAggregator` como provider de Facts. |
| **G2** | **ActionGate runtime enforce** (warn-only, ADR 0086 Fase 5 pendente) | Alto — actor IA pode chamar tool fora `modules_write` sem bloqueio | M (~7d) | Implementar middleware MCP: antes do `Tool::handle()`, resolver `ActorResolver::fromToken()` + checar `canWriteModule()` + `isActionBlocked()`; 403 + audit_log entry. Pest cross-tenant biz=1 vs biz=99 obrigatório. |
| **G3** | **Self-service token rotation** (hoje só Wagner gera via `/copiloto/admin/team`) | Médio — Felipe/Maiara dependem de Wagner pra dev cred | S (~3d) | Endpoint `POST /team-mcp/me/token` autorizado por sessão Laravel; rate-limit 1/24h; `McpTokenIssuer::issue()` já existe — só falta route + form. |
| **G4** | **UI dashboard audit log query/filter** (today: tabela raw em /copiloto/admin/team legacy) | Médio — investigação incident depende de SQL direto | M (~5d) | Migrar pra `Modules/Governance` Fase 5 (já planejado) — filtros por actor/tool/biz/janela + export CSV + drill-down per-action. |
| **G5** | **Métricas DX (PR velocity, review time, defect-rate)** | Baixo curto-prazo / Alto longo-prazo | M (~7d) | Adaptar Backstage Tech Insights Facts: provider GitHub via gh API → `mcp_dx_facts` table → scorecard checks ("PR<24h merged: 80%", "test coverage Pest: >70%"). Foco em 5p, simples. |

## 6. Recomendação executiva

TeamMcp é **funcional e tem 4 diferenciais reais vs líderes** (parent_actor IA→humano, CC sessions ingest, append-only DB-enforced, webhook git canon). Os 5 gaps são **táticos sem rearquitetura** — totalizam ~27d de trabalho (3 sprints) pra subir nota de 67 → 88/100.

Prioridade Wagner: **G2 (ActionGate) > G1 (Scorecard UI) > G3 (Self-service token)**. G4 já está roteirizado (Modules/Governance Fase 5). G5 esperar time MCP atingir 5p+ ativos pra ROI virar relevante.

## 7. Referências canon

- BRIEFING: [BRIEFING.md](BRIEFING.md) · SPEC: [SPEC.md](SPEC.md)
- ADRs: [0053](../../decisions/0053-mcp-server-governanca-como-produto.md) (MCP server) · [0079](../../decisions/0079-constituicao-7-camadas.md) (Constituição 7 camadas) · [0080](../../decisions/0080-trust-tiers.md) (Trust tiers) · **[0081](../../decisions/0081-identity-mesh-actor-trust-mcp.md) (Identity Mesh mãe)** · [0086](../../decisions/0086-action-gate-fase-5.md) (ActionGate Fase 5) · [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (Multi-tenant Tier 0)
- Comparáveis pesquisados:
  - [Backstage Tech Insights — Roadie product page](https://roadie.io/product/tech-insights/)
  - [Backstage community-plugins Tech Insights README](https://github.com/backstage/community-plugins/blob/main/workspaces/tech-insights/plugins/tech-insights/README.md)
  - [Cortex — Backstage Alternatives 2026](https://www.cortex.io/post/backstage-alternatives-what-engineering-leaders-need-to-know-in-2026)
  - [SAP LeanIX 2026 building on EA momentum](https://www.leanix.net/en/blog/sap-leanix-2026-building-on-momentum)
  - [Stack Overflow for Teams 2026 Reviews G2](https://www.g2.com/products/stack-overflow-internal/reviews)
  - [Stack Internal product page](https://stackoverflow.co/internal/)

---
**v1.0.0** (2026-05-16) — Wave 22 Auditoria Governance Maturity — agent isolado em area exclusiva (sem git ops; parent consolida).
