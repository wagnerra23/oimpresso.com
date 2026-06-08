# ADR TECH-0001 (Crm) · Follow-up notifications via Scheduler

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Follow-up tem `scheduled_at`. Vendedor precisa ser avisado quando chega a hora. Sem mecanismo, follow-ups viram órfãos.

## Decisão

Command `crm:send-follow-up-reminders` rodando a cada 15min via Laravel Scheduler. Query follow-ups com `scheduled_at <= now() AND notified_at IS NULL`, dispara Notification (email + in-app), marca `notified_at`.

## Consequências

**Positivas:**
- Baixo acoplamento — qualquer sistema que crie follow-up com scheduled_at é notificado.
- Idempotente — `notified_at` evita duplicação.

**Negativas:**
- Latência de até 15min entre o momento agendado e a notificação.

## Alternativas consideradas

- **Queue com delay por job**: dispersa timing em muitos jobs pequenos.
- **Cron externo**: quebra isolamento Laravel.
