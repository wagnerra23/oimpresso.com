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
     * Segredo do webhook do gateway, decifrado. `config_json.webhook_secret` é gravado com
     * Crypt::encryptString desde 2026-11-19 (auditoria RB d4; a migration
     * 2026_11_19_100000 recifrou o que estava em claro).
     *
     * Valor que não decifra (em claro, chave trocada, corrompido) vira null: o webhook
     * rejeita com 401 em vez de comparar contra texto puro (fail-secure).
     */
    public function webhookSecret(): ?string
    {
        $cifrado = $this->config_json['webhook_secret'] ?? null;
        if (! is_string($cifrado) || $cifrado === '') {
            return null;
        }

        try {
            $segredo = \Illuminate\Support\Facades\Crypt::decryptString($cifrado);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            \Illuminate\Support\Facades\Log::warning('rb.boleto_credential.webhook_secret_ilegivel', [
                'business_id' => $this->business_id,
                'credential_id' => $this->id,
            ]);

            return null;
        }

        return $segredo !== '' ? $segredo : null;
    }

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
