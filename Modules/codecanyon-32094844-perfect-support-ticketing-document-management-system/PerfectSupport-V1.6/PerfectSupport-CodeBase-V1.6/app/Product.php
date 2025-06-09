<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{   
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
    
    /**
     * The source that belong to the product.
     */
    public function sources()
    {
        return $this->belongsToMany('App\Source', 'product_sources');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', '=', 1);
    }

    /**
     * The support agents that belong to the product.
     */
    public function supportAgents()
    {
        return $this->belongsToMany('App\User', 'product_support_agents');
    }

    /**
     * Get the departments for the product.
     */
    public function productDepartments()
    {
        return $this->hasMany('App\ProductDepartment');
    }
    
    public static function getDropdown($includeAll = false, $is2dArray = false)
    {
        $query = Product::Active();

        if(!$is2dArray) {
            $products = $query->pluck('name', 'id');
        }

        if($is2dArray) {
            $products = $query->select('name', 'id')
                        ->get()->toArray();
        }

        if ($includeAll && !$is2dArray) {
            $products->prepend(__('messages.all'), '');
        }

        if($is2dArray && $includeAll) {
            $products = array_merge([['name' => __('messages.all'), 'id' => '']], $products);
        }

        return $products;

    }
}
