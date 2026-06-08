---
slug: 2026-05-17-arte-bucket-cross-cutting-vs-idps
type: session-log
agent: estado-da-arte
date: 2026-05-17
module: Governance
tags: [estado-da-arte, idp, backstage, port-io, cortex, opslevel, compass, cross-cutting-infra, bucket-comparativo, w27]
related_adrs: [0094, 0093, 0143, 0155, 0156, 0157, 0158, 0159, 0160, 0091, 0122, 0127]
pii: false
related_session: 2026-05-17-arte-oimpresso-vs-melhores-2026.md
---

# Estado da arte — bucket `cross_cutting_infra` (7 módulos oimpresso) vs top 5 IDPs 2026

**Pergunta Wagner (W27):** os 7 módulos do bucket `cross_cutting_infra` (Governance 89, ProjectMgmt 84, Auditoria 81, Brief 83, Admin 77, TeamMcp 79, Superadmin 71, Connector 80) batem com IDPs world-class 2026? Onde o oimpresso supera, onde está atrás, quais 5 features faltam, qual a mais impactante implementável em 1 wave.

> **Disclaimer metodológico:** §1 foi pesquisado SEM tocar memory/ pra não contaminar. §2-3 cruzou com BRIEFINGs reais dos 7 módulos + ADRs 0160 (v4 scoped scorecards) + Services/Console/Pages do `Modules/Governance`. Nenhum número de mercado é projeção minha — todos saem de relatório público nominado.

---

## §1 Top 5 IDPs world-class 2026 (pesquisa limpa)

| # | Player | Como resolve governança cross-cutting | Por que é referência |
|---|---|---|---|
| 1 | **Backstage (Spotify/CNCF)** | Framework open-source com plugin Soundcheck (scorecards Bronze/Silver/Gold). Governança via catalog content + plugin approval + RBAC. Spotify oferece Backstage Enterprise (hosted) com SSO, compliance tooling, observability dashboards e suporte comercial. SQL-backed scorecards atualizam em real-time. | **3.400+ orgs CNCF, 89% market share IDP, 2M+ devs**. Spotify interno: 99% adoção. Padrão CNCF de facto. |
| 2 | **Port.io** ($100M Series C dez/25, $800M valuation) | Blueprint-based data model (no-code) define qualquer entidade + relações (knowledge graph). 2026: Agentic Engineering Platform — "context lake" como fonte de verdade pra agentes IA, guardrails per-blueprint, human-in-the-loop com approval workflows + confidence thresholds. Self-heal incidents + remediate vulnerabilities autônomos. | **Posicionamento agentic-first**: primeiro IDP a tratar IA agents como cidadão de 1ª classe com governance própria (não chat sobre catalog, mas agente que age sobre catalog). |
| 3 | **OpsLevel** | **Scoped Scorecards** — regras diferentes por maturidade/criticidade/equipe (contra "one-size-fits-all" da Cortex). Initiatives com project brief section pra contexto. Checks gate CI/CD (bloqueiam deploy se maturity gate não bate). Time-to-deploy 30-45 dias (vs 6+ meses Cortex). Preço ~½ Cortex. | Posicionamento **anti-Cortex** explícito. Implementação rápida + scoped scorecards são o que oimpresso ADR 0160 v4 acabou de adotar. |
| 4 | **Cortex** | **Scorecards opinionated** (production readiness, security, DORA, AI readiness/governance/maturity). **Initiatives** = scorecard com deadline + reminders + backlog auto-gerados. **MCP server** (jul/25): 25.109 queries em ~5 meses via Cortex MCP em IDEs do time. Wrapped 2025: +64% deploy freq, +20% PRs/dev, 36.581 workflows automatizados. 60+ pre-built integrations. | **MCP server adotado em produção real**: prova de mercado pra padrão `mcp.oimpresso.com`. AI Governance scorecard é vertical próprio (Cortex tem 3: AI Governance, AI Readiness, AI Maturity). |
| 5 | **Atlassian Compass** | Component catalog + scorecards weighted (critério × peso → % completion). 2025: package dependency tracking como critério ("componente usa versões aprovadas/atualizadas?"). Auto-sync GitHub/GitLab/Bitbucket via SCM apps. **Status:** Atlassian está migrando Compass scorecards+catalog pro DX app (sinal de pivot, não morte). | Único IDP integrado nativo ao Jira/Confluence — vantagem distribution massiva. Package dependency como scorecard criterion é diferencial real. |

