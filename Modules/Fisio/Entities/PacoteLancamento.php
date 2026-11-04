<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;

/**
 * `fisio_package_ledger` — lançamentos de sessão do pacote. APPEND-ONLY (model + trigger
 * MySQL): correção é lançamento inverso (`estorno`). `kind` é enum fechado no banco.
 *
 * @property int $id
 * @property int $business_id
 * @property int $package_id
 * @property string $kind
 * @property int $sessions
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property string|null $ref
 * @property string|null $note
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class PacoteLancamento extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const UPDATED_AT = null;

    public const CREDITO = 'credito';

    public const DEBITO = 'debito';

    public const ESTORNO = 'estorno';

    public const EXTENSAO = 'extensao';

    protected $table = 'fisio_package_ledger';

    protected $guarded = ['id'];

    protected $casts = [
        'sessions' => 'integer',
        'valid_until' => 'date:Y-m-d',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $lancamento): void {
            throw DadoClinicoImutavelException::alteracao($lancamento->getTable());
        });
    }

    /** @return BelongsTo<Pacote, $this> */
    public function pacote(): BelongsTo
    {
        return $this->belongsTo(Pacote::class, 'package_id');
    }
}
