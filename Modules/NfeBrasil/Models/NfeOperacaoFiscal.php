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
 *
 * @property int $id
 * @property int $business_id
 * @property string $slug
 * @property string $nome
 * @property int $finalidade
 * @property string|null $cfop
 * @property string|null $cfop_interestadual
 * @property string|null $cfop_exterior
 * @property string|null $tipo                     saida · entrada · null = pelo 1º dígito do cfop
 * @property bool $ativa
 * @property bool $pede_nota_referenciada
 * @property string|null $tipo_destinatario
 * @property array<string,mixed>|null $regra_geral
 * @property array<string,mixed>|null $config_tela  tributos como a tela edita (motor lê a partir da onda 4)
 * @property bool $padrao
 */
class NfeOperacaoFiscal extends Model
{
    use HasBusinessScope;
    use SoftDeletes;

    protected $table = 'nfe_operacoes_fiscais';

    protected $fillable = [
        'business_id', 'slug', 'nome', 'finalidade', 'cfop',
        'tipo_destinatario', 'regra_geral', 'padrao',
        // Onda 2 das naturezas (tela da Tributação). O motor ainda não lê estes campos.
        'tipo', 'ativa', 'pede_nota_referenciada', 'cfop_interestadual', 'cfop_exterior',
        'config_tela',
    ];

    protected $casts = [
        'regra_geral' => 'array',
        'config_tela' => 'array',
        'padrao'      => 'boolean',
        'ativa'                  => 'boolean',
        'pede_nota_referenciada' => 'boolean',
        'finalidade'  => 'integer',
    ];

    /**
     * A tela de naturezas só edita o que o motor ainda não usa para calcular (onda 3).
     *
     * O motor lê a operação padrão (Venda) e as que têm `regra_geral` (vindas do CSV): editar uma
     * delas pela tela mudaria imposto antes da onda 4. As outras não têm o que calcular — o motor
     * recusa uma operação não padrão sem `regra_geral` — então gravá-las não muda valor.
     */
    public function editavelPelaTela(): bool
    {
        return ! $this->padrao && empty($this->regra_geral);
    }

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
