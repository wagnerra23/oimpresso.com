<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;

/**
 * `fisio_records` — prontuário (Res. COFFITO 414/2012 e 619/2025). Escopo por empresa (ADR 0093).
 *
 * APPEND-ONLY: registro assinado (`signed_at` preenchido) não aceita UPDATE nem DELETE — aqui
 * no model e no banco (trigger MySQL da migration). Correção é um novo registro
 * `kind=adendo` com `amends_id` apontando o original, que fica intacto.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property string $kind
 * @property array $body
 * @property ?string $modality
 * @property ?string $channel
 * @property ?int $signed_by
 * @property ?string $signed_crefito
 * @property ?\Illuminate\Support\Carbon $signed_at
 * @property ?int $amends_id
 */
class Record extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const UPDATED_AT = null;

    public const KIND_AVALIACAO = 'avaliacao';

    public const KIND_EVOLUCAO = 'evolucao';

    public const KIND_TELEMONITORAMENTO = 'telemonitoramento';

    public const KIND_ADENDO = 'adendo';

    protected $table = 'fisio_records';

    protected $guarded = ['id'];

    protected $casts = [
        'body' => 'array',
        'signed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $registro): void {
            if ($registro->getOriginal('signed_at') !== null) {
                throw DadoClinicoImutavelException::alteracao($registro->getTable());
            }
        });
    }

    public function estaAssinado(): bool
    {
        return $this->signed_at !== null;
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /** Registro que este adendo corrige. @return BelongsTo<Record, $this> */
    public function amends(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amends_id');
    }

    /** Adendos que corrigem este registro. @return HasMany<Record, $this> */
    public function adendos(): HasMany
    {
        return $this->hasMany(self::class, 'amends_id');
    }
}