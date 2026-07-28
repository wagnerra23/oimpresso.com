---
id: reference-legacy-audit-modulos-datacontroller-2026-04-26
name: Audit DataController/Install em todos os 32 módulos (2026-04-26)
description: 9 módulos críticos sem DataController + 8 sem user_permissions descobertos quando Jana não aparecia no menu; tabela completa de status por módulo
type: reference
originSessionId: 866e50c8-744a-42e4-8e79-7470bb472801
---
Auditoria completa em 2026-04-26 quando Wagner reportou que Jana não aparecia no menu pós-install. Achado: o problema era arquitetural — vários módulos têm InstallController (ADR 0023) mas **não têm DataController**, então invisíveis pra UltimatePOS core (middleware `AdminSidebarMenu` que invoca `Modules\<Nome>\Http\Controllers\DataController@modifyAdminMenu`).

## Estado por módulo (2026-04-26 13:00 BRT)

**✅ OK (15 módulos — DataController + user_permissions + modifyAdminMenu)**:
Accounting · AiAssistance · AssetManagement · Crm · Essentials · Financeiro · Grow · Manufacturing · MemCofre · PontoWr2 · Project · Repair · Spreadsheet · Superadmin · Woocommerce

**🔴 Críticos sem DataController (9 módulos)**: Jana · Boleto · Dashboard · Fiscal · IProduction · LaravelAI · NfeBrasil · RecurringBilling · Writebot

**🟡 Parciais sem user_permissions (8 módulos — têm menu mas Spatie não filtra)**: BI · Chat · Cms · Connector · Help · Jana · Officeimpresso · ProductCatalogue

## Why

`BaseModuleInstallController` (ADR 0023, pattern 1-clique) só faz `module:migrate` + `module:publish` + `System::addProperty('<key>_version')`. **Não cria menu, não cria Spatie permissions**. O contrato menu+perm é via `DataController` invocado em runtime pelo core do UltimatePOS.

20 módulos foram refatorados pra extender `BaseModuleInstallController` em 2026-04-25 — mas a refatoração só padronizou install, não validou que cada módulo tinha DataController correspondente.

## How to apply

- **Antes de declarar módulo como "instalado/funcionando"**: SEMPRE checar `Modules/<Nome>/Http/Controllers/DataController.php` existe E tem `user_permissions()` E `modifyAdminMenu()`. Sem isso, módulo invisível mesmo com System property setada.
- **Receita pra criar DataController novo**: usar PontoWr2 ou Crm como template (eles são os mais limpos pós-refactor 2026-04-25).
- **Permissions Spatie**: `user_permissions()` no DataController só DECLARA pro UltimatePOS UI; ainda precisa SEEDER (`Permission::firstOrCreate(['name' => 'x.y.z', 'guard_name' => 'web'])`) pra Spatie reconhecer no `can:'x.y.z'`.
- **Wire seeder no Install**: sobrescrever `postInstallCommand()` no `InstallController` retornando `'<modulo>:seed-permissions'` (Artisan command custom). Pattern do Financeiro.
- **Audit periódico**: rodar shell de auditoria do bash-loop em `memory/audits/2026-04-26-modules-datacontroller.md` (ou pedir pro Claude rodar) toda vez que adicionar módulo novo.

## Receita de auditoria

```bash
for m in Modules/*/; do
  name=$(basename "$m")
  dc="$m/Http/Controllers/DataController.php"
  ic="$m/Http/Controllers/InstallController.php"
  has_dc="-"; has_perms="-"; has_menu="-"; has_inst="-"
  [ -f "$dc" ] && has_dc="✓"
  [ -f "$ic" ] && has_inst="✓"
  if [ -f "$dc" ]; then
    grep -q "function user_permissions" "$dc" 2>/dev/null && has_perms="✓"
    grep -q "function modifyAdminMenu" "$dc" 2>/dev/null && has_menu="✓"
  fi
  printf "%-22s DC:%s perm:%s menu:%s install:%s\n" "$name" "$has_dc" "$has_perms" "$has_menu" "$has_inst"
done
```

## Status fix (2026-04-26)

Wagner pediu "conserte todos em paralelo" — fix em curso. Atualizar este memo quando completar.
