<?php

namespace Modules\NFSe\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\NFSe\Models\Concerns\NfseBusinessScope;

/**
 * Config NFS-e por business (1:1) — provider municipal, fiscais e ambiente.
 *
 * @property int         $id
 * @property int         $business_id
 * @property string|null $provider
 * @property string|null $municipio_codigo_ibge
 * @property string|null $serie_default
 * @property string|null $cnae
 * @property string|null $lc116_codigo_default
 * @property string|null $aliquota_iss
 * @property string      $ambiente               1=producao | homologacao (default)
 * @property int|null    $cert_id
 * @property string|null $prestador_cnpj
 * @property string|null $prestador_im
 */
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
