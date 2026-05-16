<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbNodeVersion;

/**
 * Factory de KbNodeVersion (snapshot append-only).
 *
 * IMPORTANTE: a factory pode CRIAR a row via raw DB insert pra evitar
 * que o Observer append-only rejeite a criação inicial. Tests devem
 * preferir usar a factory pra cenários de leitura/listagem; pra cenários
 * de "Service grava versão" use o Service direto.
 */
class KbNodeVersionFactory extends Factory
{
    protected $model = KbNodeVersion::class;

    public function definition(): array
    {
        return [
            'business_id'    => 1,
            'node_id'        => null,  // OBRIGATORIO
            'version_at'     => now(),
            'author_user_id' => null,
            'snapshot'       => [
                'title'    => 'Snapshot test',
                'excerpt'  => 'Snapshot excerpt',
                'body_blocks' => [['kind' => 'para', 'text' => 'snap']],
                'tags'     => ['v1'],
                'status'   => 'ok',
            ],
            'change_reason'  => 'test seed',
        ];
    }
}
