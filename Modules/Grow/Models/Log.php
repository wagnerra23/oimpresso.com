<?php

namespace Modules\Grow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    'contract' => 'Modules\Grow\Models\Contract',
    'subscription' => 'Modules\Grow\Models\Subscription',
    'invoice' => 'Modules\Grow\Models\Invoice',

]);

class Log extends Model {

    /**
     * @primaryKey string - primry key column.
     * @dateFormat string - date storage format
     * @guarded string - allow mass assignment except specified
     * @CREATED_AT string - creation date column
     * @UPDATED_AT string - updated date column
     */
    
    protected $primaryKey = 'log_id';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $guarded = ['log_id'];
    const CREATED_AT = 'log_created';
    const UPDATED_AT = 'log_updated';


}
