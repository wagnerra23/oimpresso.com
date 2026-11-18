# Modules/Officeimpresso

> Administração do licenciamento do desktop Delphi legado (WR Sistemas / Office Impresso): máquinas, empresas bloqueadas, versão exigida, log de acesso e clients OAuth.
> **Tier 0:** multi-tenant `business_id` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)); PII redigida antes de gravar no log.
> **Fluxo ponta a ponta:** [SDD do licenciamento](SDD-licenciamento-delphi-v1.0.md).

## Por que existe

Os clientes que ainda rodam o Office Impresso desktop precisam de licença por máquina. O desktop chama a API do `Modules/Connector` ao abrir; este módulo guarda as máquinas (`licenca_computador`) e o histórico (`licenca_log`), e dá ao operador as telas para liberar e bloquear. Sem ele, máquina nova não seria liberada e cliente inadimplente não seria bloqueado no desktop.

## Caminho real de uma máquina

Lido do código em 2026-11-18; detalhe e linhas no SDD.

| Passo | Onde | O que acontece |
|-------|------|----------------|
| 1. O desktop pede token (`POST /oauth/token`, password grant) | Passport + `Listeners/LogPassportAccessToken.php` | token emitido; `login_success` em `licenca_log` |
| 2. O desktop registra a máquina (`POST /connector/api/processa-dados-cliente` ou `/connector/api/oimpresso/registrar`) | `Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php` / `OImpressoRegistroController.php` | máquina nova nasce bloqueada; resposta `N` |
| 3. O operador libera em `/officeimpresso/licenca_computador` | `Http/Controllers/LicencaComputadorController.php::toggleBlock` → `Services/LicencaService.php::alternarBloqueio` | `bloqueado = false`; motivo do operador em `licenca_log` (`admin_action`) |
| 4. A cada abertura o desktop repete o registro | Connector | `S` ou `N;<motivo>`; toda chamada vira `api_call` em `licenca_log` (`Http/Middleware/LogDelphiAccess.php`) |
| 5. Cobrança da licença vence | `Modules/Superadmin/Listeners/OnCobrancaVencidaBloqueaSubscription.php` | `business.officeimpresso_bloqueado = true`; o desktop recebe `N` no próximo registro |

## Telas

| Tela | Rota | RUNBOOK |
|---|---|---|
| `Licencas/Index` | `/officeimpresso/licenca_computador` | [RUNBOOK-licencas](RUNBOOK-licencas.md) |
| `Empresas/Index` | `/officeimpresso/businessall` | [RUNBOOK-empresas](RUNBOOK-empresas.md) |
| `Empresa/Show` | `/officeimpresso/computadores` · `/officeimpresso/licenca_computado/licencas/{id}` | [RUNBOOK-empresa](RUNBOOK-empresa.md) |
| `Logs/Index` · `Logs/Timeline` | `/officeimpresso/licenca_log` · `/officeimpresso/licenca_log/timeline/{licenca_id}` | [RUNBOOK-logs](RUNBOOK-logs.md) |
| `Clientes/Index` | `/officeimpresso/client` | [RUNBOOK-clientes](RUNBOOK-clientes.md) |

## Estrutura

Os inventários vivos são derivados: [SUPERFICIE.md](SUPERFICIE.md) (`node scripts/governance/module-surface.mjs Officeimpresso`) e [_STATUS-GENERATED.md](_STATUS-GENERATED.md) (`node scripts/governance/requisitos-status.mjs Officeimpresso`). O resumo abaixo é só orientação.

```
Modules/Officeimpresso/
├── Config/                # config.php; retention.php (ver LGPD abaixo)
├── Console/               # licenca-log:parse, officeimpresso:inspect-api
│   └── Commands/          # officeimpresso:health, officeimpresso:import, officeimpresso:alinhar-pacotes-catalogo
├── Database/Migrations/   # licenca_computador, licenca_log
├── Entities/              # Licenca_Computador, LicencaLog
├── Http/Controllers/      # Audit, Client, Data, Install, LicencaComputador, LicencaLog, Officeimpresso
├── Http/Middleware/       # LogDelphiAccess (alias log.delphi), LogDesktopAccess (alias log.desktop)
├── Listeners/             # LogPassportAccessToken
├── Resources/js/Pages/    # as 6 telas React
├── Services/              # LicencaService, LicencaAuditService, AcessoOperador, FirebirdImporter/
├── Routes/                # api.php (ping + /audit) · web.php (telas)
└── Tests/Feature/         # lista: git ls-files 'Modules/Officeimpresso/Tests/Feature/*.php'
```

Nenhum comando está agendado; todos rodam à mão.

## LGPD

`module.json` (`retention_days`) e `Config/retention.php` descrevem janelas de retenção por evento e um expurgo do `licenca_log`. **Não existe comando que execute esse expurgo**, e as duas fontes divergem nas janelas. A proposta esbarra na lápide §5 de 2026-07-27 de `memory/proibicoes.md` (decisão [W]: num ERP não se apaga PII). Se o log técnico de acesso do desktop entra nessa lápide é decisão [W] em aberto; até lá, nada aqui deve ser lido como plano de expurgo.

## Observabilidade

Spans `officeimpresso.*` em `Services/LicencaService.php` e `Services/LicencaAuditService.php`; os dois endpoints de registro do Connector têm span próprio (`connector.delphi.*`). Zero custo quando `otel.enabled=false`.

## Referências

- [SPEC](SPEC.md) · [SDD](SDD-licenciamento-delphi-v1.0.md) · [BRIEFING](BRIEFING.md) · [SCOPE](SCOPE.md) · [CHANGELOG](CHANGELOG.md)
- [Proposta vs Mubsys](PROPOSTA-COMERCIAL-vs-mubsys.md) · [Recuperação on-prem](RUNBOOK-recuperacao-on-prem.md) · [Migração React](RUNBOOK-migracao-react.md) · [Schema Firebird](OFFICEIMPRESSO-FIREBIRD-SCHEMA.md)
- ADRs: [0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) · [0018](../../decisions/0018-officeimpresso-log-acesso-passivo.md) · [0019](../../decisions/0019-officeimpresso-delphi-nao-autentica.md) · [0021](../../decisions/0021-officeimpresso-contrato-api-delphi.md) · [0203](../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)
- _Até 2026-11-18 este README citava a rota `/superadmin/licenca-computador` e o cron `mcp:brief-generate`, que não existem, e linkava `0021-officeimpresso-connector-endpoints-restaurados.md` e `0159-modules-internal-governance-active.md`, arquivos que não existem._
