---
module: Governance
purpose: "A regra está sendo cumprida? — mede e mostra o cumprimento (drift de escopo/deploy/índice, scorecard SDD, policies, trilha de tool MCP, custo e qualidade de IA) e injeta o veredito no Daily Brief. Quem bloqueia merge é o CI em scripts/governance/, não este módulo. (A nota de módulo saiu em 2026-09-15, ADR 0399.)"
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "DashboardController — UI /governance painel consolidado (KPIs ADR pending + policies + audit + drift + actors + compliance score)"
  - "PoliciesController — CRUD inline mcp_governance_rules (toggle enabled MVP; edit JSON futuro)"
  - "AuditController — drill-down mcp_audit_log filtrável (período/actor/endpoint/status)"
  - "DriftAlertsController — runtime scan SCOPE.md vs filesystem real (a lista de alertas persistidos, `DriftAlertService::persistedAlerts()`, devolve vazio — medido 2026-11-19)"
  - "DsRolloutController — /governance/ds-rollout plano de portar o DS em ondas + Ledger de Conformidade DS (tradução F3 protótipo Cowork · census via scripts/ds-ledger.mjs)"
  - "CustosController — /governance/custos dashboard de custo de IA por business (US-COPI-070; recebido do Modules/Jana em 2026-08-05, ADR 0366 §D-B — o `Chat.charter.md` já mandava 'custo vai pra /governance')"
  - "QualidadeIaController — /governance/qualidade-ia trend das métricas de RAG/memória (MEM-MET-4, ADR 0050; recebido do Modules/Jana em 2026-08-05 — decisão [W]: eval é gate de conformidade, medido contra piso igual ao drift — e igual ao module-grade, que era o outro exemplo até ser aposentado em 2026-09-15 pela ADR 0399)"
  - "InstallController — install/uninstall hooks (ADR 0024)"
  - "DataController — sidebar/permissions hooks (UltimatePOS pattern)"
  - "ActionGate middleware — alias `actiongate` registrado no ServiceProvider, modo warn|strict por config. Em 2026-11-19 nenhuma rota o aplica (`git grep -n actiongate -- '*routes*.php'` vazio); ligar ou aposentar é decisão [W] aberta (ADR 0086)"
  # Recebido do Modules/ADS em 2026-07-31 (ADR 0363 — o módulo deixou de existir; política→Governance, #5128).
  - "Services/PolicyEngine — decision flow (Risk/Confidence/Policy). O `ActionGate::handle` não o chama (só o cita em docblock); o consumidor em runtime medido em 2026-11-19 é o grafo de conhecimento do Modules/KB (tela admin do grafo)"
  # Peças fora dos controllers. Inventário arquivo a arquivo: SUPERFICIE.md (gerado por
  # `node scripts/governance/module-surface.mjs Governance --write`). Aqui fica o papel de cada grupo.
  - "Console/Commands — comandos artisan `governance:*`, `governanca:*`, `charter:*` e `observability:aggregate-daily`. Quais estão agendados: `app/Console/Kernel.php` (procure a assinatura). Os que não aparecem lá rodam sob demanda ou são chamados por outro artefato (ex. `governance:ragas-eval-alert` por `scripts/tests/ct100-jana-evals.sh`)"
  - "Services/Checkers — os DriftChecker plugáveis (ADR 0216 framework) que `governance:audit` executa via `DriftCheckerRegistry`; resultado em `DriftCheckResult`/`DriftFinding`"
  - "Services/Concerns — `PersistsDriftAlert` (grava em `mcp_alertas`) e `PublishesDriftToCentrifugo` (canal `governance:drift`)"
  - "Services/*BriefLineService + AgentOutcomeBriefSectionService — linhas que o Daily Brief injeta; quem as chama é `Modules/Forja/Console/Commands/GenerateBriefCommand.php`"
  - "Services/DriftAlertService, AuditDrillDownService, GovernanceRulesService, PolicyToggleService — backends das telas Drift, Audit e Policies"
  - "Services/ScopedScorecardEvaluator + InitiativeService + ObservabilitySnapshotService — scorecard snapshot, iniciativas e agregação de spans"
