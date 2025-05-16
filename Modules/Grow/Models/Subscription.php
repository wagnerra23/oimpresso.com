<?php

namespace Modules\Grow\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model {

    /**
     * @primaryKey string - primry key column.
     * @dateFormat string - date storage format
     * @guarded string - allow mass assignment except specified
     * @CREATED_AT string - creation date column
     * @UPDATED_AT string - updated date column
     */

    //protected $table = 'projects_assigned';
    protected $primaryKey = 'subscription_id';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $guarded = ['subscription_id'];
    const CREATED_AT = 'subscription_created';
    const UPDATED_AT = 'subscription_updated';

    /**
     * relatioship business rules:
     *         - the Creator (user) can have many Invoices
     *         - the Invoice belongs to one Creator (user)
     */
    public function creator() {
        return $this->belongsTo('Modules\Grow\Models\User', 'subscription_creatorid', 'id');
    }

    /**
     * relatioship business rules:
     *         - the Invoice belongs to one Client
     */
    public function client() {
        return $this->belongsTo('Modules\Grow\Models\Client', 'subscription_clientid', 'client_id');
    }

    /**
     * relatioship business rules:
     *         - the Invoice belongs to one Project
     */
    public function project() {
        return $this->belongsTo('Modules\Grow\Models\Project', 'subscription_projectid', 'project_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Invoices
     *         - the Invoice belongs to one Category
     */
    public function category() {
        return $this->belongsTo('Modules\Grow\Models\Category', 'subscription_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Subscription can have many Invoices
     *         - the Invoice belongs to one Subscription
     */
    public function invoices() {
        return $this->hasMany('Modules\Grow\Models\Invoice', 'bill_subscriptionid', 'subscription_id');
    }

    /**
     * relatioship business rules:
     *         - the Subscription can have many Payments
     *         - the Payments belongs to one Subscription
     */
    public function payments() {
        return $this->hasMany('Modules\Grow\Models\Payment', 'payment_subscriptionid', 'subscription_id');
    }

    /**
     * relatioship business rules:
     *         - the Invoice can have many Tags
     *         - the Tags belongs to one Invoice
     *         - other tags can belong to other tables
     */
    public function tags() {
        return $this->morphMany('Modules\Grow\Models\Tag', 'tagresource');
    }

    /**
     * relatioship business rules:
     *         - the Subscriotion can have many Logs
     *         - the Logs belongs to one Subscriotion
     */
    public function logs() {
        return $this->morphMany('Modules\Grow\Models\Log', 'logresource');
    }

    /**
     * display format for invoice id - adding leading zeros & with any set prefix
     * e.g. INV-000001
     */
    public function getFormattedSubscriptionidAttribute() {
        return runtimeSubscriptionIdFormat($this->subscription_id);
    }

    /**
     */
    public function taxes() {
        return $this->morphMany('Modules\Grow\Models\Tax', 'taxresource');
    }

}
