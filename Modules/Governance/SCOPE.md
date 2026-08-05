---
module: Governance
purpose: "A regra está sendo cumprida? — mede e mostra o cumprimento (drift de escopo/deploy/índice, nota de módulo, scorecard SDD, policies, trilha de tool MCP) e injeta o veredito no Daily Brief. Quem bloqueia merge é o CI em scripts/governance/, não este módulo."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "DashboardController — UI /governance painel consolidado (KPIs ADR pending + policies + audit + drift + actors + compliance score)"
  - "PoliciesController — CRUD inline mcp_governance_rules (toggle enabled MVP; edit JSON futuro)"
  - "AuditController — drill-down mcp_audit_log filtrável (período/actor/endpoint/status)"
  - "DriftAlertsController — runtime scan SCOPE.md vs filesystem real + persisted alerts cron"
  - "ModuleGradeController — /governance/module-grades Index ranking 34 módulos + Show drill-down 9 dimensões v3 + dossier markdown (ADR 0155 + Charter Goal 9 2026-05-17)"
  - "DsRolloutController — /governance/ds-rollout plano de portar o DS em ondas + Ledger de Conformidade DS (tradução F3 protótipo Cowork · census via scripts/ds-ledger.mjs)"
  - "CustosController — /governance/custos dashboard de custo de IA por business (US-COPI-070; recebido do Modules/Jana em 2026-08-05, ADR 0366 §D-B — o `Chat.charter.md` já mandava 'custo vai pra /governance')"
  - "QualidadeIaController — /governance/qualidade-ia trend das métricas de RAG/memória (MEM-MET-4, ADR 0050; recebido do Modules/Jana em 2026-08-05 — decisão [W]: eval é gate de conformidade, medido contra piso igual module-grades e drift)"
  - "InstallController — install/uninstall hooks (ADR 0024)"
  - "DataController — sidebar/permissions hooks (UltimatePOS pattern)"
  - "ActionGate middleware — runtime gate (modo warn|strict por config)"
not_contains:
  - "Decision flow (Risk/Confidence/Policy Engine) → Modules/ADS"
  - "Skills governance → Modules/ADS"
  - "Tokens MCP CRUD → Modules/Forja"
  - "Identity Mesh (mcp_actors) UI → Modules/Forja"
  - "Knowledge browsing (ADRs read-only) → Modules/KB"
  - "Constitution doc edit → memory/governance/CONSTITUTION.md (não DB)"
  - "Module Grade v4 Tri-pane (era /admin/governance/v4 no Modules/Admin) → REMOVIDO com o Admin Center em 2026-07-29 (ADR 0360 supersede 0122); a fronteira não existe mais"
  - "MCP usage cross-team dashboard (/jana/admin/governanca) → Modules/Jana (drift — migrar pra cá Fase 5, ver drift_alerts)"
trust_required: L1
owner: wagner
permission_prefix: governance.*
charter_adr: 0086
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0122-admin-center-ct100
url_prefixes:
  - /governance/*
routes:
  # Fonte canônica: Modules/Governance/Http/routes.php
  - "GET  /governance                              → DashboardController@index           (governance.admin.dashboard)"
  - "GET  /governance/policies                     → PoliciesController@index            (governance.policies.index)"
  - "POST /governance/policies/{id}/toggle         → PoliciesController@toggle           (governance.policies.toggle)"
  - "GET  /governance/audit                        → AuditController@index               (governance.audit.index)"
  - "GET  /governance/drift                        → DriftAlertsController@index         (governance.drift.index)"
  - "GET  /governance/module-grades                → ModuleGradeController@index         (governance.module-grades.index)"
  - "GET  /governance/module-grades/{name}         → ModuleGradeController@show          (governance.module-grades.show)"
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
db_tables_consumed:
  # Consumidas pelas 2 telas recebidas do Jana em 2026-08-05 (ADR 0366 §D-B).
  # LEITURA apenas, e o dono continua sendo o Modules/Jana — a ADR moveu a tela,
  # não a tabela (o item 4 do plano §D-C, que move as `Mcp*`, NÃO está autorizado).
  # Precedente do mesmo formato: Modules/Forja já importa Modules\Jana\Entities\Mcp\McpTask.
  - jana_conversas          # CustosService — agregação de custo por business
  - jana_mensagens          # CustosService — tokens in/out por mensagem
  - copiloto_memoria_metricas  # QualidadeIaController via Modules\Jana\Entities\MemoriaMetrica
  - jana_memoria_gabarito   # QualidadeIaController — contagem do gabarito ativo
drift_alerts:
  # 2026-05-17 — atualizado: Copiloto foi renomeado Jana em Fase 3.7 PR-2 (2026-05-06).
  # Drift ainda VIVO. ETA migração: Fase 5 (próxima sessão dedicada).
  - controller: "Modules/Jana/Http/Controllers/Admin/GovernancaController.php"
    pertence_a: "Modules/Governance (MCP usage cross-team)"
    motivo: "Dashboard de MCP usage cross-team (cf. ADR 0053) é governança, não chat Jana. SCOPE.md de Jana já cataloga este drift (Fase 5)."
    url_atual: "/jana/admin/governanca"
    eta_migracao: "Fase 5 — manter URL via Route::redirect 301 (pattern Fase 3.7 PR-1)"
---

# Modules/Governance — UI consolidada de governança

## Missão

Onde Wagner opera **5min/dia**: aprova ADRs pendentes, ajusta policies, vê audit highlights, resolve drift alerts. Constituição Art. 8 + Art. 9 operacional.

## Quando este módulo é tocado

| Trigger | Quem | Ação |
|---|---|---|
| Wagner abre `/governance` | L1 | Painel consolidado: KPIs + ADRs pending + policies + audit + drift |
| ActionGate processa request L2+ | sistema | Lê rules + log decisão; modo warn|strict |
| Wagner edita policy (futuro) | L1 | UPDATE em mcp_governance_rules + audit |

## Quando NÃO é tocado

- ❌ Decision flow ADS (Risk/Confidence/Policy Engine) → Modules/ADS
- ❌ Skill governance → Modules/ADS
- ❌ Token / scope CRUD → Modules/Forja
- ❌ Constitution doc edit → file `memory/governance/CONSTITUTION.md` direto

## ActionGate modes

```
GOVERNANCE_ACTIONGATE_MODE=off    → middleware loaded mas não checa
GOVERNANCE_ACTIONGATE_MODE=warn   → log violations sem bloquear (DEFAULT MVP)
GOVERNANCE_ACTIONGATE_MODE=strict → block 403 + audit obrigatório
```

Estado MVP: `warn`. Coleta sinal de violations por 4 semanas antes de virar `strict`.

## Estado de implementação

| Item | Status |
|---|---|
| Scaffold módulo (8 peças) | ✅ feito |
| DashboardController + KPIs | ✅ feito (MVP — Inertia render falta) |
| ActionGate middleware (warn) | ✅ feito |
| UI Inertia /governance | ⏸️ próxima sessão (componente React) |
| PoliciesController CRUD | ⏸️ próxima sessão |
| AuditController drill-down | ⏸️ próxima sessão |
| DriftAlertsController | ⏸️ próxima sessão |

---

- **v1.0.0** (2026-05-05) — Scaffold MVP. Dashboard + ActionGate. Inertia frontend pendente.
