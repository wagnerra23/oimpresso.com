<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * `pcp_romaneios` — romaneio de impressão de uma OS (UC-FLX-12 · US-PCP-023). Gravado não muda:
 * update e delete pelo Eloquent são recusados; gerar de novo cria a versão seguinte
 * (RomaneioDeImpressao). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $os_tipo
 * @property int $os_id
 * @property int $versao
 * @property string $numero
 * @property int $programacao_id
 * @property ?int $posto_id
 * @property ?string $posto_nome
 * @property int $gerado_por
 * @property \Illuminate\Support\Carbon $gerado_em
 */
class Romaneio extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_romaneios';

    protected $guarded = ['id'];

    protected $casts = ['gerado_em' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Romaneio gravado não muda: gere uma nova versão.'));
        static::deleting(fn () => throw new LogicException('Romaneio gravado não é apagado.'));
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<RomaneioLinha, $this> */
    public function linhas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RomaneioLinha::class, 'romaneio_id')->orderBy('ordem')->orderBy('id');
    }
}
