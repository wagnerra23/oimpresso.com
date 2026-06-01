---
module: Crm
# ⚠️ Crm É o módulo de CLIENTE / contatos do cliente. UltimatePOS herdou o nome
# "Crm" — NÃO existe Modules/Cliente. Requisitos canônicos: memory/requisitos/Crm/
# (memory/requisitos/Cliente/ é legado/stale). Ver memory/reference/crm-e-o-modulo-de-cliente.md
purpose: "Módulo de CLIENTE / contatos do cliente (UPOS herdou o nome 'Crm'; NÃO existe Modules/Cliente): cadastro, drawer 760px com aba Endereço, múltiplos endereços (US-CRM-078)."
contains:
  - "CallLogController"
  - "CampaignController"
  - "ClienteAuditoriaController"   # Wave F LGPD (#1344 merged 2026-05-21)
  - "ClienteAutosaveController"    # Drawer 760 autosave draft (Wave A-G refinada #1382)
  - "ClienteIaController"          # Wave E IA cards (#1344 merged 2026-05-21)
  - "ClienteLookupController"      # Drawer 760 endpoint lookup CEP/CNPJ (Wave A-G refinada #1382)
  - "ClienteOssDataController"     # 7 endpoints JSON read-only sub-tabs OssTab drawer 760 (ADR 0179, #1886)
  - "ClienteVeiculosController"    # Drawer 760 sub-tab Placas (Daniela @ Martinho #1776 2026-05-27)
  - "ContactBookingController"
  - "ContactLoginController"
  - "CrmDashboardController"
  - "CrmMarketplaceController"
  - "CrmSettingsController"
  - "DashboardController"
  - "DataController"
  - "InstallController"
  - "LeadController"
  - "LedgerController"
  - "ManageProfileController"
  - "OrderRequestController"
  - "ProposalController"
  - "ProposalTemplateController"
  - "PurchaseController"
  - "ReportController"
  - "ScheduleController"
  - "ScheduleLogController"
  - "SellController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/ProjectMgmt"
  - "MCP server admin → Modules/TeamMcp"
trust_required: L3
owner: wagner
permission_prefix: crm.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /crm/*
drift_alerts: []
---

# Modules/Crm

## Missão

UltimatePOS herdado (CRM core).

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
