<?php

namespace Modules\Essentials\Entities\Folha;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * As três incidências de uma rubrica (INSS, IRRF, FGTS) num intervalo de competências
 * (ADR-mãe da folha §2.2-§2.3). São três booleanos independentes, nunca um enum: o aviso prévio
 * indenizado é ❌ INSS ❌ IRRF ✅ FGTS, combinação que nenhuma escala ordenada expressa.
 */
class FolhaRubricaVigencia extends Model
{
    use HasBusinessScope;

    protected $table = 'essentials_folha_rubrica_vigencias';

    protected $fillable = [
        'business_id', 'rubrica_id', 'vigencia_inicio', 'vigencia_fim',
        'incide_inss', 'incide_irrf', 'incide_fgts',
    ];

    protected $casts = [
        'incide_inss' => 'boolean',
        'incide_irrf' => 'boolean',
        'incide_fgts' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $v) {
            FolhaCompetencia::validar($v->vigencia_inicio, 'vigencia_inicio');
            if ($v->vigencia_fim !== null) {
                FolhaCompetencia::validar($v->vigencia_fim, 'vigencia_fim');
                if ($v->vigencia_fim < $v->vigencia_inicio) {
                    throw new InvalidArgumentException('vigencia_fim não pode ser anterior a vigencia_inicio.');
                }
            }
        });
    }
}
