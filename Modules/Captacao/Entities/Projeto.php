<?php

namespace Modules\Captacao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projeto de captação (captacao_projetos). O proponente é texto no próprio projeto — nome e
 * CNPJ — e não FK para outro módulo (ADR 0452 D3). Situação em lista fechada (D5).
 */
class Projeto extends Model
{
    use HasBusinessScope;

    public const SITUACOES = ['ideia', 'elaboracao', 'inscrito', 'aprovado', 'reprovado', 'captando', 'execucao', 'prestacao_contas', 'encerrado'];

    protected $table = 'captacao_projetos';

    protected $fillable = [
        'business_id', 'titulo', 'objetivo', 'proponente_nome', 'proponente_cnpj', 'situacao', 'situacao_desde',
        'valor_solicitado', 'valor_aprovado', 'execucao_inicio', 'execucao_fim', 'prestacao_contas_ate', 'created_by',
    ];

    protected $casts = [
        'situacao_desde' => 'date',
        'execucao_inicio' => 'date',
        'execucao_fim' => 'date',
        'prestacao_contas_ate' => 'date',
        'valor_solicitado' => 'decimal:4',
        'valor_aprovado' => 'decimal:4',
    ];

    public function aportes(): HasMany
    {
        return $this->hasMany(Aporte::class, 'projeto_id');
    }

    public function comprovantes(): HasMany
    {
        return $this->hasMany(Comprovante::class, 'projeto_id');
    }
}
