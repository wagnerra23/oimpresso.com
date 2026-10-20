<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * `pcp_romaneio_linhas` — uma peça do romaneio de impressão (UC-FLX-12 · US-PCP-023): cópia do item
 * do orçamento da OS no momento da geração. Imutável como o romaneio. Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $romaneio_id
 * @property ?int $orcamento_item_id
 * @property int $ordem
 * @property string $descricao
 * @property ?int $material_id
 * @property ?string $material_nome
 * @property ?string $largura_m
 * @property ?string $altura_m
 * @property int $quantidade
 * @property ?string $area_m2
 */
class RomaneioLinha extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_romaneio_linhas';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Linha de romaneio gravada não muda.'));
        static::deleting(fn () => throw new LogicException('Linha de romaneio gravada não é apagada.'));
    }
}
