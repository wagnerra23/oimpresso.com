<?php

namespace Modules\Associacoes\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Associação-membro da rede (assoc_associacoes). Linha dentro da empresa da rede, não empresa
 * própria (ADR 0451 D1). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $nome
 * @property string|null $sigla
 * @property string $cidade
 * @property string|null $regiao
 * @property string|null $cnpj
 * @property int|null $representante_contact_id
 * @property string $situacao
 * @property \Illuminate\Support\Carbon|null $diretoria_atualizada_em
 * @property \Illuminate\Support\Carbon|null $diretoria_mudou_em
 */
class Associacao extends Model
{
    use HasBusinessScope;

    public const SITUACOES = ['em_adesao', 'ativa', 'desligada'];

    protected $table = 'assoc_associacoes';

    protected $fillable = [
        'business_id', 'nome', 'sigla', 'cidade', 'regiao', 'cnpj', 'representante_contact_id',
        'situacao', 'diretoria_atualizada_em', 'diretoria_mudou_em',
    ];

    protected $casts = [
        'diretoria_atualizada_em' => 'date',
        'diretoria_mudou_em' => 'date',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoAssociacao::class, 'associacao_id');
    }

    public function associadas(): HasMany
    {
        return $this->hasMany(Associada::class, 'associacao_id');
    }
}
