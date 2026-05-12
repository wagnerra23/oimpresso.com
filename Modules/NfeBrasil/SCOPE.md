---
module: NfeBrasil
purpose: "NFC-e + NF-e + SPED brasileiro. Spec-ready."
contains:
  - "DataController"
  - "InstallController"
  - "NfeBrasilController"
  - "CertificadoController"
  - "TributacaoController — CRUD regras NCM (US-NFE-010 fase 2)"
  - "ConfigDefaultController — Nível 4 cascade (defaults business)"
  - "ImportRegrasController — import CSV bulk (US-NFE-010 fase 3)"
  - "NfeStatusController — endpoint JSON polling + Page Inertia (US-NFE-002 fase 2C)"
  - "NfeEmissaoController — emissão fiscal manual + reenvio DANFE email + download PDF (US-NFE-MANUAL, PR #262)"
  - "ManifestacaoController — Manifestação do Destinatário (US-NFE-052, PR #317)"
  - "NfeInutilizacaoController — UI admin pra inutilizar faixa NFe via SEFAZ (US-SELL-030)"
db_tables_owned:
  - nfe_certificados
  - nfe_emissoes
  - nfe_eventos
  - nfe_inutilizacoes
  - nfe_fiscal_rules
  - nfe_business_configs
  - nfe_fiscal_rule_tax_rate_links (bridge ADR ARQ-0005)
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/ProjectMgmt"
  - "MCP server admin → Modules/TeamMcp"
trust_required: L3
owner: wagner
permission_prefix: nfebrasil.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /nfebrasil/*
drift_alerts: []
---

# Modules/NfeBrasil

## Missão

NFC-e + NF-e + SPED brasileiro. Spec-ready.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
