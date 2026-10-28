<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<AccountType, $this> */
    public function sub_types(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\AccountType::class, 'parent_account_type_id');
    }

    public function parent_account()
    {
        return $this->belongsTo(\App\AccountType::class, 'parent_account_type_id');
    }
}
