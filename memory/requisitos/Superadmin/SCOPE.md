---
module: Superadmin
purpose: "Console do operador da plataforma (superadmin-only, cross-tenant por desenho). Vende e administra o produto — pacotes, assinaturas e checkout — e administra os tenants e o acesso: criar, desativar e destruir business, resetar senha, e a vista Usuário 360 com roles, permissões efetivas, tokens, sessões e lockouts. É o único módulo que fura o business_id global scope legitimamente."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "BaseController"
  - "BusinessController"
  - "CommunicatorController"
  - "DataController"
  - "InstallController"
  - "PackagesController"
  - "PageController"
  - "PesaPalController"
  - "PricingController"
  - "SubscriptionController"
  - "SuperadminController"
  - "SuperadminSettingsController"
  - "SuperadminSubscriptionsController"
  - "Usuario360Controller"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L0
owner: wagner
permission_prefix: superadmin.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /superadmin/*
drift_alerts: []
---

# Modules/Superadmin

## Missão

Pacotes + subscription multi-tenant. Só Wagner toca.

## Trust level

**L0** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
