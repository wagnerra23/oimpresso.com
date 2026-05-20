---
module: Financeiro
purpose: "Contas a pagar/receber, fluxo de caixa, DRE BR."
contains:
  - "AssinaturaController — FIN-004 atualizar cobrança recorrente (multi-tenant Tier 0, gateway delegado a AssinaturaCobrancaService)"
  - "BoletoController"
  - "CategoriaController"
  - "CobrancaController — F3 PaymentGateway UI Tela 1 (substitui /financeiro/boletos com escopo expandido boleto+pix+pix_recv+card; ADR 0144 + 0170)"
  - "ConciliacaoController — Onda 19 OFX MVP upload + parser STMTTRN + fuzzy match (US-FIN-009)"
  - "ContaBancariaController"
  - "ContaPagarController"
  - "ContaReceberController"
  - "CoworkSidebarController — endpoint JSON Mock Cowork (sidebar real via ShellMenuBuilder + 3 camadas habilitação, sem hardcode business_id; ADR 0093 Tier 0)"
  - "DashboardController"
  - "DataController"
  - "ExtratoController"
  - "FinanceiroController"
  - "FluxoController"
  - "InstallController"
  - "PlanoContaController — Onda 18 tela /plano-contas dedicada hierárquica BR (49 entries Receita Federal/DCASP)"
  - "RelatoriosController"
  - "UnificadoController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/ProjectMgmt"
  - "MCP server admin → Modules/TeamMcp"
trust_required: L3
owner: wagner
permission_prefix: financeiro.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /financeiro/*
drift_alerts: []
---

# Modules/Financeiro

## Missão

Contas a pagar/receber, fluxo de caixa, DRE BR.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