not_contains:
  # A linha "Decision flow (Risk/Confidence/Policy Engine) → Modules/ADS" saiu em
  # 2026-08-10: o ADS foi REMOVIDO em 2026-07-31 (ADR 0363) e a política veio PRA CÁ —
  # `Services/PolicyEngine.php` (#5128), agora declarado em `contains`. Mesmo caso da
  # linha do MCP usage abaixo: deixar em not_contains passaria a mentir.
  # Destino era o ADS até a remoção dele em 2026-07-31 (ADR 0363); skills foram pra Jana (#5129).
  - "Skills governance → Modules/Jana (Services/SkillsService.php, #5129)"
  - "Tokens MCP CRUD → Modules/Forja"
  - "Identity Mesh (mcp_actors) UI → Modules/Forja"
  - "Knowledge browsing (ADRs read-only) → Modules/KB"
  - "Constitution doc edit → memory/governance/CONSTITUTION.md (não DB)"
  - "Module Grade v4 Tri-pane (era /admin/governance/v4 no Modules/Admin) → REMOVIDO com o Admin Center em 2026-07-29 (ADR 0360 supersede 0122); a fronteira não existe mais"
  # A linha "MCP usage cross-team dashboard → Modules/Jana (drift)" saiu em
  # 2026-08-05: o drift FOI RESOLVIDO — a tela foi fundida no DashboardController
  # deste módulo (ADR 0366 §D-C item 1). Deixar em not_contains passaria a mentir.
trust_required: L1
owner: wagner
permission_prefix: governance.*
charter_adr: 0086
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0360-deprecacao-admin-center-supersede-0122
  - 0216-governance-drift-framework-driftchecker-plugavel
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0366-fronteira-jana-forja-governance-kb
  - 0392-fronteira-governance-audiencia-enforcement-na-concessao
  - 0399-aposentar-rubrica-module-grade-gate-e-baseline
