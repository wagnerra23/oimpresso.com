<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class EssentialsLeave extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 12 D1 boost)
    use LogsActivity;

    protected static $logAttributes = ['*'];

    protected static $logAttributesToIgnore = ['created_at', 'updated_at'];

    protected static $logOnlyDirty = true;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
    
    /**
     * As três relations declaram o tipo de retorno desde 2026-09-05 (PR-9 HRM-O7).
     * Sem ele o Larastan não reconhece a relation: `with('user')` virava
     * "Relation 'user' is not found" e `$leave->user` virava "undefined property",
     * o que empurrava todo consumidor novo pro phpstan-baseline. Tipar aqui, uma
     * vez, é mais barato que ignorar o mesmo erro em cada call-site.
     *
     * @return BelongsTo<\App\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\User::class);
    }

    /** @return BelongsTo<EssentialsLeaveType, $this> */
    public function leave_type(): BelongsTo
    {
        return $this->belongsTo(EssentialsLeaveType::class, 'essentials_leave_type_id');
    }

    /** @return BelongsTo<\App\User, $this> */
    public function changed_by_user(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'changed_by');
    }
}
