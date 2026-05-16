<?php

namespace Modules\RecurringBilling\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BoletoCredential extends Model
{
    use HasBusinessScope;
    use LogsActivity; // D7 LGPD audit trail (Wave 14) — credencial bancária sensível

    protected $table = 'rb_boleto_credentials';

    protected $fillable = [
        'business_id',
        'conta_bancaria_id',
        'banco',       // inter | c6 | asaas
        'ambiente',    // production | sandbox
        'ativo',
        'config_json', // campos sensíveis criptografados via Crypt::encryptString
        'nome_display',
    ];

    protected $casts = [
        'config_json' => 'array',
        'ativo'       => 'boolean',
    ];

    /**
     * Auditoria LGPD (D7) — registra ativação/desativação de credencial bancária.
     * NUNCA loga `config_json` (contém token cifrado + webhook_secret).
     *
     * @see Modules\RecurringBilling\Config\retention.php
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'conta_bancaria_id', 'banco', 'ambiente',
                'ativo', 'nome_display',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recurringbilling.boleto_credential');
    }
}
