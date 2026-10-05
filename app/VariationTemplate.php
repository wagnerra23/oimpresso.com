<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VariationTemplate extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the attributes for the variation.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\VariationValueTemplate, $this>
     */
    public function values(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\VariationValueTemplate::class);
    }

    /**
     * Subconsulta: quantos produtos do negócio usam este modelo de variação. A tela de Cadastros
     * mostra o número e o VariationTemplateController@destroy recusa a exclusão com o mesmo número.
     */
    public static function produtosQueUsam(int $business_id): \Illuminate\Database\Query\Builder
    {
        return \DB::table('product_variations as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->selectRaw('count(distinct pv.product_id)')
            ->whereColumn('pv.variation_template_id', 'variation_templates.id')
            ->where('p.business_id', $business_id);
    }
}
