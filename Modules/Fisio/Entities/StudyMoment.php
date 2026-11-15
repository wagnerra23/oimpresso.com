<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `fisio_study_moments` — momento de avaliação do estudo (etapa 07 P-04). Escopo por empresa.
 *
 * @property int $id
 * @property int $business_id
 * @property int $study_id
 * @property string $label
 * @property int $day_offset
 * @property int $window_days
 * @property list<string> $instruments
 * @property int $sort
 */
class StudyMoment extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_study_moments';

    protected $guarded = ['id'];

    protected $casts = [
        'instruments' => 'array',
        'day_offset' => 'integer',
        'window_days' => 'integer',
        'sort' => 'integer',
    ];
}
