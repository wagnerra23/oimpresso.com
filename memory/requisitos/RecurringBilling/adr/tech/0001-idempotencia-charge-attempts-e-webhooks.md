# ADR TECH-0001 (RecurringBilling) · Idempotência em charge_attempts e webhooks

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: `Financeiro/adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md`, R-RB-004, R-RB-005

## Contexto

RecurringBilling tem 3 superfícies de duplicação crítica:

1. **Geração fatura** — job retry (queue worker crashou) → 2 invoices pra mesma competência
2. **Charge attempt** — frontend retry / job retry / webhook chega antes do response → 2 cobranças no cartão
3. **Webhook gateway** — provider envia "at least once" — Asaas garante 5+ retentativas em até 24h por evento

Em billing, dupla é desastre:
- Cliente debitado 2x
- Suporte tem que ressarcir
- Reputação atinge contas tenant ↔ cliente final

## Decisão

**3 chaves de idempotência distintas, cada uma com UNIQUE constraint:**

### 1. Geração fatura — `(contract_id, competencia_yyyy_mm)` UNIQUE

```sql
ALTER TABLE rb_invoices
  ADD UNIQUE KEY uk_competencia (business_id, contract_id, competencia_yyyy_mm);
```

Service:

```php
class InvoiceGeneratorService {
    public function gerar(Contract $c): Invoice {
        return Invoice::firstOrCreate(
            [
                'business_id' => $c->business_id,
                'contract_id' => $c->id,
                'competencia_yyyy_mm' => $this->competenciaPara($c->next_billing_date),
            ],
            [/* outros campos */]
        );
    }
}
```

Retry de job = no-op se já criada.

### 2. Charge attempt — `(business_id, invoice_id, attempt_number)` UNIQUE

```sql
ALTER TABLE pg_charge_attempts
  ADD UNIQUE KEY uk_attempt (business_id, invoice_id, attempt_number);
```

Service:

```php
class ChargeService {
    public function tentar(Invoice $invoice, int $attempt): ChargeAttempt {
        return DB::transaction(function () use ($invoice, $attempt) {
            $existing = ChargeAttempt::where([
                'business_id' => $invoice->business_id,
                'invoice_id' => $invoice->id,
                'attempt_number' => $attempt,
            ])->first();

            if ($existing) {
                if ($existing->status === 'processing') {
                    throw new ChargeJaEmAndamentoException();  // tem que esperar
                }
                return $existing;  // já terminou — retorna resultado
            }

            return ChargeAttempt::create([/* ... */]);
        });
    }
}
```

Frontend chama 2x rápido = no-op na 2ª.

### 3. Webhook gateway — `(provider, event_id)` UNIQUE em `pg_webhook_events`

```sql
CREATE TABLE pg_webhook_events (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(30) NOT NULL,
    event_id VARCHAR(100) NOT NULL,
    business_id INT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    received_at TIMESTAMP NOT NULL,
    processed_at TIMESTAMP NULL,
    UNIQUE KEY uk_event (provider, event_id)
);
```

Controller:

```php
class WebhookController {
    public function handle(Request $r, string $provider) {
        if (!$this->validarSignature($r, $provider)) {
            return response('invalid signature', 401);
        }

        $eventId = $this->extractEventId($r, $provider);

        try {
            $row = PgWebhookEvent::create([
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => $r->input('event'),
                'payload' => $r->all(),
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // já recebido antes — Asaas at-least-once
            return response('ok', 200);
        }

        ProcessWebhookEvent::dispatch($row);  // async
        return response('ok', 200);
    }
}
```

Asaas envia 5x = 1 processamento.

## Consequências

**Positivas:**
- Cliente nunca cobrado 2x (mesmo com bugs)
- Webhooks robustos: cliente pode resender, oimpresso aceita silenciosamente
- Retry de job é seguro
- Audit trail: cada chave aponta de onde veio a mutação

**Negativas:**
- Race condition entre `firstOrCreate` e UNIQUE: rara mas existe — `lockForUpdate` em casos críticos
- Webhook deduplicado tem que retornar 200 (não 4xx) senão provider vai retentar mais
- Precisa testar volume real (Asaas costuma enviar webhook 1-3 vezes em produção)

## Tests obrigatórios

```php
test('100 jobs concorrentes geração fatura = 1 fatura por competencia', function () {
    $contract = Contract::factory()->create();
    Bus::fake();
    for ($i = 0; $i < 100; $i++) GerarInvoiceJob::dispatch($contract);
    Bus::assertBatched(...);
    expect(Invoice::where('contract_id', $contract->id)->count())->toBe(1);
});

test('webhook Asaas mesmo event_id 5x = 1 processamento', function () {
    Bus::fake();
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/webhooks/asaas', ['id' => 'ASAAS_001', /* ... */]);
    }
    Bus::assertDispatchedCount(ProcessWebhookEvent::class, 1);
});

test('charge attempt double-click = 1 cobrança', function () {
    Http::fake();
    $invoice = Invoice::factory()->create();
    promise_all([
        ChargeService::tentar($invoice, 1),
        ChargeService::tentar($invoice, 1),
    ]);
    Http::assertSentCount(1);
});
```

## Decisões em aberto

- [ ] Webhook deduplicação: armazenar payload pra debug ou só event_id pra economia espaço?
- [ ] Charge attempt em `processing` há > 5 min: timeout automático? (corre-se risco de duplicar se gateway responder depois)
- [ ] Cleanup de `pg_webhook_events` antigos (>90 dias): mover pra archive ou só manter?

## Referências

- `Financeiro/adr/tech/0001` (mesmo princípio, contexto diferente)
- R-RB-003, R-RB-004, R-RB-005
- Stripe API idempotency (referência canônica)
- Asaas webhooks docs (at-least-once)
