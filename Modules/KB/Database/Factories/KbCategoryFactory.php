<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbCategory;

/**
 * Factory de KbCategory.
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §5
 */
class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    public function definition(): array
    {
        static $i = 0; $i++;
        return [
            'business_id' => 1,
            'slug'        => sprintf('cat-%04d', $i),
            'label'       => "Categoria {$i}",
            'description' => null,
            'hue'         => 240,
            'icon'        => null,
            'sort_order'  => 0,
        ];
    }
}
