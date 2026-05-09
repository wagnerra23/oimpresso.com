<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de NF-e recebida (parsed do XML).
 *
 * Sucessor canônico de App\ItemDfe (legacy órfão removido).
 */
class NfeDfeItem extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_dfe_itens';

    protected $fillable = [
        'business_id',
        'dfe_recebido_id',
        'ncm',
        'cfop',
        'descricao',
        'quantidade',
        'valor_unitario',
        'valor_total',
    ];

    protected $casts = [
        'quantidade'     => 'decimal:4',
        'valor_unitario' => 'decimal:4',
        'valor_total'    => 'decimal:2',
    ];

    public function dfeRecebido(): BelongsTo
    {
        return $this->belongsTo(NfeDfeRecebido::class, 'dfe_recebido_id');
    }
}
