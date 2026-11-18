---
id: requisitos-officeimpresso-briefing
module: Officeimpresso
status: parcial
updated_at: '2026-11-18'
---

# BRIEFING — Officeimpresso

O Officeimpresso administra o **licenciamento do desktop legado** (WR Comercial / WR Sistemas, Delphi + Firebird): quais máquinas de cada cliente podem rodar o executável, quais empresas estão bloqueadas, que versão o desktop deve ter, e o histórico de cada contato do desktop com o servidor. Também guarda o importador PHP do Firebird. Não é produto novo: é meio de transição, descomissionável quando o último cliente sair do Delphi.

O fluxo ponta a ponta (token → registro no Connector → liberação → `S`/`N` → bloqueio manual ou por cobrança → log → importação) está no [SDD do licenciamento](SDD-licenciamento-delphi-v1.0.md).

**Estado:** parcial. Licenciamento e log em produção, com as telas de administração em React; o importador PHP não é confiável fora de `--dry-run`.

## Capacidades no código (`origin/main`, 2026-11-18)
- **Telas React** (`Resources/js/Pages/Officeimpresso/`), cada uma com charter, casos e RUNBOOK:
  - `Licencas/Index`: lista de máquinas, bloquear e liberar com motivo. Cutover em 2026-10-06 (#8783), sem flag e sem Blade.
  - `Empresas/Index`: empresas licenciadas, com busca. Cutover em 2026-10-27 (#10035), sem flag e sem Blade.
  - `Empresa/Show`: ficha da empresa, edita versão e caminho do banco, bloqueia a empresa e as máquinas. Flag `useV2OfficeimpressoEmpresa` com padrão ligado; o Blade `computadores` segue no repo como rota de fuga.
  - `Logs/Index` e `Logs/Timeline`: o que cada máquina fez. Flag `useV2OfficeimpressoLogs` ligada para todas as empresas desde 2026-10-14 (#9412); os Blades seguem no repo.
  - `Clientes/Index`: clients OAuth do desktop, atrás da flag `useV2OfficeimpressoClientes`, que não está nos padrões do `FeatureFlagService`; sem ligar, a Blade serve.
- **Log de acesso** (`licenca_log`): login no Passport (`LogPassportAccessToken`), toda chamada do desktop à API do Connector (`LogDelphiAccess`, middleware `log.delphi`), a API própria do módulo (`LogDesktopAccess`), o evento opt-in `POST /api/officeimpresso/audit` e as ações do operador (`admin_action`). PII redigida antes de gravar.
- **Acesso:** `superadmin` ou as permissões delegáveis `officeimpresso.*`, que desde 2026-10-01 só valem para usuário da empresa operadora (`Services/AcessoOperador.php`). A delegação ao suporte é de 2026-07-30.
- **Comandos** (nenhum agendado): `officeimpresso:health`, `licenca-log:parse`, `officeimpresso:inspect-api`, `officeimpresso:alinhar-pacotes-catalogo`, `officeimpresso:import`.
- **Catálogo:** as rotas antigas de catálogo e QR redirecionam para o `ProductCatalogue` desde 2026-10-01.

## O que não está pronto
- **Importador PHP** (`officeimpresso:import`): sem `pdo_firebird` ou sem `--source-fb` cai em modo mock e grava fixtures fora do `--dry-run`; o contador de migrados soma insert que falhou. As migrações de cliente usam os scripts Python de `scripts/legacy-migration/` ([ADR 0203](../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)). Lote L2 da auditoria de 2026-11-05.
- **Cutover que falta:** a ficha da empresa e as duas telas de Logs ainda têm flag e Blade no repo; Clientes ainda é servida pela Blade.
- **Decisões [W] pendentes:** a [ADR 0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) ("superadmin exclusivo") segue aceita e sem sucessora, enquanto o código delega; e o expurgo do `licenca_log` descrito em `Config/retention.php` e na CAPTERRA esbarra na lápide §5 de 2026-07-27 (não há comando que o execute).

## Dependências / integrações
- **Modules/Connector:** é a API que o desktop chama (`processa-dados-cliente`, `oimpresso/registrar`, `check-update`). Ela grava em `licenca_computador` e `licenca_log`, tabelas criadas pelas migrations deste módulo, e usa o middleware `log.delphi`, que só o provider deste módulo registra.
- **Modules/Superadmin:** os listeners de cobrança ligam e desligam `business.officeimpresso_bloqueado`; o desktop sente no próximo registro.
- **Laravel Passport** (token do desktop), **PiiRedactor** (LGPD), multi-tenant `business_id` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Firebird** do cliente, só leitura: pelo PHP (`FirebirdConnector`) ou pelos scripts Python.

**SPEC:** [SPEC.md](SPEC.md) · **SDD:** [SDD-licenciamento-delphi-v1.0.md](SDD-licenciamento-delphi-v1.0.md) · **Escopo:** [SCOPE.md](SCOPE.md)

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** parcial. **Fonte:** código em `origin/main` lido em 2026-11-18 + auditoria do módulo de 2026-11-05 (lote L5).
