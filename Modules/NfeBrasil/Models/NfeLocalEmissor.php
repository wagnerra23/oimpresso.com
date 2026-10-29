<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Local (`business_locations`) que emite como estabelecimento próprio — thread 26 (R-NFE-031/032).
 *
 * Sem linha aqui, o local emite como o business (UF, CNPJ e série padrão), igual a antes.
 *
 * @property int $id
 * @property int $business_id
 * @property int $location_id
 * @property string|null $serie_nfe
 * @property string|null $serie_nfce
 * @property string|null $logradouro
 * @property string|null $numero
 * @property string|null $bairro
 * @property string|null $cod_municipio
 * @property string|null $cep
 * @property int|null $crt  regime do local (P-155); null = o da empresa
 */
class NfeLocalEmissor extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0

    protected $table = 'nfe_locais_emissores';

    /** CRT da NF-e: 1 Simples, 2 Simples com excesso de sublimite, 3 regime normal, 4 MEI. */
    public const CRTS = [1, 2, 3, 4];

    protected $fillable = [
        'business_id', 'location_id', 'serie_nfe', 'serie_nfce',
        'logradouro', 'numero', 'bairro', 'cod_municipio', 'cep', 'crt',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'location_id' => 'integer',
        'crt'         => 'integer',
    ];

    /** Série deste local para o modelo (55 = NF-e, 65 = NFC-e); null = usa a do business. */
    public function seriePara(string $modelo): ?string
    {
        $serie = $modelo === '65' ? $this->serie_nfce : $this->serie_nfe;

        return $serie !== null && $serie !== '' ? (string) $serie : null;
    }
}
