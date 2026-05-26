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
 * `config_json` contém segredos sensíveis (api_key Asaas `$aact_*`,
 * client_secret Inter, secret_key Pagar.me, webhook_secret, cert_password
 * mTLS). Cast `encrypted:array` aplica AES-256-CBC + HMAC-SHA256 nativo do
 * Laravel 12 — coluna grava cipher base64-JSON {iv, value, mac, tag} no
 * disco e sob `SELECT` direto. Decryption automática só na hidratação do
 * model (acesso via `$cred->config_json`).
 *
 * PCI-DSS 4.0 — Requisito 3 (file/app-layer encryption-at-rest) coberto:
 *   - Algoritmo aprovado (AES-256-CBC + MAC)
 *   - Chave fora do payload (APP_KEY env)
 *   - Dump SQL bruto NÃO expõe credencial em texto plain
 *   - Rotação de APP_KEY = `paymentgateway:rewrap-credentials` rewrap
 *
 * LGPD: NUNCA logado por LogsActivity (logOnly explícita abaixo) — Spatie
 * gravaria properties.attributes em activity_log com segredos cifrados ainda
 * assim recuperáveis caso atacante tenha APP_KEY, então excluímos do log.
 *
 * Drift histórico — ADR 0170 §G prometia `encrypted:` desde Onda 2 mas cast
 * ficou `'array'` plain (auditoria audit-senior 2026-05-25, VULN P0-#1).
 * Corrigido em US-PG-001 com command rewrap pra rows pré-existentes.
 *
 * ADR 0170 Onda 2 · US-PG-001 (2026-05-25).
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
        // Encrypted-at-rest (AES-256-CBC + MAC) — VULN P0-#1 fix (US-PG-001).
        // Rows pré-existentes em plain JSON → migrar via
        // `php artisan paymentgateway:rewrap-credentials --apply`.
        'config_json'       => 'encrypted:array',
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
