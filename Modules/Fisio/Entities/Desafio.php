<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;

/**
 * `fisio_game_desafios` — decisão do paciente sobre o desafio semanal (T-08c, etapa 02 §3.4):
 * aceitou com meta de dias, ou recusou. Uma por semana; APPEND-ONLY (model + trigger MySQL).
 * Recusar não lança nada no ledger. Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property \Illuminate\Support\Carbon $week_start
 * @property bool $accepted
 * @property ?int $target_days
 */
class Desafio extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const UPDATED_AT = null;

    protected $table = 'fisio_game_desafios';

    protected $guarded = ['id'];

    protected $casts = [
        'week_start' => 'date:Y-m-d',
        'accepted' => 'boolean',
        'target_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $desafio): void {
            throw DadoClinicoImutavelException::alteracao($desafio->getTable());
        });
    }
}
