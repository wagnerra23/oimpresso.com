<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Cobrança canônica do PaymentGateway.
 *
 * Source-of-truth do que foi cobrado (independente de origem: Sell/Invoice/
 * Subscription/Avulsa). Eventos `CobrancaEmitida`/`Paga`/`Vencida` apontam
 * pra registros aqui.
 *
 * Idempotência: (business_id, idempotency_key) UNIQUE.
 *
 * Multi-tenant Tier 0 — global scope via HasBusinessScope (ADR 0093).
 *
 * LGPD: payer_cpf_cnpj / payer_name / payer_email / descricao são PII —
 * declarado em module.json.lgpd_compliance, retention 5y, redactor habilitado.
 *
 * ADR 0170 Onda 2.
 */
class Cobranca extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'cobrancas';

    protected $fillable = [
        'business_id',
        'payment_gateway_credential_id',
        'gateway_external_id',
        'tipo',
        'status',
        'valor_centavos',
        'valor_pago_centavos',
        'vencimento',
        'paga_em',
        'contact_id',
        'payer_cpf_cnpj',
        'payer_name',
        'payer_email',
        'descricao',
        'idempotency_key',
        'origem_type',
        'origem_id',
        'linha_digitavel',
        'codigo_barras',
        'pix_emv',
        'pix_qr_code_path',
        'boleto_pdf_url',
        'nosso_numero',
        'forma_pagamento',
        'payload_gateway',
    ];

    protected $casts = [
        'valor_centavos'      => 'integer',
        'valor_pago_centavos' => 'integer',
        'vencimento'          => 'date',
        'paga_em'             => 'datetime',
        'payload_gateway'     => 'array',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayCredential::class, 'payment_gateway_credential_id');
    }

    /**
     * LGPD: NÃO loga payer_* / descricao / payload_gateway (PII bruta).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'tipo', 'valor_centavos', 'valor_pago_centavos',
                'vencimento', 'paga_em', 'forma_pagamento',
                'origem_type', 'origem_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('paymentgateway.cobranca');
    }
}
