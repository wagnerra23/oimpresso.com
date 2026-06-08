# Modules/PaymentGateway — Contratos públicos

> Interfaces PHP, DTOs e eventos que módulos externos podem usar. **Nada fora desta lista é API pública.** Drivers, services internos e helpers são detalhe de implementação.

Vinculado a [ADR 0170](../../memory/decisions/0170-paymentgateway-extracao-camada-cobranca.md). Versão deste contrato: **v0.1** (rascunho Onda 0 — pode mudar antes de Onda 1 fechar).

---

## 1. Contratos (interfaces)

### `PaymentGatewayContract`

API principal. Quem precisa cobrar usa só isso.

```php
<?php

namespace Modules\PaymentGateway\Contracts;

use App\Account;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Entities\Cobranca;
use Modules\PaymentGateway\Dto\CardToken;

interface PaymentGatewayContract
{
    /**
     * Seleciona a credencial de gateway vinculada a esta conta.
     * Lança PaymentGatewayException se não houver gateway ativo,
     * ou se a credencial estiver com health_status != ok.
     *
     * Idempotente — pode ser chamado várias vezes na mesma request.
     */
    public function for(Account $account): self;

    /**
     * Emite um boleto único.
     *
     * Idempotência: se já existe Cobranca com mesmo $input->idempotencyKey
     * em status emitida|paga, retorna o resultado anterior sem chamar gateway.
     *
     * @throws \Modules\PaymentGateway\Exceptions\GatewayUnavailableException quando gateway responde 5xx/timeout
     * @throws \Modules\PaymentGateway\Exceptions\InvalidPayerException       quando dados do pagador são insuficientes
     * @throws \Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException
     */
    public function emitirBoleto(EmitirCobrancaInput $input): CobrancaEmitidaResult;

    /**
     * Emite PIX cobrança.
     *
     * @param string $tipo 'cob' (imediata, sem vencimento) | 'cobv' (com vencimento)
     */
    public function emitirPix(EmitirCobrancaInput $input, string $tipo = 'cob'): CobrancaEmitidaResult;

    /**
     * Emite PIX Automático (BCB recv) — mandato recorrente.
     * Só driver bcb_pix suporta hoje (Asaas não tem; Inter está em homologação).
     *
     * @throws \Modules\PaymentGateway\Exceptions\DriverNotSupportedException
     */
    public function emitirPixAutomatico(EmitirCobrancaInput $input): CobrancaEmitidaResult;

    /**
     * Tokeniza (se ainda não) + cobra cartão.
     *
     * @throws \Modules\PaymentGateway\Exceptions\DriverNotSupportedException quando driver não tem cartão
     * @throws \Modules\PaymentGateway\Exceptions\CardDeclinedException       quando emissor recusou
     */
    public function cobrarCartao(EmitirCobrancaInput $input, CardToken $token): CobrancaEmitidaResult;

    /**
     * Cancela cobrança ainda não paga.
     * Lança se cobrança já foi paga (não é estorno — pra estorno use refund()).
     */
    public function cancelar(Cobranca $cobranca, string $motivo): void;

    /**
     * Estorna cobrança paga. Driver-dependent (PesaPal e Asaas suportam; Inter parcial).
     *
     * @throws \Modules\PaymentGateway\Exceptions\DriverNotSupportedException
     */
    public function refund(Cobranca $cobranca, ?int $valorCentavos, string $motivo): void;

    /**
     * Consulta status atualizado direto no gateway (bypass cache local).
     * Útil pra reconciliação manual ou quando webhook está atrasado.
     */
    public function consultar(Cobranca $cobranca): CobrancaStatus;
}
```

### `PaymentDriverContract`

Interface dos drivers. **Não é pública** — listada aqui só pra documentar quem implementa o quê.

