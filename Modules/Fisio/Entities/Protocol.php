<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `fisio_protocols` — protocolo-modelo da clínica, com a lógica toda visível (não é SaMD,
 * ADR 0437 §5 P58). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property ?string $region
 * @property array $items
 * @property ?string $notes
 */
class Protocol extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_protocols';

    protected $guarded = ['id'];

    protected $casts = ['items' => 'array'];
}