<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_document_transcriptions` — o que a IA leu na foto e o que o profissional conferiu
 * (US-FISIO-063, parte 3). Escopo por empresa (ADR 0093). Sem DELETE.
 *
 * @property int $id
 * @property int $business_id
 * @property int $document_id
 * @property string $status
 * @property array<string, mixed> $suggested
 * @property ?array<string, mixed> $confirmed
 * @property ?list<string> $corrected
 * @property ?string $model
 * @property int $requested_by
 * @property ?int $decided_by
 * @property ?\Illuminate\Support\Carbon $decided_at
 */
class TranscricaoDocumento extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const PENDENTE = 'pendente';

    public const CONFERIDA = 'conferida';

    public const DESCARTADA = 'descartada';

    protected $table = 'fisio_document_transcriptions';

    protected $guarded = ['id'];

    protected $casts = [
        'suggested' => 'array',
        'confirmed' => 'array',
        'corrected' => 'array',
        'decided_at' => 'datetime',
    ];
}
