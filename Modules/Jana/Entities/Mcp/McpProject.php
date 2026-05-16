<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Project = unidade superior de agrupamento. Tasks têm identifier humano
 * "<key>-<NNNN>" gerado a partir de next_task_number.
 *
 * D7 LGPD audit trail — Wave 17 (2026-05-16): LogsActivity rastreia mudanças
 * de status/lead/settings — projeto é raiz da hierarquia, audit vital.
 *
 * @property int     $id
 * @property string  $key             COPI, NFSE, FIN, INFRA
 * @property string  $name
 * @property ?string $description
 * @property ?int    $lead_user_id
 * @property ?string $color
 * @property ?string $icon
 * @property string  $status          active|archived
 * @property array   $settings
 * @property ?int    $default_workflow_id
 * @property array   $custom_field_schema
 * @property int     $next_task_number
 */
class McpProject extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'mcp_jira_projects';

    protected $fillable = [
        'key', 'name', 'description', 'lead_user_id',
        'color', 'icon', 'status', 'settings',
        'default_workflow_id', 'custom_field_schema', 'next_task_number',
    ];

    protected $casts = [
        'settings' => 'array',
        'custom_field_schema' => 'array',
        'next_task_number' => 'int',
    ];

    public const STATUSES = ['active', 'archived'];

    public function epics()
    {
        return $this->hasMany(McpEpic::class, 'project_id');
    }

    public function cycles()
    {
        return $this->hasMany(McpCycle::class, 'project_id');
    }

    public function components()
    {
        return $this->hasMany(McpComponent::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(McpTask::class, 'project_id');
    }

    /**
     * Aloca atomicamente o próximo número de task identifier (ex: COPI-124).
     * Usa lock pessimista pra evitar collision em chamadas paralelas.
     */
    public function allocateNextIdentifier(): string
    {
        return DB::transaction(function () {
            /** @var self $project */
            $project = self::query()->lockForUpdate()->findOrFail($this->id);
            $next = $project->next_task_number;
            $project->next_task_number = $next + 1;
            $project->save();
            return $project->key . '-' . $next;
        });
    }

    public function activeCycle(): ?McpCycle
    {
        return $this->cycles()->where('status', 'active')->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('mcp_project')
            ->logOnly(['status', 'lead_user_id', 'name', 'key', 'default_workflow_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
