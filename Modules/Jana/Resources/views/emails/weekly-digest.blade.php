@component('mail::message')
# Jana — Weekly Digest

**Semana:** {{ $semana }}
**Range:** {{ $rangeInicio }} (segunda) → {{ $rangeFim }} (domingo)
**Business:** {{ $businessName }}

---

## Métricas da semana

@component('mail::table')
| Métrica | Valor |
| ------- | ----: |
| Commits | {{ $metrics['commits'] ?? 0 }} |
| PRs mergeados | {{ $metrics['prs_merged'] ?? 0 }} |
| US closed | {{ $metrics['us_closed'] ?? 0 }} |
| US criadas | {{ $metrics['us_created'] ?? 0 }} |
| ADRs novas | {{ $metrics['adrs_new'] ?? 0 }} |
| Handoffs | {{ $metrics['handoffs'] ?? 0 }} |
| Cycle progress | {{ $metrics['cycle_progress_pct'] ?? 0 }}% |
@endcomponent

---

## Digest

{!! $digestBody !!}

---

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'primary'])
Abrir Cockpit Governance
@endcomponent

Esta é uma notificação automática gerada toda segunda 09:00 BRT pelo comando
`jana:weekly-digest` (AUDITORIA-MEMORIA-2026-05-15 §D8 #6).

Para parar de receber, comente o schedule em `app/Console/Kernel.php` ou
ative `WEEKLY_DIGEST_EMAIL_ENABLED=false` no `.env`.

Obrigado,<br>
Jana — assistente IA do {{ config('app.name') }}
@endcomponent
