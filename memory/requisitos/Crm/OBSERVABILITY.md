# OBSERVABILITY — Modules/Crm

> Declaração canônica de pontos de hook OTel (D9.a Observability v3 — 2026-05-16).
> Estratégia leve: documenta superfície de instrumentação SEM código novo. Quando SDK OTel full subir no CT 100 (`OTEL_FULL_SDK=true` — ver `config/otel.php`), services serão envolvidos via decorator/middleware sem mudar assinatura.

## Spans canônicos planejados

| Service / Método | Span name (OTel GenAI conv.) | Atributos obrigatórios | Trigger |
|---|---|---|---|
| `LeadAssignmentService::createLead()` | `crm.lead.create` | `business_id`, `crm_source`, `lifecycle_stage`, `user_id_assigned` | POST novo lead |
| `LeadAssignmentService::updateLead()` | `crm.lead.update` | `business_id`, `lead_id`, `fields_changed.count` | PATCH lead |
| `ScheduleService::criarFollowUp()` | `crm.followup.criar` | `business_id`, `lead_id`, `tipo`, `delta_dias` | Agendamento |
| `CampaignService::dispararCampanha()` | `crm.campaign.disparar` | `business_id`, `campanha_id`, `count.contatos`, `canal` (whatsapp/email) | Disparo manual/auto |
| `ContactBookingService::book()` | `crm.contact.book` | `business_id`, `contato_id`, `slot_iso` | Reserva agenda |

## Princípios Tier 0

- **Zero-cost quando driver=null** — wrapper checa `config('otel.enabled')`
- **business_id SEMPRE atributo** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **PII redacted** — nome/email/telefone do lead JAMAIS em span; usar `lead_id` ou `sha256(email)`
- **Session safety** — services CRM operam em background jobs também; business_id explícito (constructor) — nunca `session()`

## Padrão de hook (quando ativar)

```php
use Modules\Jana\Services\Memoria\Telemetry\RetrievalSpan;

$span = new RetrievalSpan('crm.lead.create', null, [
    'business_id' => $businessId,
    'crm_source' => $input['crm_source'] ?? 'unknown',
]);
try {
    $lead = CrmContact::createNewLead($input, $assignedUsers);
    $span->setAttribute('lead_id', $lead?->id);
    $span->setStatus($lead ? 'ok' : 'error');
    return $lead;
} finally {
    $span->end();
}
```

## Refs
- [config/otel.php](../../../config/otel.php)
- [Modules/Crm/Services/LeadAssignmentService.php](../../../Modules/Crm/Services/LeadAssignmentService.php)
- ADR canon: 0011 (alinhamento padrão Jana), 0093 (multi-tenant)
