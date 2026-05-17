<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Instalacao — execução de instalação (fachada/equipe/comprovação) SPEC §12.1.
 *
 * Tabela execução com:
 * - equipe (JSON snapshot — promover quando ≥3 instalacoes/dia)
 * - foto pré/pós + assinatura cliente (LGPD opt-in obrigatório — anti-hook #7 charter)
 * - GPS coords (NR-35 comprovação trabalho em altura)
 * - nfse_emissao_id (trigger pós instalacao_aceita dispara NFSe US-COMVIS-008)
 * - comissao_calculada (Job CalcularComissaoOsJob lê snapshot)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório.
 *
 * @property int         $id
 * @property int         $business_id
 * @property int         $ordem_id
 * @property int|null    $catalogo_id
 * @property array|null  $equipe_user_ids_json
 * @property \Carbon\Carbon|null $data_agendada
 * @property \Carbon\Carbon|null $data_realizada
 * @property array|null  $endereco_json
 * @property string|null $foto_pre_url
 * @property string|null $foto_pos_url
 * @property string|null $assinatura_cliente_url
 * @property string|null $lat_lng_inicio
 * @property string|null $lat_lng_fim
 * @property int|null    $nfse_emissao_id
 * @property float|null  $comissao_calculada
 * @property string      $status   agendada|em_execucao|concluida|cancelada|aguardando_reagendamento
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1 US-COMVIS-007
 */
class Instalacao extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * Audit trail Spatie ActivityLog (Wave 26 D7 LGPD compliance).
     * Whitelist exclui equipe_user_ids_json (PII operadores), endereco_json (cliente),
     * assinatura_cliente_url e foto_*_url (mídia PII). Apenas status + datas + comissão.
     * AuditTrailIntegrityTest reforça whitelist.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'data_agendada', 'data_realizada', 'nfse_emissao_id', 'comissao_calculada'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comvis.instalacao');
    }

    protected $table = 'cv_instalacoes';

    protected $fillable = [
        'business_id',
        'ordem_id',
        'catalogo_id',
        'equipe_user_ids_json',
        'data_agendada',
        'data_realizada',
        'endereco_json',
        'foto_pre_url',
        'foto_pos_url',
        'assinatura_cliente_url',
        'lat_lng_inicio',
        'lat_lng_fim',
        'nfse_emissao_id',
        'comissao_calculada',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'business_id'          => 'integer',
        'ordem_id'             => 'integer',
        'catalogo_id'          => 'integer',
        'nfse_emissao_id'      => 'integer',
        'equipe_user_ids_json' => 'array',
        'endereco_json'        => 'array',
        'data_agendada'        => 'datetime',
        'data_realizada'       => 'datetime',
        'comissao_calculada'   => 'decimal:2',
    ];

    public const STATUSES = [
        'agendada',
        'em_execucao',
        'concluida',
        'cancelada',
        'aguardando_reagendamento',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('cv_instalacoes.business_id', $businessId);
            }
        });

        static::creating(function (Instalacao $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemProducao::class, 'ordem_id');
    }

    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(InstalacaoCatalogo::class, 'catalogo_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeAgendadas(Builder $query): Builder
    {
        return $query->where('cv_instalacoes.status', 'agendada');
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->whereIn('cv_instalacoes.status', ['agendada', 'aguardando_reagendamento']);
    }
}