**Padrões transversais que todos os 5 cultivam em 2026:**

1. **Score-as-code** (scorecards versionados em git/YAML, não UI clica-clica) — Backstage, Port, OpsLevel, Cortex declarativo
2. **Scoped/segmented scorecards** (bucket per service type/maturity) — OpsLevel pioneirou, Port adota, Cortex resistiu até cair atrás
3. **Initiatives com deadline + auto-backlog** — Cortex original, Port/OpsLevel copiaram
4. **MCP server expondo catalog pra IDEs do time** — Cortex MCP jul/25 com tração real (25k queries)
5. **Drift detection + alertas** — comum a todos via cron + comparação baseline ↔ current

---

## §2 Comparação dimensional — bucket `cross_cutting_infra` vs IDPs 2026

| Dimensão | Estado-da-arte 2026 | Estado oimpresso hoje | Distância |
|---|---|---|---|
| **Score-as-code (scorecards versionados)** | Todos os 5 — YAML/git, code review obrigatório | ✅ ADR 0160 v4 — 4 YAMLs canon (`memory/scorecards/*.yaml`), `ScopedScorecardEvaluator` (Wave 24), bucket meta declarativo, paired-indicator anti-gaming cap 50% | **CURTA** — oimpresso bate o padrão Jellyfish 2025; **supera Cortex** (que não tem cap paired) |
| **Scoped scorecards por bucket** | OpsLevel pioneirou; Port + Backstage tracks | ✅ 4 buckets (`vertical_client_facing` ≥85, `cross_cutting_infra` ≥90, `ai_central` ≥85, `functional_horizontal` ≥80) + meta por bucket | **CURTA** — oimpresso bate exatamente o pattern OpsLevel |
| **Drift detection + alertas** | Cortex/Port: cron + comparação baseline | ✅ `DetectDriftCommand` + `DriftAlertService` + UI `/governance/drift-alerts`; daemon `procedure_drift` em `jana:health-check`; `fsm:scan-drift transactions` cron 03:00 BRT (FSM ADR 0143) | **CURTA** — múltiplas superfícies de drift (filesystem + DDL + FSM bypass), mais granular que Cortex single-track |
| **ADR append-only enforcement em CI** | Não documentado em nenhum dos 5 — Backstage tem RFC plugin mas sem enforcement runtime | ✅ `governance-gate.yml` Job 1 bloqueia merge de PR com status M/R* em `memory/decisions/NNNN-*.md` (ADR 0094 §10.4 Cascade Review) | **CURTA — oimpresso SUPERA**. Nenhum IDP global tem esse rigor de imutabilidade decisória em CI. Diferencial real. |
| **Multi-tenant Tier 0 (paired indicator)** | Port + Backstage têm RBAC; nenhum trata multi-tenant `business_id` global scope como **Tier 0 IRREVOGÁVEL** com Pest cross-tenant biz=1 vs biz=99 obrigatório | ✅ ADR 0093 multi-tenant Tier 0 + `MultiTenantIsolationTest` em ~12 módulos + paired indicator `multi_tenant_isolation` cap 50% se quebrado (ADR 0160 v4) | **CURTA — oimpresso SUPERA** estruturalmente (B2B SaaS BR exige; IDPs B2B são geralmente single-tenant per-customer) |
| **ActionGate runtime + PII redaction em audit** | Backstage RBAC + audit log; Cortex `mcp_audit_log` interno | ✅ `Governance/Http/Middleware/ActionGate.php` enforcement por rota + `PiiRedactor` em log violations (ADR 0094 Art. 8); whitelist UNREVERTIBLE 5 categorias LGPD-sensitive (ADR 0127) | **CURTA — oimpresso supera** em rigor LGPD-aware (mercado IDP gringo não tem LGPD pressure) |
| **Audit log com undo single + bulk** | Atlassian Compass tem audit, mas undo bulk não é padrão IDP — é feature de tools de compliance separados (Datadog Workflow Audit, etc) | ✅ `Modules/Auditoria` `RevertService` (single ADR 0127) + `BulkRevertActivityRequest` ≤50 ids + reason min:10 + whitelist UNREVERTIBLE | **CURTA — oimpresso supera** em UX de revert (raríssimo em IDPs) |
| **MCP server expondo catalog/state pro time** | Cortex MCP jul/25 — 25.109 queries em 5 meses (prova mercado) | ✅ `mcp.oimpresso.com` com 352+ docs sync via webhook + tools (`brief-fetch`, `tasks-list`, `cycles-active`, `decisions-search`, `memoria-search`, `my-work`, `my-inbox`) + ingest Claude Code sessions + RBAC actor-bound | **CURTA — paridade ou melhor**. oimpresso tem >7 tools MCP especializadas + identity mesh (`mcp_actors` ADR 0081) que Cortex não documenta. |
| **Brief/contexto consolidado (~3k tokens) pra agentes IA** | Port "context lake" (anúncio Series C dez/25) — está construindo; Cortex MCP serve queries on-demand mas não brief consolidado | ✅ `Modules/Brief` cron 6x/dia gera ~3k tokens consolidados, validator (4 invariantes), cache 5min, fetched por skill `brief-first` Tier A always-on (ADR 0091) | **CURTA — oimpresso pode SUPERAR Port "context lake"** em prática hoje. Port anunciou dez/25 mas ainda não shipou; Brief oimpresso está em prod desde Sprint 1. |
| **Jira-style tasks/cycles internos (substituto Linear/Jira)** | Nenhum IDP é task tracker — todos integram (Jira, Linear, GitHub Issues). Mistura paradigmas. | ✅ `Modules/ProjectMgmt` Kanban free-flow + cycles 2-semanas + tasks via tools MCP (`tasks-list`, `tasks-update`) + `mcp_jira_*` tables; ADR 0070 removeu CURRENT.md/TASKS.md | **CURTA mas atípica** — oimpresso fundiu IDP + tracker num só. Pode ser vantagem (sem context-switch) ou dívida (Jira/Linear fazem melhor). Não há padrão de mercado pra comparar. |
| **Identity Mesh (`mcp_actors` IA + humano)** | Nenhum dos 5 — Port mais perto com "human-to-agent collaboration experience" mas ainda assume agent = service account. Sem manifest YAML per-actor. | ✅ `Modules/TeamMcp` `ActorsController` + tabela `mcp_actors` + manifest YAML (`memory/governance/IDENTITY-MESH-MANIFESTS.md`) bind actor↔token + RBAC scopes (ADR 0081) | **CURTA — oimpresso SUPERA**. Manifest declarativo per-actor (humano OU IA, com trust tier) é mais maduro que SaaS state-of-art dez/25. |
| **UI Dashboard (catalog + scorecards + drill-down)** | Backstage UI plugin marketplace; Cortex out-of-the-box drill-down leadership reports + persona dashboards (Eng Mgr/Platform/SRE); Port custom views por persona | 🟡 5 Pages em `resources/js/Pages/Governance/` (Audit, Dashboard, DriftAlerts, ModuleGrades, Policies) + Admin Center 10 widgets + Auditoria Index/Detail — funcional, mas **sem persona-views**, sem leadership-report drill-down, sem chart timeseries reading mensal | **MÉDIA** — funcional pra Wagner-only, fica atrás de Cortex em drill-down/persona |
| **AI Judge / LLM-evaluated scorecards** | Cortex AI Governance scorecard (set/25) + AI Readiness + AI Maturity (3 verticais separados); Port mencionou em Series C mas sem shipping | 🟡 `Modules/Brief` usa Brain B (sonnet-4-6) pra gerar resumo, mas **não há LLM como juiz de scorecard** (apenas gerador). v4 ainda é code-driven. | **MÉDIA** — pode ativar V2 Wave 24 baseline 30d (ADR 0160 review_triggers). |
| **Initiatives com deadline + auto-backlog** | Cortex pioneirou (Initiatives = scorecard com deadline + reminders + backlog auto); Port + OpsLevel adotaram | 🔴 **AUSENTE** — oimpresso tem cycles 2-semanas + cycle-goals-track + tasks, mas nada que ligue scorecard fail → auto-task com deadline. Initiative = conceito Cortex que não existe canon aqui. | **LONGA** — gap claro. |
| **Ecosystem integrations native (60+ pre-built)** | Cortex 60+ (PagerDuty, GitHub, Datadog, etc); Port plug-and-play | 🔴 oimpresso tem integrações pontuais (Asaas, Inter, SEFAZ, WhatsApp, Meta) mas **sem catálogo unificado de "integration cards"** estilo Backstage/Cortex. Cada integração vive solta no módulo. | **MÉDIA-LONGA** — não é prioridade, mas é gap se time MCP crescer e quiser auto-discover. |
| **Plugin marketplace / extensibility user-facing** | Backstage 200+ plugins community; Port custom blueprints | 🔴 oimpresso é monolito modular fechado — não há plugin externo, não há marketplace. Skills/Rules existem mas são internas. | **LONGA — mas intencional**. oimpresso não quer ser plataforma terceira; é ERP. Gap legítimo de escopo, não de qualidade. |

