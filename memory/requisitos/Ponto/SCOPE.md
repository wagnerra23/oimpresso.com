---
module: Ponto
purpose: "Ponto eletrônico CLT sob Portaria MTP 671/2021 — marcação append-only imutável (trigger MySQL mais override no Model; correção só por anulação) com NSR sequencial por REP e importação de AFD. Sobre isso: apuração de jornada, banco de horas, intercorrências com aprovação e espelho. Multi-tenant Tier 0."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
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
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: ponto.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /ponto/* (web · Modules/Ponto/Http/routes.php)
  - /ponto/api/* (API Passport)
  - /ponto/install/* (rotas de instalação do módulo)
drift_alerts: []
---

# Modules/Ponto — Ponto eletrônico (ex-PontoWr2)

## Missão

Ponto eletrônico Portaria 671/2021. Renomeado de PontoWr2 em Fase 3.7 PR-2 (2026-05-06) — rename PHP-only. **Config keys** (`config/pontowr2.php`, `pontowr2.ai.*`, `pontowr2.afd.*`) e **lang dir** (`pontowr2::ponto.*`) seguem legacy; as **URLs** são `/ponto/*` e as **permissions** são `ponto.*` — medido 2026-08-23 em `Http/routes.php` (3 prefixos) e `DataController::user_permissions()` (6 permissões); zero rota `/pontowr2/` e zero permission `pontowr2.` no módulo.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-2: rename PHP-only PontoWr2→Ponto. URLs/permissions/config legacy mantidas.
