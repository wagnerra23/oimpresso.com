<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Credencial de gateway de cobrança (Inter/C6/Asaas/BCB Pix/PesaPal).
 *
 * Multi-tenant Tier 0 — global scope via HasBusinessScope (ADR 0093).
 *
 * `config_json` contém segredos cifrados (token/secret/cert) via
 * Crypt::encryptString — NUNCA logado por LogsActivity (PCI/LGPD).
 *
 * ADR 0170 Onda 2.
 */
class PaymentGatewayCredential extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'payment_gateway_credentials';

    protected $fillable = [
        'business_id',
        'gateway_key',
        'ambiente',
        'ativo',
        'nome_display',
        'config_json',
        'conta_bancaria_id',
        'health_status',
        'health_checked_at',
    ];

    protected $casts = [
        'config_json'       => 'array',
        'ativo'             => 'boolean',
        'health_checked_at' => 'datetime',
    ];

    /**
     * LGPD/PCI: NÃO loga config_json (segredos cifrados).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'gateway_key', 'ambiente', 'ativo', 'nome_display',
                'conta_bancaria_id', 'health_status', 'health_checked_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('paymentgateway.credential');
    }
}
