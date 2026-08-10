# Modules/Officeimpresso

> Bridge sincronização desktop Delphi legacy (WR Sistemas) ↔ Laravel/UltimatePOS.
> **Status:** Cross-cutting **internal_governance_active** (Wagner uso diário — ADR 0159).
> **Tier 0:** Multi-tenant `business_id` global scope; PII redactor em audit log.

## Por que existe

26 anos de Office Impresso legacy Delphi → ~15+ clientes ativos sincronizam pra Hostinger. Sem este módulo, o ERP novo (oimpresso 6.7) não enxergaria essas empresas e bloquearia migração gradual.

Drift detection + audit trail ([ADR 0021](../../memory/decisions/0021-officeimpresso-connector-endpoints-restaurados.md)) é hoje a infraestrutura crítica que sustenta o piloto de migração Delphi → React.

## Cliente piloto

- **Cross-cutting interno** — Wagner usa daily pra inspecionar drift do Delphi
- 6 OfficeImpresso saudáveis sinalizados (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart) — futuros candidatos Modules/ComunicacaoVisual

## Journey real biz=1 (Wagner dev)

| Passo | Onde | Resultado esperado |
|-------|------|-------------------|
| 1. Delphi POST `/api/officeimpresso/audit` com `{event:"login_success"}` | `AuditController` | `LicencaLog` persistido + PII redacted via `PiiRedactor` |
| 2. Wagner abre `/superadmin/licenca-computador` | `LicencaComputadorController` | Tabela licenças biz=1 listadas via `LicencaService` |
| 3. Wagner clica "bloquear máquina X" | `LicencaService::alternarBloqueio` | Toggle `bloqueado` + span OTel `officeimpresso.licenca.alternar_bloqueio` |
| 4. Cron diário `mcp:brief-generate` lê drift via `LicencaLog` | DB → MCP table | Brief diário Wagner mostra clientes saudáveis × silenciados |
| 5. Endpoint OAuth/token recebe payload com `hd` (serial HD) | `LogPassportAccessToken` | Match exato `licenca_computador.hd` (roadmap) |

## Estrutura

```
Modules/Officeimpresso/
├── Config/                # retention.php (LGPD)
├── Console/Commands/      # licenca-log:retention-purge (futuro)
├── Database/Migrations/   # licenca_logs + licenca_computador
├── Entities/
│   ├── LicencaLog.php     # audit append-only
│   └── Licenca_Computador.php
├── Http/Controllers/
│   ├── AuditController.php           # POST /api/officeimpresso/audit
│   └── LicencaComputadorController.php
├── Services/
│   ├── LicencaService.php       # Wave 16 D4 — CRUD + bloqueio com OTel spans
│   └── LicencaAuditService.php  # Wave 16 D4 — PII redaction defense in depth
├── Routes/                # api.php (Delphi) + web.php (admin Inertia)
└── Tests/Feature/         # ArchitectureTest, LgpdComplianceTest, MultiTenantIsolationTest, ScaffoldTest, SmokeRoutesTest
```

## LGPD (ADR 0094 §4 + Wave 10 D7.c)

Retention por evento (`module.json.retention_days`):

| Evento | Janela | Justificativa |
|--------|--------|---------------|
| `api_call`, `error_logs` | 365d | Debug Delphi |
| `login_success`, `login_error` | 730d | Auditoria acesso usuário |
| `admin_action` (block/unblock/businessupdate) | 2555d (7a) | Audit trail legal CC Art. 206 |
| `licenca_computador inativa` | 1825d (5a) | Suporte vitalício legacy |

## Observabilidade

Spans canon `officeimpresso.*` (ver `Services/LicencaService.php` + `Services/LicencaAuditService.php`):
- `officeimpresso.licenca.{listar,buscar,criar,atualizar,remover,alternar_bloqueio}`
- `officeimpresso.empresa.{atualizar,alternar_bloqueio,listar_com_desktop}`
- `officeimpresso.licenca_audit.registrar`

Zero-cost quando `otel.enabled=false` (D9.a — ADR 0155).

## Referências

- SPEC: `memory/requisitos/Officeimpresso/SPEC.md`
- Proposta vs Mubsys: `memory/requisitos/Officeimpresso/PROPOSTA-COMERCIAL-vs-mubsys.md`
- Recuperação on-prem: `memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md`
- Migração React: `memory/requisitos/Officeimpresso/RUNBOOK-migracao-react.md`
- Schema Firebird legacy: `memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md`
- ADRs: [0021](../../memory/decisions/0021-officeimpresso-connector-endpoints-restaurados.md), [0159](../../memory/decisions/0159-modules-internal-governance-active.md)
