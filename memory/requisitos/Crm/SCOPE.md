---
module: Crm
# ⚠️ Crm É o módulo de CLIENTE / contatos do cliente. UltimatePOS herdou o nome
# "Crm" — NÃO existe módulo `Cliente` em `Modules/`. Requisitos canônicos: memory/requisitos/Crm/
# (a antiga memory/requisitos/Cliente/ foi consolidada aqui em 2026-06-01).
# Ver memory/reference/crm-e-o-modulo-de-cliente.md
purpose: "Pipeline pré-venda herdado do UltimatePOS (leads, follow-ups, campanhas, propostas, call logs) — EM DEPRECAÇÃO por ADR 0301. Hospeda ainda, por acidente histórico, os endpoints JSON do drawer de Cliente (/cliente/*) e o portal do contato (/contact/*), cujo dono real é o ContactController do core."
migracao_ui: "liberado — decisao [W] 2026-11-03 ('crm pode sim'); cada tela segue o MWART da ADR 0104 e liga por flag so na empresa 1; a captura publica de lead segue desligada ate o [W]"
contains:
  - "CallLogController"
  - "CapturaLeadController"       # Captura pública de lead com origem/UTM no funil (US-CRM-096)
  - "CampaignController"
  - "ClienteAuditoriaController"   # Wave F LGPD (#1344 merged 2026-05-21)
  - "ClienteAutosaveController"    # Drawer 760 autosave draft (Wave A-G refinada #1382)
  - "ClienteIaController"          # Wave E IA cards (#1344 merged 2026-05-21)
  - "FunilController"            # Funil de vendas em kanban /crm/funil (US-CRM-092) — sai da depreciação pela ADR 0441
  - "ClienteLookupController"      # Drawer 760 endpoint lookup CEP/CNPJ (Wave A-G refinada #1382)
  - "ClienteOssDataController"     # 7 endpoints JSON read-only sub-tabs OssTab drawer 760 (ADR 0179, #1886)
  - "ClienteVeiculosController"    # Drawer 760 sub-tab Placas (Daniela @ Martinho #1776 2026-05-27)
  - "ContactAddressController"     # US-CRM-078 — múltiplos endereços do cliente (PR1, drift do check-scope)
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
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: crm.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /crm/*
  - /cliente/* (drawer 760 — draft · lookup · {id}; o miolo e co-provido pelo NUCLEO, routes/web.php:591 e :607)
  - /contact/* (portal do cliente — profile · ledger · purchases · sells · bookings · order-request)
drift_alerts: []
---

# Modules/Crm

## Missão

UltimatePOS herdado (CRM core).

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **2026-11-03** — `migracao_ui` passa de `bloqueado-escopo` a `liberado` por decisão [W] ("crm pode sim"), no programa de levar o protótipo à produção. A ADR 0301 (pipeline em depreciação) segue aceita; a liberação não a revoga.
- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
