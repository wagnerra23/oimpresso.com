<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbComment;

class KbCommentFactory extends Factory
{
    protected $model = KbComment::class;

    public function definition(): array
    {
        return [
            'business_id'    => 1,
            'node_id'        => null,  // OBRIGATORIO
            'block_idx'      => 0,
            'text'           => 'Comentário de teste.',
            'author_user_id' => null,  // OBRIGATORIO
        ];
    }
}
