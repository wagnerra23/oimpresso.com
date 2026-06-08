# Runbook · CRM

## Problema: Vendedor não recebe notificação de follow-up

**Sintoma**: Follow-up agendado pra 14h, são 15h e ninguém foi avisado.

**Causa possível**: Scheduler não está rodando OU notifications desligadas.

**Correção**:
```bash
# 1. Scheduler roda a cada minuto? (produção Hostinger)
crontab -l | grep schedule:run

# 2. Comando está na Kernel?
grep "crm:send-follow-up" app/Console/Kernel.php

# 3. Rodar manualmente pra testar
php artisan crm:send-follow-up-reminders

# 4. Verificar user tem canal ativo
>>> User::find(1)->notifications
```

## Problema: Lead convertido em Contact some do dashboard

**Sintoma**: Vendedor fecha venda, lead marcado "Won", mas desaparece.

**Causa**: Default filter esconde leads com `life_stage` = "Won" ou "Lost". É feature, não bug.

**Correção**: usar filtro "Incluir ganhos" ou "Incluir perdidos" no topo da lista.

## Problema: Permissão de lead não respeita "view_own"

**Sintoma**: Vendedor A vê leads do vendedor B mesmo sem `view_all`.

**Causa**: Controller não aplicou scope em query.

**Correção**: verificar `CrmLeadController::index` — deve ter:
```php
if (! $user->can('crm.leads.view_all')) {
    $query->where('user_id', $user->id);
}
```

## Comandos úteis

```bash
# Disparar follow-up reminders manualmente
php artisan crm:send-follow-up-reminders

# Auditar CRM
php artisan docvault:audit-module Crm --save
```
