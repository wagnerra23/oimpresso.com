<?php

namespace Modules\Essentials\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

class EssentialsTodoComment extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant via ToDo->business_id (Wave 18 D1)

    /**
     * Nome da relação parent pra `ScopeByBusinessViaParent` resolver business_id.
     */
    protected string $businessParentRelation = 'task';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function added_by()
    {
        return $this->belongsTo(\App\User::class, 'comment_by');
    }

    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }

    public function task()
    {
        return $this->belongsTo(\Modules\Essentials\Entities\ToDo::class, 'task_id');
    }
}
