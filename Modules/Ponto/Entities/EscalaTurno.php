<?php

namespace Modules\Ponto\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wave 18 RETRY D1 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 *
 * `ponto_escala_turnos` NÃO tem business_id direto — turno é child de Escala
 * (cuja tabela `ponto_escalas` carrega o business_id). Trait
 * `BelongsToBusinessViaParent` injeta whereHas('escala') automático no scope global,
 * impedindo leak cross-tenant de turnos via query direta a EscalaTurno.
 *
 * Padrão idêntico a Modules/Accounting/Entities/Transfer (parent User com business_id).
 */
class EscalaTurno extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 RETRY D1 MT — child via Escala)
    use HasFactory;

    protected $table = 'ponto_escala_turnos';

    /**
     * Parent relation pra ScopeByBusinessViaParent — Escala tem business_id direto.
     */
    protected string $businessParentRelation = 'escala';

    protected $fillable = [
        'escala_id',
        'dia_semana',
        'hora_entrada',
        'hora_almoco_inicio',
        'hora_almoco_fim',
        'hora_saida',
    ];

    public function escala(): BelongsTo
    {
        return $this->belongsTo(Escala::class, 'escala_id');
    }
}
