# OBSERVABILITY — Sells (núcleo UltimatePOS, não é módulo)

> Declaração canônica de pontos de hook OTel (D9.a Observability v3 — 2026-05-16).
> Estratégia leve: documenta superfície de instrumentação SEM código novo. Quando SDK OTel full subir no CT 100 (`OTEL_FULL_SDK=true` — ver `config/otel.php`), services e use-cases serão envolvidos via decorator/middleware.

## Spans canônicos planejados — FSM Pipeline ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))

Sells não tem Services próprios (lógica vive em `app/Domain/Fsm/`). Spans planejados ao redor do `ExecuteStageActionService`:

| Componente / Método | Span name | Atributos obrigatórios | Trigger |
|---|---|---|---|
| `ExecuteStageActionService::execute()` | `sells.fsm.execute_action` | `business_id`, `subject.type` (Transaction/JobSheet), `subject.id`, `action.key`, `stage.from`, `stage.to`, `user.id`, `duration_ms` | Cada transição FSM |
| `ExecuteStageActionService` side-effect: `ReservarEstoque` | `sells.estoque.reservar` | `business_id`, `transaction_id`, `count.linhas`, `locations.count` | Stage `quote_approved` → `reserved` |
| `ExecuteStageActionService` side-effect: `ConsumirEstoque` | `sells.estoque.consumir` | `business_id`, `transaction_id`, `count.linhas` | Stage `picked` → `shipped` |
| `CancelarVendaCascade` (orquestra) | `sells.venda.cancelar_cascade` | `business_id`, `transaction_id`, `count.callbacks_externos` (NFe/Asaas/Inter) | Stage `cancelled` |
| Webhook NFe SEFAZ cancel | `sells.nfe.cancelar_sefaz` | `business_id`, `nfe_id`, `protocolo`, `http.status`, `sefaz.codigo_retorno` | Side-effect cancel |
| `RefundCobrancaAsaasJob` | `sells.refund.asaas` | `business_id`, `cobranca_id`, `http.status`, `flag.enabled` (`ASAAS_REFUND_ENABLED`) | Side-effect cancel |

## Princípios Tier 0

- **Zero-cost quando driver=null** — wrapper checa `config('otel.enabled')`
- **business_id SEMPRE atributo** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **FSM canon LIVE biz=1** desde 2026-05-12 — spans NÃO podem mudar comportamento; só observar
- **PII redacted** — cliente_id OK, nome/cpf/email JAMAIS em raw
- **Audit log preservado** — `sale_stage_history` é fonte canônica append-only ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)); spans OTel são COMPLEMENTO para latência/erros, NÃO substitutos

## Padrão de hook (quando ativar)

```php
// Em app/Domain/Fsm/Services/ExecuteStageActionService.php (futuro)
use Modules\Jana\Services\Memoria\Telemetry\RetrievalSpan;

public function execute($subject, string $actionKey, $user, array $payload = []): mixed
{
    $span = new RetrievalSpan('sells.fsm.execute_action', null, [
        'business_id' => $subject->business_id,
        'subject.type' => class_basename($subject),
        'subject.id' => $subject->id,
        'action.key' => $actionKey,
        'stage.from' => $subject->current_stage_id,
        'user.id' => $user->id,
    ]);
    try {
        $result = /* lógica existente — flag singleton + UPDATE FSM */;
        $span->setAttribute('stage.to', $subject->refresh()->current_stage_id);
        $span->setStatus('ok');
        return $result;
    } catch (\Throwable $e) {
        $span->setStatus('error', $e->getMessage());
        throw $e;
    } finally {
        $span->end();
    }
}
```

## Refs
- [config/otel.php](../../../config/otel.php)
- [app/Domain/Fsm/](../../../app/Domain/Fsm/) — pipeline canônico
- ADR canon: 0129 (FSM custom), 0143 (FSM LIVE prod), 0093 (multi-tenant)
