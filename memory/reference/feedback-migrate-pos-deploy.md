---
name: Migrate manual obrigatório pós-deploy Hostinger
description: Migrations NUNCA rolam automaticamente em Hostinger pós-quick-sync.yml — sempre rodar manual após merge PR com migration
type: feedback
---
# Migrate manual obrigatório pós-deploy Hostinger

## Regra

**`php artisan migrate` NÃO é automático** em prod Hostinger. O workflow `quick-sync.yml` faz `git pull` + cache clear, mas NÃO roda `composer install` (nem `--no-dev`) NEM `migrate`. Toda PR que adicione migration exige passo manual SSH pós-merge:

```bash
ssh u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
php artisan migrate --force
```

## Por que: Como aplica

Hostinger é shared hosting (ADR 0062). `artisan migrate` precisa rodar uma vez com schema lock. Auto-migrate poderia quebrar produção sem rollback fácil — workflow optou por exigir intervenção humana.

## Sintomas quando esquecemos

- `SQLSTATE[42S22]: Unknown column 'X' in 'INSERT'` — coluna nova não existe
- `SQLSTATE[42S02]: Base table or view 'whatsapp_reminders' doesn't exist` — tabela nova não criada
- Cron scheduled jobs (`ProcessRemindersJob`, `FsmScanDriftCommand`) começam a quebrar todo hourly/daily

## Checklist pós-merge PR com migration

1. SSH Hostinger
2. `git status` (confirma main puxado pelo workflow)
3. `php artisan migrate:status | grep Pending` (lista migrations pendentes)
4. `php artisan migrate --force`
5. Se erro, rollback: `php artisan migrate:rollback --step=1`
6. Sanity check tabelas criadas via tinker