```php
<?php

namespace Modules\PaymentGateway\Contracts;

interface PaymentDriverContract
{
    public function key(): string; // 'inter' | 'c6' | 'asaas' | 'bcb_pix' | 'pesapal'

    public function supports(string $tipo): bool; // 'boleto' | 'pix_cob' | 'pix_cobv' | 'pix_recv' | 'card'

    public function emitirBoleto(EmitirCobrancaInput $input, PaymentGatewayCredential $cred): CobrancaEmitidaResult;
    public function emitirPix(EmitirCobrancaInput $input, PaymentGatewayCredential $cred, string $tipo): CobrancaEmitidaResult;
    public function emitirPixAutomatico(EmitirCobrancaInput $input, PaymentGatewayCredential $cred): CobrancaEmitidaResult;
    public function cobrarCartao(EmitirCobrancaInput $input, PaymentGatewayCredential $cred, CardToken $token): CobrancaEmitidaResult;
    public function cancelar(Cobranca $cobranca, PaymentGatewayCredential $cred, string $motivo): void;
    public function refund(Cobranca $cobranca, PaymentGatewayCredential $cred, ?int $valorCentavos, string $motivo): void;
    public function consultar(Cobranca $cobranca, PaymentGatewayCredential $cred): CobrancaStatus;
    public function healthCheck(PaymentGatewayCredential $cred): DriverHealth;
    public function processWebhook(array $payload, PaymentGatewayCredential $cred): ?Cobranca;
}
```

---

## 2. DTOs

### `EmitirCobrancaInput`

```php
<?php

namespace Modules\PaymentGateway\Dto;

final class EmitirCobrancaInput
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $contactId,            // FK pra contacts (pagador)
        public readonly int $valorCentavos,
        public readonly \DateTimeImmutable $vencimento,
        public readonly string $descricao,
        public readonly string $idempotencyKey,    // ex: "sale:1234" | "invoice:5678" | "subscription_license:99" | "avulsa:uuid"
        public readonly ?string $origemType = null, // 'sale' | 'invoice' | 'subscription_license' | null
        public readonly ?int $origemId = null,
        public readonly array $multa = [],          // ['percentual' => 2.0, 'apos_dias' => 1]
        public readonly array $juros = [],          // ['percentual_dia' => 0.033]
        public readonly array $desconto = [],       // ['valor_centavos' => 1000, 'ate' => '2026-05-20']
        public readonly ?string $instrucoesPagador = null,
        public readonly ?string $callbackUrl = null,  // override do webhook padrão
        public readonly array $meta = [],            // extra fields driver-specific (ex: split de pagamento)
    ) {}
}
```

### `CobrancaEmitidaResult`

```php
<?php

namespace Modules\PaymentGateway\Dto;

final class CobrancaEmitidaResult
{
    public function __construct(
        public readonly int $cobrancaId,             // PK em cobrancas
        public readonly string $gatewayExternalId,   // ID no Inter/Asaas/BCB
        public readonly string $tipo,                // boleto | pix_cob | pix_cobv | pix_recv | card
        public readonly ?string $linhaDigitavel = null,
        public readonly ?string $codigoBarras = null,
        public readonly ?string $pixEmv = null,       // BR Code copia-e-cola
        public readonly ?string $pixQrCodePath = null, // path local do PNG do QR
        public readonly ?string $boletoPdfUrl = null,
        public readonly ?string $nossoNumero = null,
        public readonly \DateTimeImmutable $emitidaEm,
        public readonly array $payloadGateway = [],   // request/response brutos (audit)
    ) {}
}
```

### `CobrancaStatus`

```php
<?php

namespace Modules\PaymentGateway\Dto;

final class CobrancaStatus
{
    public function __construct(
        public readonly string $status,            // pending | emitida | paga | vencida | cancelada | erro
        public readonly ?\DateTimeImmutable $pagaEm = null,
        public readonly ?int $valorPagoCentavos = null,
        public readonly ?string $formaPagamento = null,
        public readonly ?string $payerCpfCnpj = null,
        public readonly array $payloadGateway = [],
    ) {}
}
```

### `CardToken`

