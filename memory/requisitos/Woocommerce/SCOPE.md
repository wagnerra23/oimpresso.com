---
module: Woocommerce
purpose: "Canal de venda WooCommerce: espelha o catálogo do oimpresso na loja WordPress (produtos, variações, categorias, tax rates, mídia) e converte pedido recebido por webhook em venda (transaction) no núcleo. Subordinado ao hub Vendas; não é loja nem gateway."
migracao_ui: "liberado — decisao [W] 2026-10-27 ('pode destravar todos os outros vou fazer todos'); cada tela segue o MWART da ADR 0104"
contains:
  - "DataController"
  - "InstallController"
  - "WoocommerceController"
  - "WoocommerceWebhookController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: woocommerce.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /woocommerce/*
  - /webhook/* (order created · updated · deleted · restored)
drift_alerts: []
---

# Modules/Woocommerce

## Missão

Integração POS ↔ WooCommerce.

> **DECISAO 2026-10-27 [W] — `migracao_ui` sai de `bloqueado-escopo` para `liberado`.**
> Textual, no pedido de migração das áreas restantes: *"pode destravar todos os outros vou fazer
> todos"*. Até esta data o campo dizia `bloqueado-escopo — aguarda decisao [W]; ver proibicoes e o
> SCOPE deste modulo` (fato datado, preservado aqui). A liberação vale para as 4 telas que o censo
> (`blade-migration-census.mjs`) lista no escopo: painel (`/`), configuração (`/api-settings`), log
> de sincronização (`/view-sync-log`) e detalhe do log (`/get-log-details/{id}`). Webhooks de
> pedido (`/webhook/*`) não são tela e ficam fora. Cada tela segue o MWART da ADR 0104.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **v1.1.0** (2026-10-27) — `migracao_ui` liberado por decisão [W] ("pode destravar todos os outros vou fazer todos").
