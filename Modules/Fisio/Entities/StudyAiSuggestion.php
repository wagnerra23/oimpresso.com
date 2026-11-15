<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_study_ai_suggestions` — sugestão do assistente da pesquisadora (etapa 07 P-08).
 * Escopo por empresa; só a área do estudo lê. Não é apagada.
 *
 * @property int $id
 * @property int $business_id
 * @property int $study_id
 * @property string $kind
 * @property string $text
 * @property string $status
 * @property ?int $decided_by
 * @property ?\Illuminate\Support\Carbon $decided_at
 */
class StudyAiSuggestion extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const TIPOS = ['qualidade', 'resumo', 'padrao'];

    public const PENDENTE = 'pendente';

    public const ACEITA = 'aceita';

    public const DESCARTADA = 'descartada';

    protected $table = 'fisio_study_ai_suggestions';

    protected $guarded = ['id'];

    protected $casts = ['decided_at' => 'datetime'];
}
