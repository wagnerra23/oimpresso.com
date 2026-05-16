<?php

namespace Modules\Accounting\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Modules\Accounting\Entities\User;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 15 D1 MT rescue; child de User via transfer_by_id)

    /**
     * Parent relation pra ScopeByBusinessViaParent — User tem business_id direto.
     */
    protected string $businessParentRelation = 'transfer_by';

    protected $fillable = [
        'journal_transaction_number',
        'transfer_from_id',
        'transfer_to_id',
        'transfer_by_id',
        'amount',
    ];

    public function transfer_from()
    {
        return $this->belongsTo(ChartOfAccount::class, 'transfer_from_id');
    }

    public function transfer_to()
    {
        return $this->belongsTo(ChartOfAccount::class, 'transfer_to_id');
    }

    public function transfer_by()
    {
        return $this->belongsTo(User::class, 'transfer_by_id');
    }

    public function scopeForBusiness($query)
    {
        return $query->whereHas('transfer_by', function ($q) {
            $q->where('business_id', session('business.id'));
        });
    }
}
