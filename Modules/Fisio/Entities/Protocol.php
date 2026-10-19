<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Fisio\Entities\Concerns\EscopoBibliotecaFisio;

/**
 * `fisio_protocols` — protocolo com a lógica toda visível (não é SaMD, ADR 0437 §5 P58).
 * `business_id` nulo = modelo do produto (revisado pela responsável técnica); preenchido =
 * protocolo da clínica, em geral cópia de um modelo (`origin_protocol_id`). Mesmo escopo da
 * biblioteca: a sessão vê os modelos e os dela, nunca os de outra empresa (ADR 0093).
 *
 * @property int $id
 * @property ?int $business_id
 * @property ?int $origin_protocol_id
 * @property string $name
 * @property ?string $region
 * @property array $items
 * @property ?string $notes
 */
class Protocol extends Model
{
    use EscopoBibliotecaFisio;

    protected $table = 'fisio_protocols';

    protected $guarded = ['id'];

    protected $casts = ['items' => 'array'];
}