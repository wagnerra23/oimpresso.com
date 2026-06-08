---
name: Whatsapp Permissions Spatie — bug histórico + comando register canônico
description: whatsapp.* permissions nunca foram registradas em prod (Spatie cria on-demand, ninguém atribuiu); PR #665 comando whatsapp:register-permissions resolve; UltimatePOS Spatie schema permissions GLOBAL, roles per-business (Admin#{biz})
type: reference
---
# Whatsapp Permissions Spatie em prod (descoberta crítica 2026-05-12)

## Bug raiz histórico

`whatsapp.*` permissions (`access`, `send`, `assign`, `templates.manage`, `settings.manage`, `metricas.view`) **NUNCA foram registradas na tabela `permissions` Spatie em prod**. Spatie cria permissions on-demand quando atribuídas via UI `/roles`, e ninguém em prod nunca atribuiu — então NENHUM atendente tinha permission.

Wagner via tudo porque tem **gate `whatsapp.view-all-phones`** (Admin#1 bypass). Mas Maiara/Felipe/Larissa não tinham nada.

Quando US-WA-069 (filtragem inbox per-canal via `channel_user_access`) mergeou, os atendentes que ANTES viam tudo por gate genérico passaram a falhar no filtro per-canal.

## Solução canônica (PR #665)

Comando artisan idempotente:

```bash
php artisan whatsapp:register-permissions [--business=N|all] [--with-backfill] [--dry-run]
```

O que faz:
1. `Permission::firstOrCreate(['name' => 'whatsapp.*', 'guard_name' => 'web'])` — registra as 6 permissions
2. `Role::where('name', "Admin#{$biz}")` + `$role->givePermissionTo($perms)` — atribui à role Admin de cada business
3. `--with-backfill` encadeia `whatsapp:backfill-channel-access` que grant em `channel_user_access`

## Rodado em prod 2026-05-12

```
60 business(es) processados · 354 permissions attached
2 canais biz=1 backfilled → 12 grants (6 users × 2 canais)
```

## Schema UltimatePOS Spatie

- `permissions` table: SEM `business_id` (permissions são GLOBAIS)
- `roles` table: COM `business_id` (roles per-business: `Admin#1`, `Cashier#1`, etc)
- `model_has_permissions`: user → permission direto
- `model_has_roles`: user → role
- `role_has_permissions`: role → permission

Query users com permission considera AMBOS direto E via role (UltimatePOS pattern).

## Permission definitions

Em `Modules/Whatsapp/Http/Controllers/DataController::user_permissions()` (~linha 32-42). Cada permission tem `name`, `label`, `default => false`.
