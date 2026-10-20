<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ComunicacaoVisual\Entities\Concerns\EscopoPorNegocioDaSessao;

/**
 * OsItem — linha da OS de comunicação visual, copiada do item do orçamento aprovado (UC-FLX-01).
 *
 * Só medidas e material: o preço fica no orçamento, e o valor da OS é o total dele.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope por business_id da sessão (trait EscopoPorNegocioDaSessao).
 *
 * @property int         $id
 * @property int         $business_id
 * @property int         $os_id
 * @property int|null    $orcamento_item_id
 * @property int|null    $material_id
 * @property string      $descricao
 * @property float|null  $largura_m
 * @property float|null  $altura_m
 * @property int         $quantidade
 * @property float|null  $area_m2
 * @property string|null $observacoes
 * @property int         $ordem
 */
class OsItem extends Model
{
    use EscopoPorNegocioDaSessao;

    protected $table = 'comvis_os_itens';

    protected $fillable = [
        'business_id',
        'os_id',
        'orcamento_item_id',
        'material_id',
        'descricao',
        'largura_m',
        'altura_m',
        'quantidade',
        'area_m2',
        'observacoes',
        'ordem',
    ];

    protected $casts = [
        'business_id'       => 'integer',
        'os_id'             => 'integer',
        'orcamento_item_id' => 'integer',
        'material_id'       => 'integer',
        'largura_m'         => 'decimal:3',
        'altura_m'          => 'decimal:3',
        'quantidade'        => 'integer',
        'area_m2'           => 'decimal:3',
        'ordem'             => 'integer',
    ];

    public function os(): BelongsTo
    {
        return $this->belongsTo(Os::class, 'os_id');
    }
}