url_prefixes:
  - /governance/*
routes:
  # Fonte canônica: Modules/Governance/Http/routes.php
  - "GET  /governance                              → redirect 302 para /ia               (governance.admin.dashboard)"
  - "GET  /governance/dashboard                    → DashboardController@index           (governance.admin.dashboard.legacy)"
  - "GET  /governance/policies                     → PoliciesController@index            (governance.policies.index)"
  - "POST /governance/policies/{id}/toggle         → PoliciesController@toggle           (governance.policies.toggle)"
  - "GET  /governance/audit                        → AuditController@index               (governance.audit.index)"
  - "GET  /governance/drift                        → DriftAlertsController@index         (governance.drift.index)"
  - "GET  /governance/ds-rollout                    → DsRolloutController@index           (governance.ds-rollout.index)"
  - "GET  /governance/custos                       → CustosController@index             (governance.custos.index)"
  - "GET  /governance/qualidade-ia                  → QualidadeIaController@index        (governance.qualidade-ia.index)"
  - "GET  /governance/install{,/uninstall,/update} → InstallController@*                 (governance.install.*)"
db_tables_owned:
  # Recebida do ADS (módulo extinto) em 2026-07-31 (ADR 0363 §1 — "a política tinha posse
  # partida"). Este módulo já tinha a leitura (ActionGate) e o toggle `enabled`
  # (PoliciesController); com o ADS extinto, passa a ser dono também do schema e
  # da escrita. A migration original saiu do repo no PR #5135 — o DDL vive no
  # baseline `database/schema/mysql-schema.sql`, e a tabela FICOU no E5 (não foi
  # dropada) justamente por ter dono e consumidor vivos.
  # Sem `business_id` POR DESIGN: é config global de superadmin (ADR 0093 não se aplica).
  - mcp_governance_rules
  # Criadas pelas migrations do próprio módulo (Modules/Governance/Database/Migrations/).
  # A `mcp_module_grades_history` foi criada e depois dropada (2026_09_15_160000, ADR 0399).
  - mcp_scorecard_runs
  - mcp_observability_spans
  - mcp_observability_aggregates_daily
  - mcp_governance_initiatives
  - mcp_sdd_scorecard_history
db_tables_consumed:
  # Consumidas pelas 2 telas recebidas do Jana em 2026-08-05 (ADR 0366 §D-B).
  # LEITURA apenas, e o dono continua sendo o Modules/Jana — a ADR moveu a tela,
  # não a tabela (o item 4 do plano §D-C, que move as `Mcp*`, NÃO está autorizado).
  # Precedente do mesmo formato: Modules/Forja já importa Modules\Jana\Entities\Mcp\McpTask.
  - jana_conversas          # CustosService — agregação de custo por business
  - jana_mensagens          # CustosService — tokens in/out por mensagem
  - copiloto_memoria_metricas  # QualidadeIaController via Modules\Jana\Entities\MemoriaMetrica
  - jana_memoria_gabarito   # QualidadeIaController — contagem do gabarito ativo
  # LEITURA da seção MCP do painel, via Modules\Jana\Services\GovernancaService.
  # O Service NÃO se moveu — a ADR 0366 moveu a TELA, não a tabela (o item 4 do
  # plano §D-C, que move as `Mcp*`, não está autorizado e o destino delas é Forja).
  - mcp_audit_log       # cada chamada MCP (append-only)
  - mcp_usage_diaria    # agregações diárias (cron 23:55)
  # Lidas ou escritas pelo próprio código do módulo (medido por `git grep` em
  # Modules/Governance em 2026-11-19). O dono de cada uma está fora daqui.
  - mcp_alertas          # escrita: PersistsDriftAlert, DetectDriftCommand, InitiativeService, ScorecardSnapshotCommand
  - mcp_alertas_eventos  # escrita junto com mcp_alertas
  - mcp_actors           # leitura: Dashboard e AuditDrillDownService (dono: Forja)
  - mcp_memory_documents # leitura: Dashboard e McpIndexFreshnessChecker
  - mcp_skill_versions   # leitura: Dashboard (dono: Forja)
  - jana_health_narratives # leitura: Dashboard (dono: Jana)
  - failed_jobs          # leitura: Dashboard (tabela do core Laravel)
  - system_access_log    # RoutesZombieChecker — tabela sem migration no repo; o checker sai `skipped`
drift_alerts: []
  # RESOLVIDO em 2026-08-05. O drift declarado aqui desde 2026-05-17
  # (Admin/GovernancaController do Jana, com `eta_migracao: Fase 5`) foi fechado
  # pela fusão da tela no DashboardController deste módulo — ADR 0366 §D-C item 1.
  # A URL antiga /ia/admin/governanca sobrevive por Route::redirect 301, como o
  # próprio `eta_migracao` previa ("manter URL via Route::redirect 301").
---

# Modules/Governance — UI consolidada de governança

## Missão

Onde Wagner opera **5min/dia**: aprova ADRs pendentes, ajusta policies, vê audit highlights, resolve drift alerts. Constituição Art. 8 + Art. 9 operacional.

## Quando este módulo é tocado

| Trigger | Quem | Ação |
|---|---|---|
| Wagner abre `/governance/dashboard` (`/governance` redireciona para `/ia`) | L1 | Painel consolidado: KPIs + ADRs pending + policies + audit + drift |
| ActionGate processa request L2+ | sistema | Previsto: lê rules + loga decisão, modo warn|strict. Em 2026-11-19 nenhuma rota aplica o middleware |
| Wagner edita policy (futuro) | L1 | UPDATE em mcp_governance_rules + audit |

## Quando NÃO é tocado

- ❌ Skill governance → **Modules/Jana** (`SkillsService`; era `Modules/ADS` até a remoção de 2026-07-31, [ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md))
- ❌ Token / scope CRUD → Modules/Forja
- ❌ Constitution doc edit → file `memory/governance/CONSTITUTION.md` direto

## ActionGate modes

```
GOVERNANCE_ACTIONGATE_MODE=off    → middleware loaded mas não checa
GOVERNANCE_ACTIONGATE_MODE=warn   → log violations sem bloquear (DEFAULT MVP)
GOVERNANCE_ACTIONGATE_MODE=strict → block 403 + audit obrigatório
```

O plano de 2026-05-05 era coletar sinal em `warn` por 4 semanas e então virar `strict`. Isso não foi revisitado, e como nenhuma rota aplica o middleware, não há sinal coletado. Ligar ou aposentar é decisão [W] aberta.

## Dependências de código

Não há `depends_on` neste frontmatter de propósito. Declarar `depends_on: [Whatsapp]` faz a catraca de acoplamento tratar o par `Governance>Whatsapp` como curado e mandar apagá-lo da baseline, com o import ainda vivo (medido 2026-11-19; é a lápide §5 2026-08-12 em `memory/proibicoes.md`). As setas reais saem da máquina:

```
node scripts/governance/catalog-graph.mjs --acoplamento --catraca
```

Em 2026-11-19 ela mostrava o módulo importando de Jana, Forja e Whatsapp, e sendo importado por Forja (brief) e KB (grafo).

## Estado de implementação

As 7 telas estão no ar (Dashboard, Policies, Audit, Drift, DsRollout, Custos, QualidadeIa). Policies, Audit, Drift e DsRollout têm `can:` na rota desde 2026-09-08 (ADR 0392 §D-D passo 2); Custos e QualidadeIa checam `jana.*` no construtor do controller; em 2026-11-19 `/governance/dashboard` tinha só `auth` na rota (confira o `routes.php` antes de citar). Por causa do `Gate::before` do admin, `can:` não barra o Admin de um business: o passo 1 da 0392 segue como decisão [W] aberta. O estado detalhado, os gaps abertos e as decisões pendentes de [W] ficam no [BRIEFING](BRIEFING.md), não aqui.

A tabela de estado que existia aqui era de 2026-05-05 (scaffold) e dizia que as telas eram "próxima sessão". Foi removida em 2026-11-19 porque descrevia o oposto do código.

---

- **v1.0.0** (2026-05-05) — Scaffold MVP. Dashboard + ActionGate.
- **v1.1.0** (2026-11-19) — Frontmatter alinhado ao código: rota `/governance` é redirect, `/governance/dashboard` listada, tabelas próprias e consumidas, ADRs posteriores ao scaffold. Auditoria Governance L4.
