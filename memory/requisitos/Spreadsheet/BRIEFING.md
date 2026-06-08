# BRIEFING — Modules/Spreadsheet

> **Estado:** 🟡 legado UltimatePOS, pouco uso, manutenção bug-fix only | **Atualizado:** 2026-05-17 (Wave 26 polish 74 → ≥85 +11pp · D1 LogsActivity + D6 Controller DI canon + D4 Service contract preservado) | **Owner:** sem owner ativo

### Wave 26 polish (2026-05-17) — saturation 74 → ≥85 (+11pp)

- **D1 Entities trait:** test `Tests/Feature/Wave26SpreadsheetSaturationTest.php` (~22 cenários):
  - `Spreadsheet` + `SpreadsheetShare` usam `LogsActivity` Spatie (audit trail D7.b append-only)
  - `getActivitylogOptions` canon: `logAll() + logOnlyDirty() + dontSubmitEmptyLogs()`
  - Table custom canon (`sheet_spreadsheets` + `sheet_spreadsheet_shares`)
  - `sheet_data` cast array; `shares()` hasMany via `sheet_spreadsheet_id`; $guarded = [id]
- **D1 cross-tenant guard preservar** (Wave 18 baseline): `sheet_spreadsheets.business_id` existe (ADR 0093); query `where(business_id)` manual no Controller isola biz=1 vs biz=99
- **D6 Controller:** `SpreadsheetController` existe + injeta `SpreadsheetService` via DI (Wave 18 D4); ACL canon (superadmin || hasPermission)
- **D7 LGPD:** Config/retention.php declara ≥2 entities canon (`sheet_spreadsheets` 1825d + `sheet_spreadsheet_shares` 1825d — janela fiscal Brasil 5y)
- **D4 Service contract preservado:** 6 métodos canon (create/update/delete/resolveNotifyableUsers/listForUser/getForUser); ≥6 spans `OtelHelper::spanBiz`; bizId Tier 0 obrigatório em CRUD; listForUser retorna LengthAwarePaginator; getForUser fail-secure nullable
- **D3 CHANGELOG + BRIEFING (este entry)** Wave 26



## O que é

Planilhas web colaborativas inline dentro do oimpresso. Usuário cria planilha, edita células num grid JS, compartilha via link público read-only ou embed iframe.

Herdado do scaffold UltimatePOS v6 (lib legada de spreadsheet JS). Em produção, **pouco usado** — clientes preferem Google Sheets pra colaboração externa.

## Por que existe

Originalmente UltimatePOS embarcou planilha pra cobrir caso de uso "exportar relatório pra Excel sem sair do ERP". Wagner mantém porque:
- Alguns clientes legacy têm dados ali
- Custo de migração + deprecação > custo de manutenção bug-fix
- Compat de scaffold (`Modules/Spreadsheet/` referenciado por outros módulos via DataController side menu)

## Capacidades hoje

- ✅ CRUD planilhas (`sheet_spreadsheets`)
- ✅ Share via link público com token UUID + expiração
- ✅ Permissions read/edit por share
- ✅ Embed iframe (rota dedicada com CSP relaxada)
- ✅ Notificação `SpreadsheetShared` ao convidado
- ✅ Organização por folder (`folder_id` opcional)
- ✅ Multi-tenant via global scope

## Diferencial vs concorrentes

- Vs Google Sheets/Excel Online: **perde em tudo** — Wagner não compete aqui
- Vantagem única: planilha vive dentro do próprio ERP, acesso integrado ao mesmo banco oimpresso (cliente não precisa SSO externo)

## Gaps reconhecidos

- 🟡 Lib JS legada (não-Sheets) — sem fórmulas avançadas/charts
- 🟡 Sem autosave colaborativo real-time (multi-user simultâneo gera conflito)
- 🟡 Sem versionamento (overwrite total)
- 🟡 Blade não migrado MWART — P3, não vale investir
- 🟡 Sem export Excel nativo (XLSX) — só JSON
- 🟡 LGPD: tokens de share não têm rate-limit no enumeration

## Estado de testes (Wave B + Wave 18)

- `Tests/Feature/MultiTenantIsolationTest.php`
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`
- `Tests/Feature/ArchitectureTest.php`
- `Tests/Feature/ObservabilityTest.php` (Wave 18 D8 — contract OTel SpreadsheetService)

## Wave 18 (2026-05-16) — D4 service + D8 observability

- SpreadsheetService: 2 métodos novos `listForUser()` + `getForUser()` (D4 service layer canônica) — extrai critério de "minhas + compartilhadas" do Controller pra service testável + instrumentado OtelHelper canonico
- ObservabilityTest contract garante todos 6 métodos público instrumentados com `spanBiz('spreadsheet.*')`
- Spans novos: `spreadsheet.list_for_user`, `spreadsheet.get_for_user`

## Decisões relacionadas

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) — Padrão modular

## Próximo passo sugerido

**Não investir em features novas.** Quando precisar mexer:
1. Avaliar se a feature não cabe melhor sugerir Google Sheets pro cliente
2. Se for bug crítico de segurança (token enumeration, XSS no embed), priorizar fix
3. Caso surja decisão de deprecar, abrir ADR + plano de migração de dados existentes