```php
<?php

namespace Modules\PaymentGateway\Dto;

final class CardToken
{
    public function __construct(
        public readonly string $token,        // token PCI-DSS no provedor (nunca PAN bruto)
        public readonly string $brand,        // visa | master | elo | amex | hipercard
        public readonly string $lastFour,
        public readonly string $holderName,
        public readonly string $expMonth,
        public readonly string $expYear,
    ) {}
}
```

### `DriverHealth`

```php
<?php

namespace Modules\PaymentGateway\Dto;

final class DriverHealth
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $status,       // ok | degraded | down
        public readonly int $latencyMs,
        public readonly \DateTimeImmutable $checkedAt,
        public readonly ?string $errorMessage = null,
    ) {}
}
```

---

## 3. Eventos broadcast

Todos eventos têm payload:

```php
public readonly int $cobrancaId;
public readonly int $businessId;
public readonly ?string $origemType;
public readonly ?int $origemId;
public readonly \DateTimeImmutable $occurredAt;
```

Mais campos específicos por evento (abaixo). Disparados via `event()` standard Laravel — listeners registram via `EventServiceProvider`.

### `CobrancaEmitida`

```php
public readonly string $tipo;
public readonly int $valorCentavos;
public readonly \DateTimeImmutable $vencimento;
```

**Listeners esperados:**
- `RecurringBilling\Listeners\MarkInvoiceCobrancaEmitida` — popula `invoices.cobranca_id`
- `Core\Listeners\MarkSalePaymentPending` — `sale.payment_status = 'aguardando_pagamento'`

### `CobrancaPaga` ⭐ canônico

Mais importante do módulo. **Múltiplos listeners.**

```php
public readonly int $valorPagoCentavos;
public readonly \DateTimeImmutable $pagaEm;
public readonly string $formaPagamento;     // boleto | pix | cartao
public readonly ?string $payerCpfCnpj;
```

**Listeners esperados (em ordem de execução implícita por queue ou síncrono):**

1. `RecurringBilling\Listeners\MarkInvoicePaid` — `invoices.paid_at = now()`, reagenda próxima fatura
2. `NFSe\Listeners\EmitirNfseFromCobrancaPaga` — **US-RB-044 canônico irrevogável**. Emite NFSe automática
3. `Core\Listeners\CreateAccountTransactionFromCobranca` — lança `AccountTransaction` de entrada na conta vinculada
4. `Core\Listeners\MarkSalePaid` — `sale.payment_status = 'paid'` se origem='sale'
5. `Superadmin\Listeners\RenovarLicencaTenant` — quando origem='subscription_license' (Onda 5), atualiza `business.subscription_end_date += 1 month`

### `CobrancaVencida`

```php
public readonly int $diasVencido;          // 1, 2, 3, ...
public readonly \DateTimeImmutable $vencimentoOriginal;
```

**Listeners esperados:**
- `RecurringBilling\Listeners\SmartRetryRecurringInvoice` — reemite cobrança (até 3 retentativas, intervalo configurável)
- `RecurringBilling\Listeners\SubscriptionOverdue` — assinatura vai pra status `overdue` se invoice principal
- `Core\Listeners\NotifyContactPaymentOverdue` — dispara notificação automatizada

### `CobrancaCancelada`

```php
public readonly string $motivo;
public readonly int $canceladoPor;        // user_id
```

**Listeners esperados:**
- `RecurringBilling\Listeners\CancelInvoice` — `invoices.status = 'canceled'`
- `Core\Listeners\MarkSaleCancelled` — `sale.payment_status = 'cancelled'`

### `CobrancaErro`

```php
public readonly string $exception;        // FQCN
public readonly string $message;
public readonly string $driverKey;
public readonly array $context;           // payload sanitizado pra audit
```

**Listeners esperados:**
- `Otel\Listeners\RecordCobrancaErro` — span + counter no Otel
- `PaymentGateway\Listeners\AlertHealthCheck` — flag credencial pra próximo health check
- `RecurringBilling\Listeners\StopRetryIfCredentialError` — pára retentativas se erro é de credencial (não rede)

---

## 4. Exceções públicas

