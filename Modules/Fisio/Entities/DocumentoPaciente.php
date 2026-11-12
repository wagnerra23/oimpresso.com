<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Arquivos\Concerns\HasArquivos;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_patient_documents` — exame, laudo ou receita que o paciente trouxe (US-FISIO-063).
 *
 * O arquivo mora no `Modules/Arquivos` (HasArquivos, ADR 0123), ligado a esta linha. Escopo por
 * empresa (ADR 0093). Sem DELETE (model e trigger): tirar da lista é `removed_at` com quem e por quê.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property string $kind
 * @property ?\Illuminate\Support\Carbon $document_date
 * @property ?string $note
 * @property string $origin
 * @property string $status
 * @property ?int $created_by
 * @property ?int $reviewed_by
 * @property ?\Illuminate\Support\Carbon $reviewed_at
 * @property ?\Illuminate\Support\Carbon $removed_at
 * @property ?int $removed_by
 * @property ?string $removed_reason
 * @property \Illuminate\Support\Carbon $created_at
 */
class DocumentoPaciente extends Model
{
    use HasArquivos;
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const TIPOS = [
        'exame_imagem' => 'Exame de imagem',
        'exame_laboratorial' => 'Exame laboratorial',
        'laudo_medico' => 'Laudo médico',
        'receita' => 'Receita',
        'outro' => 'Outro',
    ];

    public const ORIGEM_CLINICA = 'clinica';

    public const ORIGEM_PORTAL = 'portal';

    public const NO_PRONTUARIO = 'prontuario';

    public const PENDENTE = 'pendente';

    public const RECUSADO = 'recusado';

    protected $table = 'fisio_patient_documents';

    protected $guarded = ['id'];

    protected $casts = [
        'document_date' => 'date',
        'reviewed_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function foiTirado(): bool
    {
        return $this->removed_at !== null;
    }
}
