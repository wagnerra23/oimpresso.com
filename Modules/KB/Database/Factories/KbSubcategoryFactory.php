<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbSubcategory;

/**
 * Factory de KbSubcategory.
 * TODO[CL]: confirmar com Agent A FQCN exato do Model.
 */
class KbSubcategoryFactory extends Factory
{
    protected $model = KbSubcategory::class;

    public function definition(): array
    {
        static $i = 0; $i++;
        return [
            'business_id' => 1,
            'category_id' => null,  // OBRIGATÓRIO passar via state()
            'slug'        => sprintf('subcat-%04d', $i),
            'label'       => "Subcategoria {$i}",
            'description' => null,
            'auto_match'  => null,
        ];
    }
}
