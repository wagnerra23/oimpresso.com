# BRIEFING — Officeimpresso

Officeimpresso é a **bridge legacy** entre o ERP desktop Delphi/Firebird histórico (**WR Comercial / WR Sistemas**) e o oimpresso Laravel, no contexto da migração "Martinho". Na prática, o que está em produção hoje é o **licenciamento e auditoria de máquinas desktop** dos clientes legacy: controla licenças por computador (HD/processador/versão), bloqueio/desbloqueio, e registra acessos do Delphi via Passport tokens. É módulo **superadmin-only** (Wagner), sem produto novo — meio de transição, descomissionável quando o último cliente sair do Delphi (ADR 0136/0137).

**Estado:** parcial. Licenciamento desktop = ativo em prod; importador Firebird PHP = construído mas mock-only (nunca rodou live).

## Capacidades REAIS (no código)
- **Licenciamento desktop legacy** (`LicencaComputadorController` + `LicencaService` + entidade `Licenca_Computador`): CRUD, toggle-block por máquina, bulk-revoke (cap 100), config de empresa. Rotas web `/officeimpresso/*`. Views Blade (14 telas) ainda legacy — migração React documentada mas **não executada**.
- **Auditoria/log append-only** (`LicencaLog` + `LicencaAuditService` + `ParseLicencaLogCommand` + `InspectDelphiApiCommand`): registra acessos Delphi/Passport, parseia `laravel.log` (idempotente), retention LGPD. Endpoint `POST /api/officeimpresso/audit` (opt-in Delphi), PiiRedactor.
- **Health check** (`officeimpresso:health`): tabelas presentes, pings desktop 24h, licenças bloqueadas.
- **Importador Firebird→oimpresso** (`officeimpresso:import` + `OfficeimpressoImporterService` + `FirebirdConnector`): mapeia 6 tabelas (CLIENTES→contacts, PRODUTOS→products, VENDAS→transactions, LICENCA_COMPUTADOR, FINANCEIRO→fin_titulos, NOTA_FISCAL→nfe_emissoes), read-only one-way (ADR 0019), idempotente por `legacy_id`. **CAVEAT:** roda contra **mocks** — `pdo_firebird` ausente cai em `:mock:` automático; validado só em Pest. A migração Martinho REAL de prod é feita por **scripts Python separados** (`scripts/legacy-migration/import-*.py`), não por este comando PHP.

## PLANEJADAS (só SPEC/RUNBOOK, não construído)
- Importador PHP rodando **live** contra Firebird real (precisa ext `pdo_firebird` — hoje mock).
- Migração das 14 telas Blade → Inertia/React (RUNBOOK existe, execução não).
- Onboarding wizard, dashboard de saúde, webhooks/API REST de sync (gaps da CAPTERRA-FICHA).

## Dependências / integrações reais
- **Firebird/Delphi WR Comercial** (`.fdb`, ISO-8859-1, read-only) — via PHP é mock; via Python é o canal real de prod.
- **Modules/Connector** (sync legacy→Laravel, ADR 0137), **Modules/Financeiro** (`fin_titulos`), **Modules/NfeBrasil** (`nfe_emissoes`) — alvos do import.
- **Laravel Passport** (auth desktop), **Jana/PiiRedactor** (LGPD), multi-tenant `business_id` (ADR 0093).

**SPEC:** [SPEC.md](SPEC.md)

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** parcial (licenciamento desktop ativo em prod · importador Firebird PHP mock-only · migração React e import live planejados). **Fonte:** código real `Modules/Officeimpresso/` + `scripts/legacy-migration/*.py`, verificado 2026-06-15.
