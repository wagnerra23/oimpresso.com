<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;

/**
 * `fisio_game_ledger` — lançamentos de XP. APPEND-ONLY (model + trigger MySQL): nenhum UPDATE
 * nem DELETE; XP, nível e sequência são DERIVADOS daqui e dos check-ins (ADR 0437 §6).
 * `reason` é enum fechado no banco. Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $reason
 * @property int $xp
 */
class GameLedger extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const UPDATED_AT = null;

    public const REASON_EXERCICIO = 'exercicio_concluido';

    public const REASON_CHECKIN = 'checkin';

    /** Missão do dia cumprida (T-08c); `ref` = chave da missão. */
    public const REASON_MISSAO = 'missao';

    /** Desafio semanal aceito e batido (T-08c); `ref` = segunda-feira da semana. */
    public const REASON_DESAFIO = 'desafio_semanal';

    protected $table = 'fisio_game_ledger';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'xp' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $lancamento): void {
            throw DadoClinicoImutavelException::alteracao($lancamento->getTable());
        });
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}