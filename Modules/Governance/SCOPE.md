---
module: Governance
purpose: "Governança consolidada — ActionGate runtime, audit dashboard, ADRs pending approvals, policies CRUD, drift alerts. Constituição Art. 8 + Art. 9 operacional."
contains:
  - "DashboardController — UI /governance painel consolidado (KPIs ADR pending + policies + audit + drift + actors + compliance score)"
  - "PoliciesController — CRUD inline mcp_governance_rules (toggle enabled MVP; edit JSON futuro)"
  - "AuditController — drill-down mcp_audit_log filtrável (período/actor/endpoint/status)"
  - "DriftAlertsController — runtime scan SCOPE.md vs filesystem real + persisted alerts cron"
  - "ModuleGradeController — /governance/module-grades Index ranking 34 módulos + Show drill-down 9 dimensões v3 + dossier markdown (ADR 0155 + Charter Goal 9 2026-05-17)"
  - "InstallController — install/uninstall hooks (ADR 0024)"
  - "DataController — sidebar/permissions hooks (UltimatePOS pattern)"
  - "ActionGate middleware — runtime gate (modo warn|strict por config)"
not_contains:
  - "Decision flow (Risk/Confidence/Policy Engine) → Modules/ADS"
  - "Skills governance → Modules/ADS"
  - "Tokens MCP CRUD → Modules/TeamMcp"
  - "Identity Mesh (mcp_actors) UI → Modules/TeamMcp"
  - "Knowledge browsing (ADRs read-only) → Modules/KB"
  - "Constitution doc edit → memory/governance/CONSTITUTION.md (não DB)"
trust_required: L1
owner: wagner
permission_prefix: governance.*
charter_adr: 0086
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0086-fase-5-mvp-governance-actiongate-warn
url_prefixes:
  - /governance/*
db_tables_owned:
  - mcp_governance_rules (compartilha com ADS — ActionGate lê, ADS write rules de decision flow)
drift_alerts:
  - controller: "(esperando migração)"
    pertence_a: "Modules/Copiloto/Http/Controllers/Admin/GovernancaController.php"
    motivo: "GovernancaController existente em Copiloto pertence aqui"
    eta_migracao: "Fase 5 (próxima sessão)"
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
- ❌ Token / scope CRUD → Modules/TeamMcp
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
