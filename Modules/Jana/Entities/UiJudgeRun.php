<?php

declare(strict_types=1);

namespace Modules\Jana\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * UiJudgeRun — 1 linha por julgamento do PrUiJudgeAgent (medição append-only).
 *
 * Tabela: jana_ui_judge_runs
 * NÃO multi-tenant: artefato de CI/repo, não dado de business (sem business_id /
 * HasBusinessScope · ver migration). Append-only: só INSERT, nunca UPDATE/DELETE.
 *
 * Gravado por UiJudgePrCommand após parse do review. Lido por
 * `jana:ui-judge-trend` pra responder "a técnica está sendo medida?" — trend de
 * score, distribuição de verdict e custo estimado por janela.
 *
 * @see app/Console/Commands/UiJudgePrCommand.php (grava)
 * @see Modules/Jana/Console/Commands/UiJudgeTrendCommand.php (lê)
 * @see Modules/Jana/Ai/Agents/PrUiJudgeAgent.php (o juiz)
 */
class UiJudgeRun extends Model
{
    protected $table = 'jana_ui_judge_runs';

    protected $fillable = [
        'pr_number',
        'repo',
        'provider',
        'model',
        'score',
        'verdict',
        'violacoes_count',
        'dimensoes',
        'confidence',
        'samples',
        'custo_usd_estimado',
        'judged_at',
    ];

    protected $casts = [
        'pr_number' => 'integer',
        'score' => 'integer',
        'violacoes_count' => 'integer',
        'dimensoes' => 'array',
        'confidence' => 'decimal:2',
        'samples' => 'integer',
        'custo_usd_estimado' => 'decimal:4',
        'judged_at' => 'datetime',
    ];
}