```
Modules\PaymentGateway\Exceptions\PaymentGatewayException        (raiz)
├── GatewayUnavailableException          (banco fora do ar, timeout, 5xx)
├── CredentialMisconfiguredException     (cert vencido, secret inválido)
├── InvalidPayerException                (CPF/CNPJ inválido, dados incompletos)
├── DriverNotSupportedException          (operação não suportada pelo driver)
├── CardDeclinedException                (emissor recusou)
├── IdempotencyConflictException         (tentativa de reemitir com chave já paga/cancelada)
└── WebhookSignatureInvalidException     (assinatura HMAC não confere)
```

---

## 5. Comandos artisan

```bash
# Health check (cron-friendly)
php artisan paymentgateway:health [--business=N] [--detail] [--json] [--alert]

# Listar drivers suportados + status
php artisan paymentgateway:drivers

# Forçar consulta de status (reconciliação manual)
php artisan paymentgateway:consultar {cobranca_id}

# Reprocessar webhook (debug)
php artisan paymentgateway:replay-webhook {gateway_webhook_event_id}

# Migrar credencial de boleto_credentials → payment_gateway_credentials (Onda 2)
php artisan paymentgateway:migrate-credentials [--dry-run]
```

---

## 6. Rotas HTTP públicas

```
GET    /payment-gateway/credenciais                 → PaymentGatewayController@index
POST   /payment-gateway/credenciais                 → PaymentGatewayController@store
PUT    /payment-gateway/credenciais/{id}            → PaymentGatewayController@update
DELETE /payment-gateway/credenciais/{id}            → PaymentGatewayController@destroy
POST   /payment-gateway/credenciais/{id}/health     → PaymentGatewayController@runHealthCheck

GET    /cobranca                                    → CobrancaController@index   (lista filtrada)
GET    /cobranca/{id}                               → CobrancaController@show    (drawer)
POST   /cobranca                                    → CobrancaController@store   (avulsa)
POST   /cobranca/{id}/cancelar                      → CobrancaController@cancel
POST   /cobranca/{id}/segunda-via                   → CobrancaController@resend
POST   /cobranca/{id}/refund                        → CobrancaController@refund

POST   /webhooks/inter                              → Webhooks\InterWebhookController
POST   /webhooks/c6                                 → Webhooks\C6WebhookController
POST   /webhooks/asaas                              → Webhooks\AsaasWebhookController
POST   /webhooks/bcb-pix                            → Webhooks\BcbPixWebhookController
```

URLs antigas (de quando webhooks moravam em `Modules/RecurringBilling`) viram **301 redirect durante 30 dias** após cutover Onda 3.

---

## 7. Permissions (Spatie)

Prefix `paymentgateway.*`:

| Permission | Quem usa |
|---|---|
| `paymentgateway.credenciais.viewAny` | admin financeiro |
| `paymentgateway.credenciais.create` | admin financeiro |
| `paymentgateway.credenciais.update` | admin financeiro |
| `paymentgateway.credenciais.delete` | admin financeiro |
| `paymentgateway.cobranca.viewAny` | Larissa, Eliana[E], admin |
| `paymentgateway.cobranca.emit` | Larissa, Eliana[E] |
| `paymentgateway.cobranca.cancel` | Eliana[E], admin |
| `paymentgateway.cobranca.refund` | admin financeiro (Tier escalado) |
| `paymentgateway.webhook.replay` | superadmin (debug) |

---

## 8. Convenções de versionamento de contrato

- **v0.x** — pré-Onda 1. Pode quebrar a qualquer momento.
- **v1.0** — congela quando Onda 1 (esqueleto) mergeia.
- **Mudanças quebradoras** após v1.0 exigem ADR filha de 0170 + deprecation window mínimo 60d.
- DTOs marcados `readonly` — imutabilidade garante reuso seguro entre listeners.

---

**Última atualização:** 2026-05-19 · v0.1 rascunho Onda 0 · vinculado a [ADR 0170](../../memory/decisions/0170-paymentgateway-extracao-camada-cobranca.md)
