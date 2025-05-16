<?php

namespace Modules\Grow\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model {

    /**
     * @primaryKey string - primry key column.
     * @dateFormat string - date storage format
     * @guarded string - allow mass assignment except specified
     * @CREATED_AT string - creation date column
     * @UPDATED_AT string - updated date column
     */
    protected $table = 'categories';
    protected $primaryKey = 'category_id';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $guarded = ['category_id'];
    const CREATED_AT = 'category_created';
    const UPDATED_AT = 'category_updated';

    /**
     * relatioship business rules:
     *         - the Creator (user) can have many Categories
     *         - the Category belongs to one Creator (user)
     */
    public function creator() {
        return $this->belongsTo('Modules\Grow\Models\User', 'category_creatorid', 'id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Projects
     *         - the Project belongs to one Category
     */
    public function projects() {
        return $this->hasMany('Modules\Grow\Models\Project', 'project_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Projects
     *         - the Project belongs to one Category
     */
    public function leads() {
        return $this->hasMany('Modules\Grow\Models\Lead', 'lead_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Projects
     *         - the Project belongs to one Category
     */
    public function clients() {
        return $this->hasMany('Modules\Grow\Models\Client', 'client_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Projects
     *         - the Project belongs to one Category
     */
    public function invoices() {
        return $this->hasMany('Modules\Grow\Models\Invoice', 'bill_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Estimates
     *         - the Estimate belongs to one Category
     */
    public function estimates() {
        return $this->hasMany('Modules\Grow\Models\Estimate', 'bill_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Contracts
     *         - the Contract belongs to one Category
     */
    public function contracts() {
        return $this->hasMany('Modules\Grow\Models\Contract', 'doc_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Proposal
     *         - the Contract belongs to one Category
     */
    public function proposals() {
        return $this->hasMany('Modules\Grow\Models\Proposal', 'doc_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Expenses
     *         - the Expense belongs to one Category
     */
    public function expenses() {
        return $this->hasMany('Modules\Grow\Models\Expense', 'expense_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Tickets
     *         - the Ticket belongs to one Category
     */
    public function tickets() {
        return $this->hasMany('Modules\Grow\Models\Ticket', 'ticket_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many articles
     *         - the Article belongs to one Category
     */
    public function articles() {
        return $this->hasMany('Modules\Grow\Models\Knowledgebase', 'knowledgebase_categoryid', 'category_id');
    }

    /**
     * The Users that are assigned to the Task.
     */
    public function users() {
        return $this->belongsToMany('Modules\Grow\Models\User', 'category_users', 'categoryuser_categoryid', 'categoryuser_userid');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many items
     *         - the Items belongs to one Category
     */
    public function items() {
        return $this->hasMany('Modules\Grow\Models\Item', 'item_categoryid', 'category_id');
    }

    /**
     * relatioship business rules:
     *         - the Category can have many Canned
     *         - the Canned belongs to one Category
     */
    public function canned() {
        return $this->hasMany('Modules\Grow\Models\Canned', 'canned_categoryid', 'category_id');
    }

    /**
     * count: canned messages
     * @usage $category->count_canned
     */
    public function getCountCannedAttribute() {
        //uses notifications relationship (above)
        return $this->canned->count();
    }
}
