<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_roteiros` — roteiro por família de produto (UC-PCV-13). Cadastros do PCP (ADR 0431, thread 03). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $familia
 * @property string $nome
 * @property bool $ativo
 */
class Roteiro extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_roteiros';

    protected $guarded = ['id'];

    protected $casts = ['ativo' => 'boolean'];

    public function etapas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoteiroEtapa::class, 'roteiro_id')->orderBy('bloco')->orderBy('trilha')->orderBy('ordem');
    }
}
