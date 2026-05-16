<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbFavorite;

class KbFavoriteFactory extends Factory
{
    protected $model = KbFavorite::class;

    public function definition(): array
    {
        return [
            'business_id' => 1,
            'user_id'     => null,  // OBRIGATORIO
            'node_id'     => null,  // OBRIGATORIO
        ];
    }
}
