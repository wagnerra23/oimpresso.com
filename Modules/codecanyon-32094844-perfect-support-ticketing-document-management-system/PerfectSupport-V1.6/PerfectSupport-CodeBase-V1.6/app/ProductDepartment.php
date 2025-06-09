<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductDepartment extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the support agents for the department.
     */
    public function supportAgents()
    {
        return $this->belongsToMany('App\User', 'product_department_support_agents');
    }

    /**
     * Get the post that owns the comment.
     */
    public function department()
    {
        return $this->belongsTo('App\Department');
    }

    public static function getDepartmentForProduct($product_id = null)
    {
        $query = ProductDepartment::with('department');

        if (!empty($product_id)) {
            $query->where('product_id', $product_id);
        }
                            
        $p_departments = $query
                            ->groupBy('department_id')
                            ->get();

        $departments = [];
        if (!empty($p_departments)) {
            foreach ($p_departments as $product_department) {
                if (!empty($product_department->department)) {
                    $departments[$product_department->id] = $product_department->department->name;
                }
            }
        }

        return $departments;
    }
}
