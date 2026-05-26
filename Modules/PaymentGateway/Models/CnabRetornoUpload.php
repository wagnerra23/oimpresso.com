<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de uploads de arquivo de RETORNO CNAB por credencial.
 *
 * Fonte de verdade do que foi processado pelo Job `CnabRetornoProcessor`.
 * Cada upload manual em `/settings/payment-gateways/{cred}/cnab-retorno`
 * gera 1 linha aqui (criada pelo Controller, atualizada pelo Job).
 *
 * Multi-tenant Tier 0 — global scope via HasBusinessScope (ADR 0093).
 *
 * Sem LogsActivity — tabela já é audit log (cobertura idêntica). Append-only
 * por convenção: só `processado_em`/`qtd_*`/`erros_json` mudam após criação.
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.0
 */
class CnabRetornoUpload extends Model
{
    use HasBusinessScope;

    protected $table = 'cnab_retorno_uploads';

    protected $fillable = [
        'business_id',
        'payment_gateway_credential_id',
        'arquivo_path',
        'arquivo_nome_original',
        'arquivo_tamanho_bytes',
        'processado_em',
        'qtd_paga',
        'qtd_cancelada',
        'qtd_vencida',
        'qtd_registrada',
        'erros_json',
        'processado_por_user_id',
    ];

    protected $casts = [
        'arquivo_tamanho_bytes'  => 'integer',
        'processado_em'          => 'datetime',
        'qtd_paga'               => 'integer',
        'qtd_cancelada'          => 'integer',
        'qtd_vencida'            => 'integer',
        'qtd_registrada'         => 'integer',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayCredential::class, 'payment_gateway_credential_id');
    }

    /**
     * Decodifica erros_json (lazy — texto puro no DB pra escapar de bind issues).
     *
     * @return array<int, string>
     */
    public function errosArray(): array
    {
        if (empty($this->erros_json)) {
            return [];
        }
        $decoded = json_decode((string) $this->erros_json, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}
