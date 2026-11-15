<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `fisio_laudos_bps_equipe` — profissional que assina o laudo biopsicossocial, com a área do
 * formulário de SC. Escopo por empresa (ADR 0093).
 *
 * `sistema`: o usuário da clínica assina com o CREFITO do perfil (área fisioterapia_to).
 * `externa`: profissional sem usuário (médico, enfermeiro, psicólogo, assistente social de fora);
 * a clínica registra nome e registro, e o PDF sai com espaço para a assinatura e o carimbo dele.
 *
 * Enquanto o laudo não é finalizado a equipe pode mudar; depois, o banco recusa (trigger).
 *
 * @property int $id
 * @property int $business_id
 * @property int $laudo_id
 * @property string $area
 * @property string $nome
 * @property string $registro
 * @property string $assinatura
 * @property int $incluido_por
 */
class LaudoBiopsicossocialEquipe extends Model
{
    use HasBusinessScope;

    /** Áreas do formulário, na ordem dele. */
    public const AREAS = [
        'medicina' => 'Medicina',
        'enfermagem' => 'Enfermagem',
        'psicologia' => 'Psicologia',
        'servico_social' => 'Serviço Social',
        'fisioterapia_to' => 'Fisioterapia/Terapia Ocupacional',
    ];

    public const ASSINATURA_SISTEMA = 'sistema';

    public const ASSINATURA_EXTERNA = 'externa';

    protected $table = 'fisio_laudos_bps_equipe';

    protected $guarded = ['id'];

    /** @return BelongsTo<LaudoBiopsicossocial, $this> */
    public function laudo(): BelongsTo
    {
        return $this->belongsTo(LaudoBiopsicossocial::class, 'laudo_id');
    }
}
