---
module: PontoWr2
will_rename_to: Ponto
will_rename_at_phase: 3.7
purpose: "Ponto eletrônico Portaria 671/2021. Renomear pra Ponto Fase 3.7."
contains:
  - "AprovacaoController"
  - "BancoHorasController"
  - "ColaboradorController"
  - "ConfiguracaoController"
  - "DashboardController"
  - "DataController"
  - "EscalaController"
  - "EspelhoController"
  - "ImportacaoController"
  - "InstallController"
  - "IntercorrenciaController"
  - "RelatorioController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/ProjectMgmt"
  - "MCP server admin → Modules/TeamMcp"
trust_required: L3
owner: wagner
permission_prefix: pontowr2.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /pontowr2/*
drift_alerts: []
---

# Modules/PontoWr2

## Missão

Ponto eletrônico Portaria 671/2021. Renomear pra Ponto Fase 3.7.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
