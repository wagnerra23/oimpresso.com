<?php

namespace Modules\Captacao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Fonte de recurso da empresa (captacao_fontes): edital, lei de incentivo, emenda parlamentar, convênio SUS, fundo, doação,
 * parceria ou voluntariado (ADR 0452 D4). Escopo por empresa (ADR 0093).
 */
class Fonte extends Model
{
    use HasBusinessScope;

    public const TIPOS = ['edital', 'lei_incentivo', 'emenda_parlamentar', 'convenio_sus', 'fundo', 'doacao_pj', 'doacao_pf', 'parceria', 'voluntariado', 'outro'];

    protected $table = 'captacao_fontes';

    protected $fillable = ['business_id', 'tipo', 'nome', 'orgao', 'numero_edital', 'inscricao_ate', 'observacao', 'ativa'];

    protected $casts = ['inscricao_ate' => 'date', 'ativa' => 'boolean'];
}