**Síntese §2:** dos ~16 vetores avaliados, oimpresso **bate ou SUPERA em 11**, fica em paridade em 1 (MCP server), está **atrás em 4** (UI persona-views, AI judge, Initiatives, ecosystem catalog).

---

## §3 Avaliação — 5 features cross-cutting que IDP top 2026 tem e oimpresso não

| # | Gap | Impacto | Esforço (IA-pair, ADR 0106) | Pré-req? |
|---|---|---|---|---|
| 1 | **Initiatives** — entidade `mcp_initiatives` que liga `scorecard_fail → auto-task com deadline + reminders + escalation`. Cortex pioneirou e virou padrão IDP. Hoje, se um módulo cai pra 67/100, o Wagner vê no dashboard mas não há automação que abra task "Restaurar Crm pra ≥80 até 2026-06-15". | **ALTO** — fecha o loop "métrica fail → ação obrigatória" da Constituição v2 §4. Aproveita `Modules/ProjectMgmt` cycles existente. | **8-12h IA-pair** (1 wave) — migration `mcp_initiatives` + `InitiativeService` + `governance:initiative-create` artisan + cron daily check threshold breach + integration `tasks-create` MCP tool | Nenhum bloqueante — ADR 0160 v4 já entrega bucket + meta, basta amarrar fail → task |
| 2 | **Persona-view dashboards** (Eng Mgr / Platform / Founder) em `Modules/Governance/Dashboard.tsx`. Hoje Wagner vê tudo de uma vez. Cortex mostra 3 dashboards filtrados por persona com KPIs relevantes ao role. | **MÉDIO** — alto pra Wagner quando time MCP entrar (Felipe/Maiara/Eliana/Luiz precisam de views próprias) | **6-10h IA-pair** — refactor `Dashboard.tsx` + 3 charters novos + RBAC view-binding | Time MCP precisa estar onboarded (ADR 0081 Identity Mesh tem trust tiers — usar L1 Wagner / L2 Felipe-Maiara / L3 Luiz-Eliana) |
| 3 | **AI Judge V2** ativo (Brain B avalia scorecard YAML + sugere ajustes baseado em código real). ADR 0160 review_trigger já prevê — "Quando AI-driven scorecard acumular 30 dias baseline". Cortex AI Governance é referência. | **MÉDIO** — sutil; pode produzir falsos positivos. ROI duvidoso até time MCP escalar. | **12-20h IA-pair** — `AiScorecardJudgeService` + prompt template + diff-suggest UI em `GovernanceV4Dashboard.tsx` + Pest comparando juízes humano vs IA | Baseline 30d de scorecard runs (`mcp_scorecard_runs` table criada Wave 24) — esperar dados acumularem |
| 4 | **Integration catalog UI** estilo Backstage — `/governance/integrations` listando todas conexões externas (Asaas, Inter, SEFAZ, Meta, WhatsApp, Vaultwarden, Tailscale, MCP) com health/last-sync/token-expiry. | **MÉDIO-BAIXO** — utilidade limitada hoje (Wagner sabe tudo de cabeça); cresce conforme time MCP entra | **8-12h IA-pair** — `IntegrationCatalogService` scanner + `IntegrationCard.tsx` componente + cron health-check rotativo | Nenhum bloqueante — pode rodar como Wave separada |
| 5 | **Leadership drill-down reports** (Wagner-monthly) PDF/email automático — Cortex faz out-of-the-box. Hoje Wagner consulta `Dashboard.tsx` manual. | **BAIXO** — Wagner é dono solo hoje; pdf mensal vira ruído. Cresce conforme Eliana[E] entrar como financeiro/legal e quiser report. | **6h IA-pair** — `MonthlyReportCommand` + template Blade/PDF + cron 1º dia do mês + email Wagner | Nenhum |

