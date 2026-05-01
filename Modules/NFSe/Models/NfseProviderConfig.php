<?php

namespace Modules\NFSe\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\NFSe\Models\Concerns\NfseBusinessScope;

class NfseProviderConfig extends Model
{
    use SoftDeletes, NfseBusinessScope;

    protected $table = 'nfse_provider_configs';

    protected $fillable = [
        'business_id', 'provider', 'municipio_codigo_ibge',
        'serie_default', 'cnae', 'lc116_codigo_default',
        'aliquota_iss', 'ambiente', 'cert_id',
    ];

    protected $casts = [
        'aliquota_iss' => 'decimal:4',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(NfseCertificado::class, 'cert_id');
    }

    public function isProducao(): bool
    {
        return $this->ambiente === 'producao';
    }
}
