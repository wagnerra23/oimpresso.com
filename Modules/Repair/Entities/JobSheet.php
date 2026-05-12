<?php

namespace Modules\Repair\Entities;

use App\Concerns\HasBusinessScope;
use App\Domain\Fsm\Concerns\GuardsFsmTransitions;
use App\Variation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Arquivos\Concerns\HasArquivos;

class JobSheet extends Model
{
    use GuardsFsmTransitions; // US-REP-FSM-003 — bloqueia UPDATE direto em current_stage_id sem ExecuteStageActionService (ADR 0129)
    use HasArquivos; // ADR 0123 — adopcao Sprint 4 (foto OS via arquivos table)
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (defesa-em-profundidade)

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'checklist' => 'array',
        'parts' => 'array',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'repair_job_sheets';

    /**
     * Return the customer for the project.
     */
    public function customer()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    /**
     * user added job sheet.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * technecian for job sheet.
     */
    public function technician()
    {
        return $this->belongsTo(\App\User::class, 'service_staff');
    }

    /**
     * status of job sheet.
     */
    public function status()
    {
        return $this->belongsTo('Modules\Repair\Entities\RepairStatus', 'status_id');
    }

    /**
     * get device for job sheet
     */
    public function Device()
    {
        return $this->belongsTo(\App\Category::class, 'device_id');
    }

    /**
     * get Brand for job sheet
     */
    public function Brand()
    {
        return $this->belongsTo(\App\Brands::class, 'brand_id');
    }

    /**
     * get device model for job sheet
     */
    public function deviceModel()
    {
        return $this->belongsTo('Modules\Repair\Entities\DeviceModel', 'device_model_id');
    }

    /**
     * get business location for job sheet
     */
    public function businessLocation()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    /**
     * Get the repair for the job sheet
     */
    public function invoices()
    {
        return $this->hasMany(\App\Transaction::class, 'repair_job_sheet_id');
    }

    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }

    /**
     * Accessor — fotos de OS via Modules/Arquivos backbone (ADR 0123).
     *
     * Convivência com `media()` legacy:
     * - Sprint 4 US-ARQ-025+: novos uploads passam por arquivos table
     * - Sprint 5 US-ARQ-026: migration backfill move Media → arquivos
     * - Pós-estabilização: deprecate $jobSheet->media() + remove App\Media polymorphic
     *
     * Sub_destination convencional: 'repair-foto'.
     */
    public function getFotoArquivosAttribute(): Collection
    {
        if (! method_exists($this, 'arquivos')) return collect();
        return $this->arquivos()
            ->where('sub_destination', 'repair-foto')
            ->where('bucket', 'active')
            ->get();
    }

    /**
     * Accessor — anexos da OS (fotos + documentos) com preferência backbone.
     *
     * Estratégia:
     * 1. Consulta `arquivos` table (backbone ADR 0123) com sub_destination='repair-foto'
     * 2. Se backbone vazio → fallback transparente para relação `media()` legacy (App\Media)
     *
     * Consumidores (controllers/views) devem usar este accessor em vez de `->media` direto.
     * Protocolo de migração: US-ARQ-029 Sprint 3 ADR 0123 §2.
     *
     * @return \Illuminate\Support\Collection Itens possuem ao menos: id, original_name|display_name, display_url|storage_path, mime_type|media_type
     */
    public function getAnexosAttribute(): Collection
    {
        // Tenta backbone Arquivos primeiro.
        if (method_exists($this, 'arquivos')) {
            $backbone = $this->arquivos()
                ->where('sub_destination', 'repair-foto')
                ->where('bucket', 'active')
                ->get();

            if ($backbone->isNotEmpty()) {
                return $backbone;
            }
        }

        // Fallback graceful — relação Media legacy (Spatie-like morphMany).
        // NÃO remover esta relação enquanto migration backfill não cobrir 100%.
        return $this->media()->get();
    }

    public function getPartsUsed()
    {
        $parts = [];
        if (! empty($this->parts)) {
            $variation_ids = [];
            $job_sheet_parts = $this->parts;

            foreach ($job_sheet_parts as $key => $value) {
                $variation_ids[] = $key;
            }

            $variations = Variation::whereIn('id', $variation_ids)
                                ->with(['product_variation', 'product', 'product.unit'])
                                ->get();

            foreach ($variations as $variation) {
                $parts[$variation->id]['variation_id'] = $variation->id;
                $parts[$variation->id]['variation_name'] = $variation->full_name;
                $parts[$variation->id]['unit'] = $variation->product->unit->short_name;
                $parts[$variation->id]['unit_id'] = $variation->product->unit->id;
                $parts[$variation->id]['quantity'] = $job_sheet_parts[$variation->id]['quantity'];
            }
        }

        return $parts;
    }
}