**Outros gaps NÃO listados (escopo proposital fora de IDP):**
- Plugin marketplace — oimpresso é ERP fechado por design (ADR 0094 §1 Context as a product)
- Blueprint no-code modeling — `module.json` + `SCOPE.md` cobrem o domínio sem precisar abstrair

---

## §4 Recomendação final — comece por #1 (Initiatives)

**Justificativa:** alto impacto + sem pré-req bloqueante + aproveita 3 ativos já maduros:

1. `Modules/Governance` v4 com scorecards bucket-scoped (entrega Wave 24)
2. `Modules/ProjectMgmt` Kanban + cycles + `tasks-create` MCP tool
3. ADR 0094 §4 "loop fechado por métrica" — Initiatives É exatamente a materialização técnica do princípio constitucional

**Fechar loop:** scorecard fail (`Crm 67/100, abaixo meta 80`) → `InitiativeService::createFromBreach()` → INSERT `mcp_initiatives` com deadline T+30d → cron daily `governance:check-initiative-breach` reminders T-7d/T-3d/T-1d/T+1d → escalation Wagner → close quando scorecard volta acima meta + auditoria append-only do ciclo completo.

Difere de "criar task qualquer" porque: (a) deadline é obrigatório, (b) backlog é auto-gerado a partir das sub-dimensões que falharam (D5 cobertura baixa → "adicionar Pest cross-tenant"; D8 FormRequests baixos → "extrair X FormRequests"), (c) close é automático quando métrica volta.

