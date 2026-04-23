# Runbook · Essentials

## Problema: botão "Criar Todo" não aparece

**Sintoma**: Usuário na tela `/essentials/todo` não vê o botão "Novo".

**Causa**: Falta permissão Spatie `essentials.add_todos` no role do usuário.

**Correção**:
```bash
php artisan tinker
>>> $u = User::find($userId);
>>> $u->givePermissionTo('essentials.add_todos');
>>> $u->getAllPermissions()->pluck('name'); // verificar
```

## Problema: clock in falha com "location required"

**Sintoma**: Tenta bater ponto e tela de "Endereço" não fecha.

**Causa**: Navegador bloqueou geolocation OU HTTPS exigido.

**Correção**:
- Verificar certificado local (Herd gera `.test` com HTTPS — se expirou, `herd trust`).
- Usuário aceitar permissão no browser (cadeado → Permissões → Localização = Permitir).

## Problema: Leave aprovada mas colaborador continua marcando ponto

**Sintoma**: Colaborador de férias consegue bater ponto via UI.

**Causa**: Attendance e Leave são sistemas independentes — nenhum bloqueia o outro.

**Correção**:
- Desabilitar botão clock-in quando há leave ativa naquele dia (middleware `CheckActiveLeave`).
- Alternativa: aceitar e sinalizar na folha (flag "batido durante ausência").
- Decisão pendente — abrir ADR.

## Problema: Knowledge Base indexa artigo privado pro usuário errado

**Sintoma**: Colaborador A vê artigo marcado como "só visível pro role RH".

**Causa**: Cache de permissões Spatie desatualizado.

**Correção**:
```bash
php artisan cache:forget spatie.permission.cache
php artisan permission:cache-reset
```

## Comandos úteis

```bash
# Seed de permissões Essentials
php artisan db:seed --class=EssentialsPermissionsSeeder

# Testar permissões de um user
php artisan tinker
>>> User::find(1)->getAllPermissions()->pluck('name')->filter(fn($p) => str_starts_with($p, 'essentials'))

# Reset cache de permissões
php artisan permission:cache-reset

# Audit Essentials
php artisan docvault:audit-module Essentials --save
```
