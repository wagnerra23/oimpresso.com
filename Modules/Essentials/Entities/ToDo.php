<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ToDo extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (defesa-em-profundidade)
    use LogsActivity;

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
    protected $table = 'essentials_to_dos';

    /**
     * Cast das colunas de data. Em Laravel 9+ só timestamps são castados
     * automaticamente — `date`/`end_date` precisam ser declarados explicitamente
     * para que `->format()` e `Carbon` helpers funcionem.
     */
    protected $casts = [
        'date'     => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Auditoria LGPD (D7) — registra mudanças críticas em tarefas.
     * Append-only via spatie/activitylog. NÃO inclui `task` (texto livre,
     * potencial PII de colaborador citado) — só status/priority/atribuição.
     *
     * @see Modules\Essentials\Config\retention.php
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'priority', 'date', 'end_date', 'created_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('essentials.todo');
    }

    public function users()
    {
        return $this->belongsToMany(\App\User::class, 'essentials_todos_users', 'todo_id', 'user_id');
    }

    public function assigned_by()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(\Modules\Essentials\Entities\EssentialsTodoComment::class, 'task_id')->orderBy('id', 'desc');
    }

    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }

    public static function getTaskStatus()
    {
        $statuses = [
            'new' => __('essentials::lang.new'),
            'in_progress' => __('essentials::lang.in_progress'),
            'on_hold' => __('essentials::lang.on_hold'),
            'completed' => __('restaurant.completed'),
        ];

        return $statuses;
    }

    public static function getTaskPriorities()
    {
        $priorities = [
            'low' => __('essentials::lang.low'),
            'medium' => __('essentials::lang.medium'),
            'high' => __('essentials::lang.high'),
            'urgent' => __('essentials::lang.urgent'),
        ];

        return $priorities;
    }

    /**
     * Attributes to be logged for activity
     */
    public function getLogPropertiesAttribute()
    {
        $properties = ['status'];

        return $properties;
    }
}
