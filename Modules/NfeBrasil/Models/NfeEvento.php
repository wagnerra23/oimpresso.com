<?php

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Evento SEFAZ aplicado a uma NfeEmissao — append-only log.
 *
 * Tipos canônicos (nó tag tpEvento da NFe):
 *   110110 — Carta de Correção Eletrônica (CCe)
 *   110111 — Cancelamento (cStat=135 = autorizado)
 *   110140 — EPEC (Emissão Prévia em Contingência)
 *   210200 — Confirmação da operação (manifesto destinatário)
 *   210210 — Ciência da operação
 *   210220 — Desconhecimento da operação
 *   210240 — Operação não realizada
 *
 * Sem updated_at (append-only). Reprocessamento = novo evento.
 *
 * Wave 18 D7: LogsActivity ativo — auditoria LGPD Art. 37 (accountability).
 * Loga apenas tipo/status/cstat_evento + justificativa truncada (sem payload_json
 * completo — XML evento fica em arquivos table). PII fiscal preservada por
 * exceção CONFAZ SINIEF 07/2005 Art. 14 (documento fiscal imutável).
 */
class NfeEvento extends Model
{
    use HasBusinessScope;
    use LogsActivity; // Wave 18 D7 — audit trail eventos SEFAZ (cancelamento/CCe/manifestação)

    public const UPDATED_AT = null; // append-only

    protected $table = 'nfe_eventos';

    protected $fillable = [
        'business_id', 'emissao_id',
        'tipo', 'justificativa',
        'status', 'cstat_evento', 'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function emissao(): BelongsTo
    {
        return $this->belongsTo(NfeEmissao::class, 'emissao_id');
    }

    public function isAutorizado(): bool
    {
        return $this->status === 'autorizado';
    }

    /**
     * D7 audit trail — accountability LGPD Art. 37.
     * Loga apenas tipo/status/cstat_evento + truncated justificativa (sem
     * payload_json completo — XML body fica em arquivos table).
     * Ver memory/requisitos/NfeBrasil/PII-LGPD-FISCAL.md §3.1
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tipo', 'status', 'cstat_evento', 'emissao_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('nfe_evento');
    }
}
