---
module: Financeiro
purpose: "Contas a pagar/receber, fluxo de caixa, DRE BR."
contains:
  - "AdvisorAccessController — Onda 31 US-FIN-037 grant/revoke acesso contador parceiro (biz-side, consent LGPD)"
  - "Advisor/AdvisorAuthController — Onda 31 US-FIN-037 login/logout guard web-advisor isolado"
  - "Advisor/AdvisorPortalController — Onda 31 US-FIN-037 dashboard /advisor (cards Meus clientes)"
  - "AssinaturaController — FIN-004 atualizar cobrança recorrente (multi-tenant Tier 0, gateway delegado a AssinaturaCobrancaService)"
  - "BoletoController"
  - "CaixaController — F6 Soft wrapper Inertia /financeiro/caixa read-only sobre cash_registers core UltimatePOS (não migra dados; lifecycle abrir/fechar continua na header POS via CashRegisterController core)"
  - "CategoriaController"
  - "CobrancaController — F3 PaymentGateway UI Tela 1 (substitui /financeiro/boletos com escopo expandido boleto+pix+pix_recv+card; ADR 0144 + 0170)"
  - "ConciliacaoController — Onda 19 OFX MVP + scaffold Anexos/Aprovação"
  - "ContaBancariaController"
  - "ContaPagarController"
  - "ContaReceberController"
  - "CoworkSidebarController — endpoint JSON Mock Cowork (sidebar real via ShellMenuBuilder + 3 camadas habilitação, sem hardcode business_id; ADR 0093 Tier 0)"
  - "DashboardController"
  - "DataController"
  - "DreController — Wave reaplicação canon 2026-05-20 US-FIN-014a (tela dedicada /financeiro/dre, hierarquia clássica header/item/subtotal com highlight, % RL sinal preservado, Δ% mês ant., export PDF/Excel/CSV; substitui tab DRE legada em RelatoriosController)"
  - "ExtratoController"
  - "FinanceiroController"
  - "FluxoController"
  - "InstallController"
  - "PlanoContaController — Onda 18 tela /plano-contas dedicada + Fluxo fallback sem conta"
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
