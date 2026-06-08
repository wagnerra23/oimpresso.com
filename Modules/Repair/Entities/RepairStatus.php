<?php

namespace Modules\Repair\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RepairStatus extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (defesa-em-profundidade)
    use LogsActivity; // D7.b LGPD — audit trail status etapa OS (Wave S Batch 2)

    /**
     * Spatie ActivityLog — registra mudanças no status de etapa OS.
     *
     * Complementa sale_stage_history FSM (ADR 0143) — este loga mudanças
     * no catálogo de status; o FSM loga transições aplicadas a JobSheet.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'sort_order',
                'is_completed_status',
                'sms_template',
                'email_subject',
                'email_body',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('repair.status');
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public static function forDropdown($business_id, $include_attributes = false)
    {
        $query = RepairStatus::where('business_id', $business_id)
                    ->orderBy('sort_order', 'asc')
                    ->get();

        $statuses = $query->pluck('name', 'id');

        //Add sms, email template as attribute
        $template_attr = null;
        if ($include_attributes) {
            $template_attr = collect($query)->mapWithKeys(function ($status) {
                return [$status->id => [
                    'data-sms_template' => $status->sms_template ?? '',
                    'data-email_subject' => $status->email_subject ?? '',
                    'data-email_body' => $status->email_body ?? '',
                    'data-is_completed_status' => $status->is_completed_status,
                ],
                ];
            })->all();
        }

        $output = ['statuses' => $statuses, 'template' => $template_attr];

        return $output;
    }

    public static function getRepairSatuses($business_id)
    {
        $list = RepairStatus::where('business_id', $business_id)
                        ->orderBy('sort_order', 'asc')
                        ->get();

        return $list;
    }
}
