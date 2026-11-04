<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `fisio_schedule_blocks` — horário bloqueado do profissional (almoço, curso, férias; A5). Não é
 * atendimento; no app aparece como indisponível. Escopo por empresa (ADR 0093). US-FISIO-013.
 */
class ScheduleBlock extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_schedule_blocks';

    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
