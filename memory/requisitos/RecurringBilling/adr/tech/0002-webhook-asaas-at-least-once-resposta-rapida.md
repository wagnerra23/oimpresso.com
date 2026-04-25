# ADR TECH-0002 (RecurringBilling) · Webhook gateway: resposta rápida + processamento async

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: TECH-0001, R-RB-004

## Contexto

Gateways (Asaas, Iugu, Pagar.me, Stripe) enviam webhooks com **timeout curto** (5-10s tipicamente). Se oimpresso demora pra responder:
- Asaas reenvia até 5x em 24h
- Stripe desabilita endpoint se falha 3x consecutivas
- Receber muitos webhooks ao mesmo tempo congestiona worker síncrono

Processamento de webhook envolve:
1. Validar assinatura (~10ms)
2. Persistir em `pg_webhook_events` (~5ms)
3. **Lookup contract + invoice + cliente + business** (~50ms)
4. **Atualizar estado**: invoice paid, charge_attempt success, criar transaction core, criar fin_titulo_baixa (~200-500ms)
5. **Disparar eventos** (`InvoicePaid`, `PaymentSucceeded`) que disparam outros listeners (~100ms cascade)

Total: ~500ms-2s — fora do orçamento de 5s seguros.

## Decisão

**Resposta 200 imediata após persistir o evento; processamento em queue separada.**

```
Provider chama webhook
        ↓
  WebhookController
        ↓
  1. Valida signature (10ms)
  2. Persiste pg_webhook_events (5ms)
  3. Dispatch ProcessWebhookEvent::dispatch($row)
  4. Retorna 200  ← total ~50ms
        ↓
  (async em queue rb_webhooks)
        ↓
  ProcessWebhookEvent
        ↓
  Mapeia evento → estado interno
  Atualiza tabelas
  Dispara eventos Laravel
```

## Implementação

```php
class WebhookController {
    public function handle(Request $r, string $provider) {
        $start = microtime(true);

        if (! $this->signatureValidator->valida($r, $provider)) {
            return response('invalid signature', 401);
        }

        try {
            $row = PgWebhookEvent::create([
                'provider' => $provider,
                'event_id' => $this->extractEventId($r, $provider),
                'event_type' => $r->input('event'),
                'payload' => $r->all(),
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return response('ok already processed', 200);
        }

        ProcessWebhookEvent::dispatch($row->id);

        $elapsed = (microtime(true) - $start) * 1000;
        Log::channel('webhooks')->info("Webhook accepted in {$elapsed}ms");

        return response('ok', 200);
    }
}

class ProcessWebhookEvent implements ShouldQueue {
    public string $queue = 'rb_webhooks';
    public int $tries = 3;
    public int $backoff = 60;  // 1 min entre retries

    public function handle(int $webhookEventId): void {
        $event = PgWebhookEvent::findOrFail($webhookEventId);
        $handler = $this->resolveHandler($event->provider, $event->event_type);
        $handler->handle($event->payload);
        $event->update(['processed_at' => now()]);
    }
}
```

## Consequências

**Positivas:**
- Resposta 200 em ~50ms — provider feliz, sem retry desnecessário
- Throughput muito maior — 1k webhooks/min sem warm-up de worker
- Failure de processamento isolado: webhook foi recebido, processamento pode retentar
- Audit trail: `pg_webhook_events.received_at` vs `processed_at` mostra delay
- Provider downgrade gracefully: se queue trava, provider não sente

**Negativas:**
- Pequeno gap entre receive e process (segundos a minutos em pico) — UI tem que mostrar "estamos processando..."
- Webhook recebido mas falha no processamento: precisa retry + alerting (já tem ShouldQueue tries=3)
- Se processamento demora mais que 24h por bug, provider envia evento de novo — UNIQUE protege

## Monitoramento crítico

- **Métrica:** delay médio entre `received_at` e `processed_at` (alerta se > 60s)
- **Métrica:** % de webhooks com `processed_at = NULL` mais de 5 min (deveria ser ~0)
- **Métrica:** Time-to-200 do controller (alerta se > 200ms)
- **Falhas processamento:** notify Slack/email após 3 retries

## Tests obrigatórios

```php
test('webhook responde 200 em menos de 200ms', function () {
    $start = microtime(true);
    $resp = $this->postJson('/webhooks/asaas', $this->payload());
    $elapsed = (microtime(true) - $start) * 1000;

    $resp->assertOk();
    expect($elapsed)->toBeLessThan(200);
});

test('webhook persiste e dispatch async', function () {
    Queue::fake();
    $this->postJson('/webhooks/asaas', $this->payload());
    Queue::assertPushed(ProcessWebhookEvent::class);
    expect(PgWebhookEvent::count())->toBe(1);
});

test('processamento async atualiza invoice', function () {
    $invoice = Invoice::factory()->open()->create();
    $event = PgWebhookEvent::factory()->forInvoice($invoice)->create();

    (new ProcessWebhookEvent)->handle($event->id);

    expect($invoice->fresh()->status)->toBe('paid');
});
```

## Alternativas consideradas

- **Processar síncrono** — rejeitado: timeout, throughput baixo
- **Webhook → SQS/Redis Streams direto** — overkill: queue Laravel resolve
- **Resposta 202 Accepted** — válido mas alguns providers tratam diferente; 200 é mais seguro
- **Worker dedicado por provider** — futuro otimização; MVP roda 1 worker `rb_webhooks` que dá conta

## Referências

- TECH-0001 (idempotência)
- R-RB-004 (SPEC)
- Asaas webhooks docs (timeout 5s, at-least-once)
- Stripe webhooks best practices
