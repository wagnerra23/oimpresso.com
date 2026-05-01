<?php

namespace Modules\NFSe\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

trait NfseBusinessScope
{
    public static function bootNfseBusinessScope(): void
    {
        static::addGlobalScope(new class implements Scope {
            public function apply(Builder $builder, Model $model): void
            {
                if (! session()->has('user.business_id')) {
                    return;
                }
                if (auth()->check() && auth()->user()->can('superadmin')) {
                    return;
                }
                $builder->where($model->getTable() . '.business_id', session('user.business_id'));
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->business_id) && session()->has('user.business_id')) {
                $model->business_id = session('user.business_id');
            }
        });
    }
}
