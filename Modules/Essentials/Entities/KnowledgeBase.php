<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KnowledgeBase extends Model
{
    // Wave 11 LGPD (D7.b) — audit trail Spatie ActivityLog.
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
    protected $table = 'essentials_kb';

    /**
     * Wave 11 LGPD (D7.b) — log mudanças em KB (title/parent_id/description).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'parent_id', 'description', 'business_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get all the children of the knowledge base.
     */
    public function children()
    {
        return $this->hasMany(\Modules\Essentials\Entities\KnowledgeBase::class, 'parent_id');
    }

    public function users()
    {
        return $this->belongsToMany(\App\User::class, 'essentials_kb_users', 'kb_id', 'user_id');
    }
}
