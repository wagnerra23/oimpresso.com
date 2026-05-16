<?php

namespace Modules\Jana\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Sugestao — proposta de meta gerada pela IA.
 *
 * Quando o gestor escolhe, `meta_id` é preenchido com a Meta criada.
 * Quando rejeita, `rejeitada_em` marca — feedback passivo pro prompt futuro.
 *
 * Multi-tenant Tier 0 (ADR 0093): tenancy herdada via parent `conversa`
 * (jana_conversas.business_id) — defesa em profundidade Wave 7.
 *
 * D7 LGPD audit trail — Wave 10 (2026-05-16): LogsActivity registra
 * escolhida/rejeitada (sinal de decisão humana) sem logar `payload_json`
 * (pode conter contexto livre). Cumpre ADR 0035 stack IA canônica.
 */
class Sugestao extends Model
{
    use BelongsToBusinessViaParent;
    use LogsActivity;

    protected $table = 'jana_sugestoes';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'conversa';

    protected $fillable = [
        'conversa_id', 'meta_id', 'payload_json', 'escolhida_em', 'rejeitada_em',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'escolhida_em' => 'datetime',
        'rejeitada_em' => 'datetime',
    ];

    /**
     * D7 LGPD audit — registra ciclo de decisão (meta_id, escolhida_em,
     * rejeitada_em). NÃO loga payload_json (texto livre do prompt original).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('jana_sugestao')
            ->logOnly(['meta_id', 'escolhida_em', 'rejeitada_em'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function conversa()
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
