<?php

namespace Modules\ComunicacaoVisual\Entities;

use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Apontamento — registro de produção (spool plotter / apontamento OS).
 *
 * Cada linha registra um intervalo de trabalho em uma OS de comunicação visual.
 * O operador inicia o apontamento ao começar a produção e finaliza ao terminar,
 * informando os m² produzidos. O sistema calcula drift vs m² orçado.
 *
 * Padrão append-only (sem SoftDeletes):
 *   Apontamento é registro legal de produção — não pode ser deletado ou editado.
 *   Correções geram novo apontamento via ApontamentoTracker::corrigir() (US-COMVIS-004b futura).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório — filtra por business_id da sessão automaticamente.
 *
 * @property int            $id
 * @property int            $business_id
 * @property int            $os_id
 * @property int|null       $orcamento_item_id
 * @property int            $operador_id
 * @property string|null    $maquina
 * @property \Carbon\Carbon $iniciado_em
 * @property \Carbon\Carbon|null $finalizado_em
 * @property int|null       $duracao_segundos
 * @property float|null     $m2_produzido
 * @property float|null     $m2_orcado
 * @property float|null     $drift_percent
 * @property string|null    $observacoes
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-004
 */
class Apontamento extends Model
{
    // Sem SoftDeletes — append-only, registro legal de produção
    use LogsActivity;

    /**
     * Audit trail Spatie ActivityLog (Wave 18 D7 LGPD compliance).
     *
     * Como Apontamento é append-only, log captura apenas updates raros
     * (correção de drift, ajuste m²) — essenciais pra audit produtivo.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['finalizado_em', 'duracao_segundos', 'm2_produzido', 'drift_percent'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comvis.apontamento');
    }


    protected $table = 'comvis_apontamentos';

    protected $fillable = [
        'business_id',
        'os_id',
        'orcamento_item_id',
        'operador_id',
        'maquina',
        'iniciado_em',
        'finalizado_em',
        'duracao_segundos',
        'm2_produzido',
        'm2_orcado',
        'drift_percent',
        'observacoes',
    ];

    protected $casts = [
        'business_id'       => 'integer',
        'os_id'             => 'integer',
        'orcamento_item_id' => 'integer',
        'operador_id'       => 'integer',
        'iniciado_em'       => 'datetime',
        'finalizado_em'     => 'datetime',
        'duracao_segundos'  => 'integer',
        'm2_produzido'      => 'decimal:3',
        'm2_orcado'         => 'decimal:3',
        'drift_percent'     => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('comvis_apontamentos.business_id', $businessId);
            }
        });

        static::creating(function (Apontamento $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * OS a que pertence este apontamento.
     */
    public function os(): BelongsTo
    {
        return $this->belongsTo(Os::class, 'os_id');
    }

    /**
     * Item do orçamento vinculado (pode ser null).
     */
    public function orcamentoItem(): BelongsTo
    {
        return $this->belongsTo(OrcamentoItem::class, 'orcamento_item_id');
    }

    /**
     * Operador que realizou o apontamento.
     */
    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    // ------------------------------------------------------------------
    // Accessors / Helpers
    // ------------------------------------------------------------------

    /**
     * Duração formatada como "02h 15m 30s".
     * Retorna null se duracao_segundos ainda não foi calculado (em andamento).
     */
    public function getDuracaoFormatadaAttribute(): ?string
    {
        if ($this->duracao_segundos === null) {
            return null;
        }

        $total   = (int) $this->duracao_segundos;
        $horas   = intdiv($total, 3600);
        $minutos = intdiv($total % 3600, 60);
        $segundos = $total % 60;

        return sprintf('%02dh %02dm %02ds', $horas, $minutos, $segundos);
    }

    /**
     * Indica se o apontamento está em andamento (finalizado_em === null).
     */
    public function getEstaEmAndamentoAttribute(): bool
    {
        return $this->finalizado_em === null;
    }
}
