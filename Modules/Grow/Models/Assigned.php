<?php

namespace Modules\Grow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    'project' => 'Modules\Grow\Models\Project',
    'task' => 'Modules\Grow\Models\Task',
    'lead' => 'Modules\Grow\Models\Lead',
    'webform' => 'Modules\Grow\Models\WebForm',

]);

class Assigned extends Model {

    /**
     * @primaryKey string - primry key column.
     * @dateFormat string - date storage format
     * @guarded string - allow mass assignment except specified
     * @CREATED_AT string - creation date column
     * @UPDATED_AT string - updated date column
     */
    protected $table = 'assigned';
    protected $primaryKey = 'assigned_id';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $guarded = ['assigned_id'];
    const CREATED_AT = 'assigned_created';
    const UPDATED_AT = 'assigned_updated';

    /**
     * relatioship business rules:
     *         - the Creator (user) can have many Attachments
     *         - the Attachment belongs to one User
     */
    public function creator() {
        return $this->belongsTo('Modules\Grow\Models\User', 'assigned_creatorid', 'id');
    }

    /**
     * relatioship business rules:
     *   - projects, tasks etc can have many assigned
     *   - the assigned can be belong to just one of the above
     *   - assigned table columns named as [assignedresource_type assignedresource_id]
     */
    public function assignedresource() {
        return $this->morphTo();
    }

}
