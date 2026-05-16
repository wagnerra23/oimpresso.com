<?php

namespace Modules\Woocommerce\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Log de sincronização Woocommerce ↔ oimpresso.
 *
 * **D7.b LGPD (Wave 14):** LogsActivity registra mudanças no sync log
 * (orders/products/customers — referência indireta a PII do cliente final).
 * Trail append-only auditável ([ADR 0093]).
 *
 * **Multi-tenant Tier 0:** scope via `business_id` (coluna existente em
 * `woocommerce_sync_logs` — migration 2018_10_11_173839). Não usamos
 * `HasBusinessScope` aqui porque controller já filtra via
 * `where('business_id', $business_id)` explícito.
 *
 * @see Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController
 */
class WoocommerceSyncLog extends Model
{
    use LogsActivity; // D7.b LGPD — audit trail sync log (Wave 14 governance D7)

    protected $guarded = ['id'];

    /**
     * Spatie ActivityLog — registra mudanças no sync log.
     *
     * **PII safety:** colunas `created_data` / `error_data` (JSON) podem
     * conter order numbers (sem PII direta). Caso futuras colunas armazenem
     * email/phone do cliente final, redactar via PiiRedactor ANTES de
     * persistir (não retroativamente via LogOptions — Spatie não suporta).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'business_id',
                'route',
                'operation_type',
                'created_data',
                'error_data',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('woocommerce.sync_log');
    }
}
