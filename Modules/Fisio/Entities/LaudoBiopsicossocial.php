<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;

/**
 * `fisio_laudos_bps` — laudo de avaliação biopsicossocial para fibromialgia (US-FISIO-060). Em SC
 * segue o modelo da Deliberação 500/CIB/2026 (`modelo = ses-sc-cipf-1.0`). Escopo por empresa
 * (ADR 0093). Sem DELETE.
 *
 * `dados` é o formulário: identificação, matriz IFBr-M (código do item => 100/75/50/25), fatores
 * contextuais, conclusão da equipe (três Sim/Não + observação), local e data. A conclusão só é
 * marcada por gente; o software calcula a pontuação e não preenche a conclusão.
 *
 * Finalizado (`finalizado_em`) não aceita UPDATE nem DELETE, aqui e no banco. Correção é laudo
 * novo com `amends_id`.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property string $modelo
 * @property ?array $dados
 * @property int $created_by
 * @property ?int $finalizado_por
 * @property ?\Illuminate\Support\Carbon $finalizado_em
 * @property ?int $amends_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class LaudoBiopsicossocial extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    protected $table = 'fisio_laudos_bps';

    protected $guarded = ['id'];

    protected $casts = [
        'dados' => 'array',
        'finalizado_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $laudo): void {
            if ($laudo->getOriginal('finalizado_em') !== null) {
                throw DadoClinicoImutavelException::alteracao($laudo->getTable());
            }
        });
    }

    public function estaFinalizado(): bool
    {
        return $this->finalizado_em !== null;
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** @return HasMany<LaudoBiopsicossocialEquipe, $this> */
    public function equipe(): HasMany
    {
        return $this->hasMany(LaudoBiopsicossocialEquipe::class, 'laudo_id');
    }
}
