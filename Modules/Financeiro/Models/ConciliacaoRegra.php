<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Regra de conciliação automática (US-FIN-080). Ver a migration
 * `2026_10_27_120000_create_fin_conciliacao_regras_table` e `RegrasConciliacaoService`.
 *
 * @property int $id
 * @property int $business_id
 * @property string $nome
 * @property bool $ativo
 * @property int $prioridade
 * @property string|null $descricao_contem
 * @property string|null $sentido
 * @property float|null $valor_min
 * @property float|null $valor_max
 * @property int|null $conta_bancaria_id
 * @property int|null $cliente_id
 * @property int|null $plano_conta_id
 * @property int|null $categoria_id
 * @property string $acao
 * @property int|null $created_by
 */
class ConciliacaoRegra extends Model
{
    use SoftDeletes, BusinessScope, LogsActivity;

    public const ACAO_SUGERIR = 'sugerir';
    public const ACAO_CONCILIAR = 'conciliar';

    /** Regra casa linha com título: quem criou/mudou a regra precisa de trilha. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'ativo', 'prioridade', 'descricao_contem', 'sentido', 'valor_min',
                'valor_max', 'conta_bancaria_id', 'cliente_id', 'plano_conta_id', 'categoria_id', 'acao'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.conciliacao_regra');
    }

    protected $table = 'fin_conciliacao_regras';

    protected $fillable = [
        'business_id', 'nome', 'ativo', 'prioridade', 'descricao_contem', 'sentido',
        'valor_min', 'valor_max', 'conta_bancaria_id', 'cliente_id', 'plano_conta_id',
        'categoria_id', 'acao', 'created_by',
    ];

    protected $casts = [
        'ativo'      => 'boolean',
        'prioridade' => 'integer',
        'valor_min'  => 'float',
        'valor_max'  => 'float',
    ];

    /**
     * A linha (descrição, valor com sinal, conta) atende os critérios da regra?
     * Valor da faixa é comparado em módulo: a faixa é sempre positiva, o sentido diz o lado.
     */
    public function atende(string $descricao, float $valorComSinal, ?int $contaBancariaId): bool
    {
        $sentidoLinha = $valorComSinal < 0 ? 'D' : 'C';
        if ($this->sentido !== null && $this->sentido !== $sentidoLinha) {
            return false;
        }
        if ($this->conta_bancaria_id !== null && (int) $this->conta_bancaria_id !== (int) $contaBancariaId) {
            return false;
        }
        $abs = abs($valorComSinal);
        if ($this->valor_min !== null && $abs < $this->valor_min - 0.005) {
            return false;
        }
        if ($this->valor_max !== null && $abs > $this->valor_max + 0.005) {
            return false;
        }
        $trecho = trim((string) $this->descricao_contem);

        return $trecho === '' || mb_stripos($descricao, $trecho) !== false;
    }
}
