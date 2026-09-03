---
module: NfeBrasil
purpose: "Motor headless de comunicação com a SEFAZ: emite NF-e/NFC-e/NFS-e, guarda e opera o certificado A1, aplica eventos (cancelamento, CC-e, inutilização, manifestação DF-e) e calcula tributos. Não tem tela própria no sidebar — a UI é do Modules/Fiscal."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
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
  - "ContingenciaController — liga/desliga contingencia SEFAZ por tenant (US-NFE-006, ADR TECH-0002)"
db_tables_owned:
  - nfe_certificados
  - nfe_emissoes
  - nfe_eventos
  - nfe_inutilizacoes
  - nfe_fiscal_rules
  - nfe_business_configs
  - nfe_fiscal_rule_tax_rate_links (bridge ADR ARQ-0005)
  - nfe_sefaz_status (saude do autorizador por UF — global por desenho da ADR TECH-0002; sem business_id)
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: nfebrasil.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /nfe-brasil/* (operacional — configuracao · contingencia · tributacao · api · transactions · inutilizacoes · manifestacao + raiz)
  - /nfebrasil/* (install do módulo + resource nfebrasil)
  - /api/v1/* (scaffold nWidart · auth:sanctum — co-provido por Financeiro e RecurringBilling)
drift_alerts: []
---

# Modules/NfeBrasil

## Missão

NFC-e + NF-e + SPED brasileiro. Spec-ready.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
