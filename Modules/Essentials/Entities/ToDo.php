<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ToDo extends Model
{
    // Wave 11 LGPD (D7.b) — audit trail Spatie ActivityLog (LGPD Art. 37 — registro de operações).
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Wave 11 LGPD (D7.b) — ActivityLog Spatie v4 API.
     * Loga apenas atributos relevantes (sem timestamps), só quando há diff.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['task', 'status', 'priority', 'date', 'end_date', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
