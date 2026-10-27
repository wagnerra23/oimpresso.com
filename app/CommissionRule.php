<?php

declare(strict_types=1);

namespace App;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Regra de comissão (US-COMM-015). Cálculo em `App\Services\Sells\ComissaoPorRegra`.
 */
class CommissionRule extends Model
{
    use HasBusinessScope;

    protected $table = 'commission_rules';

    protected $guarded = ['id'];

    protected $casts = [
        'valor' => 'decimal:4',
        'ativo' => 'boolean',
    ];
}