**Bônus colateral:** desbloqueia ADR 0160 review_trigger "Quando 3+ módulos abusarem mudança de bucket sem label aprovação (gaming)" — Initiative pode detectar gaming e abrir investigação automática.

**Próxima ação concreta hoje (2026-05-17):** abrir spec inicial em `memory/requisitos/Governance/SPEC-INITIATIVES.md` com 4 user stories:
- US-GOV-INIT-001: criar Initiative a partir de scorecard breach
- US-GOV-INIT-002: cron daily check + reminders
- US-GOV-INIT-003: auto-close quando métrica volta
- US-GOV-INIT-004: UI `/governance/initiatives` lista + drill-down

Sem ADR nova necessária — Initiatives cabe sob ADR 0094 §4 + ADR 0160 (extensão natural do v4).

---

## Fontes consultadas (Fase 1 sem contaminação)

- [Backstage Spotify enterprise overview 2026 (Roadie)](https://roadie.io/backstage-spotify/)
- [Port nets $100M Series C agentic AI hub (SiliconANGLE dez/25)](https://siliconangle.com/2025/12/11/port-nets-100m-turn-developer-portal-agentic-ai-hub/)
- [Port Agentic Engineering Platform (Port blog)](https://www.port.io/blog/port-agentic-engineering-platform)
- [OpsLevel Scoped Scorecards vs Cortex](https://www.opslevel.com/resources/opslevel-vs-cortex-whats-the-best-internal-developer-portal)
- [Cortex Wrapped 2025 AI Excellence](https://www.cortex.io/post/cortex-wrapped-2025-the-year-of-ai-excellence)
- [Cortex MCP docs](https://docs.cortex.io/get-started/mcp)
- [Cortex Initiatives — when scorecards need a deadline](https://www.cortex.io/post/cortex-initiatives-when-scorecards-need-a-deadline)
- [Cortex AI Governance solutions](https://docs.cortex.io/solutions/ai-governance/configure)
- [Atlassian Compass scorecards + package dependencies 2025](https://support.atlassian.com/compass/docs/understand-how-scorecards-work/)
- [Compass scorecards design effective (Atlassian)](https://www.atlassian.com/software/compass/guide/design-and-architecture/scorecard-design)
- [Port vs Backstage vs Cortex 2026 (Tasrie IT)](https://tasrieit.com/blog/port-vs-backstage-vs-cortex-developer-portal-comparison-2026)
- [Backstage alternatives 2026 (Cortex)](https://www.cortex.io/post/backstage-alternatives-what-engineering-leaders-need-to-know-in-2026)
- [Configuration drift 2026 guide (Nudge Security)](https://www.nudgesecurity.com/saas-security-glossary/configuration-drift)
- [Shadow mode, drift alerts and audit logs (VentureBeat)](https://venturebeat.com/orchestration/shadow-mode-drift-alerts-and-audit-logs-inside-the-modern-audit-loop)
