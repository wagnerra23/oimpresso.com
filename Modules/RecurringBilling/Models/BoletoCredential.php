<?php

namespace Modules\RecurringBilling\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Credencial de boleto/PIX por tenant.
 *
 * `config_json` traz secrets criptografados (Crypt::encryptString) — NUNCA logado.
 *
 * LGPD (Wave 10 D7): LogsActivity registra (banco, ambiente, ativo, nome_display).
 * `config_json` EXCLUÍDO explicitamente — contém api_key/client_secret/certificado.
 */
class BoletoCredential extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['banco', 'ambiente', 'ativo', 'nome_display', 'conta_bancaria_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recurringbilling.boleto_credential');
    }

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
}
