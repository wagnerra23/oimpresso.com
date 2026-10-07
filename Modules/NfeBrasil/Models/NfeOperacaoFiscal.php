<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Natureza de operação fiscal (D-OPERACAO · playbook Fiscal thread 07).
 *
 * A porta de entrada do cálculo: cada regra de NCM pertence a uma operação, e a operação tem a
 * sua regra geral (Nível 4). A operação padrão ("Venda", `padrao=true`) é a das regras sem
 * `operacao_id` e usa o `tributacao_default` da empresa quando `regra_geral` é NULL.
 *
 * Multi-tenant: `HasBusinessScope` + o motor escopa por `business_id` explicitamente.
 */
class NfeOperacaoFiscal extends Model
{
    use HasBusinessScope;
    use SoftDeletes;

    protected $table = 'nfe_operacoes_fiscais';

    protected $fillable = [
        'business_id', 'slug', 'nome', 'finalidade', 'cfop',
        'tipo_destinatario', 'regra_geral', 'padrao',
    ];

    protected $casts = [
        'regra_geral' => 'array',
        'padrao'      => 'boolean',
        'finalidade'  => 'integer',
    ];

    /**
     * CFOP com "?" no 1º dígito vira 5 (mesma UF), 6 (outra UF) ou 7 (exterior, `EX`).
     * CFOP sem "?" volta igual (R-NFE-020c).
     */
    public static function resolverCfop(string $cfop, string $ufOrigem, string $ufDestino): string
    {
        if ($cfop === '' || $cfop[0] !== '?') {
            return $cfop;
        }

        $digito = match (true) {
            strtoupper($ufDestino) === 'EX'                   => '7',
            strtoupper($ufDestino) === strtoupper($ufOrigem) => '5',
            default                                           => '6',
        };

        return $digito . substr($cfop, 1);
    }
}
