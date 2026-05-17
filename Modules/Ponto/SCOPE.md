---
module: Ponto
purpose: "Ponto eletrônico Portaria 671/2021. Renomeado de PontoWr2 em Fase 3.7 PR-2 (2026-05-06). URLs/permissions/config keys mantêm prefixo legacy `pontowr2.*` por compatibilidade."
contains:
  - "Api/MobileMarcacaoController — W28-8 endpoint POST /api/v1/ponto/marcacao-mobile autenticado Sanctum token per-funcionario (escopo ponto:marcar). Recebe selfie+lat/lng+device_uuid; delega a MobileMarcacaoService. PII LGPD: selfie_base64 NUNCA logado."
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
  - /pontowr2/* (legacy preservada na Fase 3.7 PR-2 — rename PHP-only)
drift_alerts: []
---

# Modules/Ponto — Ponto eletrônico (ex-PontoWr2)

## Missão

Ponto eletrônico Portaria 671/2021. Renomeado de PontoWr2 em Fase 3.7 PR-2 (2026-05-06) — rename PHP-only. URLs `/pontowr2/*`, permissions `pontowr2.*`, config keys e lang dir mantidas legacy.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-2: rename PHP-only PontoWr2→Ponto. URLs/permissions/config legacy mantidas.
