<?php

namespace Modules\Repair\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DeviceModel extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (defesa-em-profundidade)
    use LogsActivity; // D7.b LGPD — audit trail catálogo (Wave S Batch 2)

    /**
     * Spatie ActivityLog — registra mudanças no catálogo de modelos de dispositivo.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'device_id',
                'brand_id',
                'repair_checklist',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('repair.device_model');
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'repair_device_models';

    /**
     * The attributes that should be cast to native types.
     *
     * ⚠️ `repair_checklist` NÃO entra aqui, e o motivo é medido — ele já esteve com
     * `=> 'array'` (herança do upstream UltimatePOS, 2025-05-16) e isso QUEBRAVA A
     * COLUNA NOS DOIS SENTIDOS, porque o formato real desta tabela é **string separada
     * por `|`**, nunca JSON:
     *
     *   LEITURA  — o cast fazia `json_decode` da string do legado, falhava e devolvia
     *              `null`. Medido no CT 100: raw `'Tela trincada| Bateria ||Carcaca'`
     *              → accessor `NULL` → `(string)` dele `''`. Todos os `explode('|', …)`
     *              do módulo (8 sites: DataTables, payload Inertia, form de edição,
     *              checklist da OS, 2 Blades do JobSheet) viravam lista VAZIA.
     *   ESCRITA  — o cast fazia `json_encode` da string vinda do textarea. Medido:
     *              `Tela trincada|Bateria` era gravado como `'"Tela trincada|Bateria"'`,
     *              com aspas, corrompendo o dado a cada salvar.
     *
     * Que o formato é pipe está dito em três lugares independentes: a migration
     * (`text`, não json), o tooltip que o produto mostra ao usuário (*"separada por
     * barra vertical (|)"*) e os 8 consumidores, que todos fazem `explode('|')` e
     * nenhum faz `json_decode`.
     *
     * `transactions.repair_checklist` é outra coluna, essa sim JSON
     * (`DataController::50` faz `json_encode`) — não confundir.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * user who added a model.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * get device for model
     */
    public function Device()
    {
        return $this->belongsTo(\App\Category::class, 'device_id');
    }

    /**
     * get Brand for model
     */
    public function Brand()
    {
        return $this->belongsTo(\App\Brands::class, 'brand_id');
    }

    public static function forDropdown($business_id)
    {
        $device_models = DeviceModel::where('business_id', $business_id)
                            ->pluck('name', 'id');

        return $device_models;
    }
}
