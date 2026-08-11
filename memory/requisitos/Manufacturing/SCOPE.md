---
module: Manufacturing
purpose: "Receita/BOM e ordem de produção: transforma insumos em produto acabado (transactions.type=production_purchase), calculando custo unitário com perda e baixando estoque dos ingredientes."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "DataController"
  - "InstallController"
  - "ManufacturingController"
  - "ProductionController"
  - "RecipeController"
  - "SettingsController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: manufacturing.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /manufacturing/*
drift_alerts: []
---

# Modules/Manufacturing

## Missão

UltimatePOS manufacturing.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
