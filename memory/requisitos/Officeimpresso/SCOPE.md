---
module: Officeimpresso
purpose: "Administração do licenciamento do Office Impresso desktop (Delphi): máquinas, empresas bloqueadas, versão exigida, log de acesso (licenca_log) e clients OAuth Passport do desktop, por superadmin ou por permissão delegada ao suporte da empresa operadora. A API que o desktop chama para registrar e verificar licença é do Modules/Connector; este módulo cria as tabelas que ela grava e registra o middleware log.delphi que ela usa. API própria pequena (ping e POST /audit). Inclui o importador Firebird do WR Comercial legado."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "AuditController"
  - "ClientController"
  - "DataController"
  - "InstallController"
  - "LicencaComputadorController"
  - "LicencaLogController"
  - "OfficeimpressoController"
not_contains:
  - "Handshake de licença do desktop (processa-dados-cliente, salvar-cliente, salvar-equipamento, oimpresso/registrar, check-update) → Modules/Connector — ele grava em licenca_computador e licenca_log, criadas pelas migrations deste módulo, e depende do alias de middleware log.delphi, registrado só no OfficeimpressoServiceProvider"
  - "Bloqueio automático por cobrança vencida (gravação de business.officeimpresso_bloqueado) → Modules/Superadmin, nos listeners OnCobrancaVencidaBloqueaSubscription e OnCobrancaPagaUpdateSubscription — este módulo só administra o flag pela tela"
  - "Catálogo de produtos e QR → Modules/ProductCatalogue (as rotas antigas daqui redirecionam)"
  - "Migração Firebird usada nos clientes → scripts/legacy-migration/*.py (ADR 0203); o importador PHP deste módulo é o caminho secundário"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: officeimpresso.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /officeimpresso/*
  - /api/officeimpresso/*
drift_alerts: []
---

# Modules/Officeimpresso

## Missão

Administrar o licenciamento do Office Impresso desktop: quem pode rodar, quem está bloqueado e o que cada máquina fez. Acesso por `superadmin` ou pelas permissões `officeimpresso.*`, que só valem para usuário da empresa operadora (`Modules/Officeimpresso/Services/AcessoOperador.php`, desde 2026-10-01). Até 2026-07-30 o módulo era só do superadmin ([ADR 0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md)); a ADR segue aceita e sem sucessora, e reconciliá-la com a delegação é decisão [W] pendente.

O fluxo ponta a ponta está no [SDD do licenciamento](SDD-licenciamento-delphi-v1.0.md).

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **v1.1.0** (2026-11-18) — Missão sem "Superadmin-only" (a delegação é de 2026-07-30); `purpose` e `not_contains` declaram as fronteiras com Connector (handshake, tabelas, `log.delphi`), Superadmin (bloqueio por cobrança), ProductCatalogue e os scripts Python. Lote L5 da auditoria de 2026-11-05.
