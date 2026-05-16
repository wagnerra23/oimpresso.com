<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbPath;

/**
 * Factory de KbPath (trilha de aprendizado).
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §6
 */
class KbPathFactory extends Factory
{
    protected $model = KbPath::class;

    public function definition(): array
    {
        static $i = 0; $i++;
        return [
            'business_id'    => 1,
            'slug'           => sprintf('path-%04d', $i),
            'title'          => "Trilha {$i}",
            'audience'       => 'Wagner onboarding governança',
            'description'    => null,
            'hue'            => 240,
            'status'         => 'published',
            'author_user_id' => null,
        ];
    }

    public function draft(): self
    {
        return $this->state(['status' => 'draft']);
    }
}
