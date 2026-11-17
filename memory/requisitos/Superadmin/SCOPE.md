---
module: Superadmin
purpose: "Duas partes num módulo: (1) console do operador da plataforma (/superadmin/*, middleware superadmin, cross-tenant por desenho) — vende e administra o produto (pacotes, assinaturas, comunicador, páginas do site, configurações globais) e administra os tenants e o acesso (criar e destruir business, resetar senha, Usuário 360 com roles, permissões efetivas, tokens, sessões e lockouts); (2) as telas de assinatura do CLIENTE (/subscription/*: Minha assinatura e Pagar), protegidas pela permissão superadmin.access_package_subscriptions e fora do middleware superadmin. É um dos módulos que furam o business_id global scope legitimamente, sempre com a marca // SUPERADMIN."
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
  - "Cálculo e contagem de assinatura recorrente (rb_*, MRR, churn) → Modules/RecurringBilling — o Superadmin LÊ pelo repositório e pelo Model do dono, nunca recalcula nem consulta a tabela por query crua"
  - "Emissão de cobrança (PIX Automático, mandato BCB, CNAB) e os eventos CobrancaPaga/CobrancaVencida → Modules/PaymentGateway (ADR 0170) — o Superadmin só ESCUTA os eventos (Listeners/OnCobranca*) e reage na assinatura"
  - "Aplicação do bloqueio do desktop Delphi (leitura de business.officeimpresso_bloqueado nos endpoints de licença) → Modules/Officeimpresso e a API do Modules/Connector — o Superadmin só GRAVA o flag nos listeners de cobrança"
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
  - 0093-multi-tenant-isolation-tier-0
  - 0170-onda5-simplificada
  - 0170-paymentgateway-extracao-camada-cobranca
url_prefixes:
  - /superadmin/*
  - /subscription/* (pay-stack · flutterwave · {package_id})
  - /pricing
  - /pricing/*
  - /all-subscriptions
  - /page/{slug}
db_tables_owned:
  - packages
  - subscriptions
  - superadmin_communicator_logs
  - superadmin_frontend_pages
drift_alerts: []
---

# Modules/Superadmin

## Missão

Pacotes e assinaturas da plataforma multi-tenant, e o console do operador. O console
(`/superadmin/*`) é do operador da plataforma; as telas `/subscription/*` (Minha assinatura,
Pagar) são do **cliente** — qualquer usuário com `superadmin.access_package_subscriptions`
(`SubscriptionController.php:62`), fora do middleware `superadmin`.

> Até 2026-11-16 esta seção dizia "Só Wagner toca". Era falso para `/subscription/*`.

## Tabelas

`db_tables_owned` lista as tabelas que as migrations do módulo **criam**
(`Schema::create` em `Database/Migrations`, medido em 2026-11-16). As migrations também
inserem linhas e colunas na tabela `system` (núcleo), que **não** é do módulo.

## Trust level

**L0** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **v1.1.0** (2026-11-16) — lote L6 da auditoria: Missão e `purpose` deixam de dizer "só Wagner toca" (as telas `/subscription/*` são do cliente); entram `db_tables_owned`, ADRs 0093 e 0170, PaymentGateway e Officeimpresso/Connector no `not_contains`; `/pricing` exato acrescentado em `url_prefixes` (antes só `/pricing/*`).
