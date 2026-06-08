<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SellingPriceGroup extends Model
{
    use LogsActivity, SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('selling_price_group');
    }

    public function scopeActive($query)
    {
        return $query->where('selling_price_groups.is_active', 1);
    }

    /**
     * Return list of selling price groups
     *
     * @param  int  $business_id
     * @return array
     */
    public static function forDropdown($business_id, $with_default = true)
    {
        $price_groups = SellingPriceGroup::where('business_id', $business_id)
                                    ->active()
                                    ->get();

        $dropdown = [];

        if ($with_default && auth()->user()->can('access_default_selling_price')) {
            $dropdown[0] = __('lang_v1.default_selling_price');
        }

        foreach ($price_groups as $price_group) {
            if (auth()->user()->can('selling_price_group.'.$price_group->id)) {
                $dropdown[$price_group->id] = $price_group->name;
            }
        }

        return $dropdown;
    }

    /**
     * Counts total number of selling price groups
     *
     * @param  int  $business_id
     * @return array
     */
    public static function countSellingPriceGroups($business_id)
    {
        $count = SellingPriceGroup::where('business_id', $business_id)
                                ->active()
                                ->count();

        return $count;
    }
}
