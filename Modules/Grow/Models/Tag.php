<?php

namespace Modules\Grow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    'invoice' => 'Modules\Grow\Models\Invoice',
    'project' => 'Modules\Grow\Models\Project',
    'client' => 'Modules\Grow\Models\Client',
    'lead' => 'Modules\Grow\Models\Lead',
    'task' => 'Modules\Grow\Models\Task',
    'estimate' => 'Modules\Grow\Models\Estimate',
    'ticket' => 'Modules\Grow\Models\Ticket',
    'contract' => 'Modules\Grow\Models\Contract',
    'note' => 'Modules\Grow\Models\Note',
    'file' => 'Modules\Grow\Models\File',
    'attachment' => 'Modules\Grow\Models\Attachment',
]);

class Tag extends Model {

    /**
     * @primaryKey string - primry key column.
     * @dateFormat string - date storage format
     * @guarded string - allow mass assignment except specified
     * @CREATED_AT string - creation date column
     * @UPDATED_AT string - updated date column
     */
    protected $primaryKey = 'tag_id';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $guarded = ['tag_id'];
    const CREATED_AT = 'tag_created';
    const UPDATED_AT = 'tag_updated';

    /**
     * relatioship business rules:
     *         - the Creator (user) can have many Taags
     *         - the Tag belongs to one Creator (user)
     */
    public function creator() {
        return $this->belongsTo('Modules\Grow\Models\User', 'tag_creatorid', 'id');
    }

    /**
     * relatioship business rules:
     *   - clients, project etc can have many Tags
     *   - the Tag can be belong to just one of the above
     *   - Tags table columns named as [tagresource_type tagresource_id]
     */
    public function tagresource() {
        return $this->morphTo();
    }

}
