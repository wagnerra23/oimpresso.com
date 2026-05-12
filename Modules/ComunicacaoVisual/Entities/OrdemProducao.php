<?php

namespace Modules\ComunicacaoVisual\Entities;

use App\Domain\Fsm\Concerns\GuardsFsmTransitions;
use App\Domain\Fsm\Models\SaleProcessStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * OrdemProducao — Ordem de Produção de Comunicação Visual (FSM canon ADR 0143).
 *
 * Tabela transacional principal SPEC §12.1. Diferente da `Os` legacy (comvis_os):
 * - usa `current_stage_id` FK pra FSM Pipeline canônico (ADR 0143 — LIVE prod biz=1)
 * - trait `GuardsFsmTransitions` bloqueia UPDATE direto em current_stage_id
 *   (gateway obrigatório: ExecuteStageActionService::execute)
 * - schema rico: substrato + dimensões + acabamento + instalação + comissão JSON
 *
 * Side-effects (orquestrados via SaleStageAction.side_effect_class):
 *  - ReservarEstoque   (cliente_aprovou_orcamento → quote_approved)
 *  - ConsumirEstoque   (concluir_impressao → impressao_concluida)
 *  - LiberarReserva    (cancelar_os qualquer não-terminal → cancelled)
 *  - CancelarVendaCascade (cancela NFe SEFAZ + Asaas + Whatsapp + email)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório.
 *
 * @property int         $id
 * @property int         $business_id
 * @property string      $codigo
 * @property int|null    $orcamento_id
 * @property int|null    $contato_id
 * @property int|null    $transaction_id
 * @property int|null    $current_stage_id
 * @property int|null    $substrato_id
 * @property float|null  $largura_m
 * @property float|null  $altura_m
 * @property int         $qtd
 * @property float|null  $area_m2
 * @property array|null  $acabamento_json
 * @property string      $instalacao_tipo
 * @property array|null  $endereco_instalacao_json
 * @property array|null  $equipamentos_necessarios_json
 * @property string|null $arte_url
 * @property \Carbon\Carbon|null $arte_aprovada_em
 * @property string|null $prazo_prometido
 * @property array|null  $commission_distribution_json
 * @property float       $subtotal
 * @property float       $extras
 * @property float       $total
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
class OrdemProducao extends Model
{
    use SoftDeletes;
    use GuardsFsmTransitions;

    protected $table = 'cv_ordens_producao';

    protected $fillable = [
        'business_id',
        'codigo',
        'orcamento_id',
        'contato_id',
        'transaction_id',
        'current_stage_id',
        'substrato_id',
        'largura_m',
        'altura_m',
        'qtd',
        'area_m2',
        'acabamento_json',
        'instalacao_tipo',
        'endereco_instalacao_json',
        'equipamentos_necessarios_json',
        'arte_url',
        'arte_aprovada_em',
        'prazo_prometido',
        'estimated_completion',
        'completed_at',
        'commission_distribution_json',
        'subtotal',
        'extras',
        'total',
        'observacoes',
    ];

    protected $casts = [
        'business_id'                   => 'integer',
        'orcamento_id'                  => 'integer',
        'contato_id'                    => 'integer',
        'transaction_id'                => 'integer',
        'current_stage_id'              => 'integer',
        'substrato_id'                  => 'integer',
        'largura_m'                     => 'decimal:3',
        'altura_m'                      => 'decimal:3',
        'qtd'                           => 'integer',
        'area_m2'                       => 'decimal:3',
        'acabamento_json'               => 'array',
        'endereco_instalacao_json'      => 'array',
        'equipamentos_necessarios_json' => 'array',
        'arte_aprovada_em'              => 'datetime',
        'prazo_prometido'               => 'date',
        'estimated_completion'          => 'datetime',
        'completed_at'                  => 'datetime',
        'commission_distribution_json'  => 'array',
        'subtotal'                      => 'decimal:2',
        'extras'                        => 'decimal:2',
        'total'                         => 'decimal:2',
    ];

    /** Tipos de instalação suportados (alinhado ENUM da migration). */
    public const INSTALACAO_TIPOS = [
        'cliente_busca',
        'fachada_simples',
        'fachada_andaime',
        'fachada_nr35',
        'entrega_apenas',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('cv_ordens_producao.business_id', $businessId);
            }
        });

        static::creating(function (OrdemProducao $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function substrato(): BelongsTo
    {
        return $this->belongsTo(Substrato::class, 'substrato_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(SaleProcessStage::class, 'current_stage_id');
    }

    public function instalacoes(): HasMany
    {
        return $this->hasMany(Instalacao::class, 'ordem_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Contact::class, 'contato_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }
}
