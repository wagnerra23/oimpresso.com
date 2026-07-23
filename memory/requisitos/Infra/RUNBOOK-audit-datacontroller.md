---
title: "RUNBOOK — Audit DataController/Permissions em todos os módulos"
owner: W
status: ativo
last_validated: "2026-06-13"
---

# RUNBOOK — Audit DataController/Permissions em todos os módulos

> **Quando usar:** depois de criar/instalar módulo novo, ou quando reportarem "módulo X não aparece no menu" pós-install.

## Por que existe

[ADR 0023](../../decisions/0023-pattern-install-modulos-1-clique.md) padronizou install via `BaseModuleInstallController` — mas o `BaseModuleInstallController` **não cria menu nem Spatie permissions**. O contrato menu+perm é via `DataController` invocado em runtime pelo middleware `AdminSidebarMenu` do UltimatePOS core.

Resultado: módulo pode estar "instalado" (System property setada) mas **invisível** se faltar `DataController` com `user_permissions()` + `modifyAdminMenu()`.

Descoberto em 2026-04-26 quando Copiloto não aparecia no menu pós-install.

## Receita de auditoria

Rodar do raiz do repo (ou via SSH no Hostinger):

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

Saída esperada por módulo "saudável":
```
NomeModulo            DC:✓ perm:✓ menu:✓ install:✓
```

Qualquer `-` em `DC` ou `menu` = módulo invisível mesmo se instalado.

## Como aplicar

- **Antes de declarar módulo como "instalado/funcionando"**: SEMPRE checar `Modules/<Nome>/Http/Controllers/DataController.php` existe E tem `user_permissions()` E `modifyAdminMenu()`.
- **Pra criar DataController novo**: usar `Modules/Ponto/` ou `Modules/Crm/` como template (foram refatorados em 2026-04-25 e estão limpos).
- **Permissions Spatie**: `user_permissions()` no DataController só **DECLARA** pro UltimatePOS UI; ainda precisa SEEDER (`Permission::firstOrCreate(['name' => 'x.y.z', 'guard_name' => 'web'])`) pra Spatie reconhecer no `can:'x.y.z'` do Blade/middleware.
- **Wire seeder no Install**: sobrescrever `postInstallCommand()` no `InstallController` retornando `'<modulo>:seed-permissions'` (Artisan command custom). Pattern do `Modules/Financeiro/`.
- **Audit periódico**: rodar a receita acima toda vez que adicionar módulo novo, OU integrar em CI como sanity check.

## Refs

- [ADR 0023](../../decisions/0023-pattern-install-modulos-1-clique.md) — pattern install 1-clique
- [RUNBOOK-criar-modulo.md](RUNBOOK-criar-modulo.md) — receita pra módulo novo (já cobre o checklist)
