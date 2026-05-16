<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbEdge;

/**
 * Factory de KbEdge — aresta tipada entre 2 nós.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §4
 *
 * IMPORTANTE: factory NÃO seta from_node_id/to_node_id automaticamente. Você
 * SEMPRE passa esses dois explicitamente — senão a invariante "from != to"
 * pode falhar randomicamente.
 *
 * States:
 *   ->crossLink()
 *   ->supersedes()
 *   ->relatedByTag($weight = 0.6)
 *   ->fixOf()
 */
class KbEdgeFactory extends Factory
{
    protected $model = KbEdge::class;

    public function definition(): array
    {
        return [
            'business_id'  => 1,
            'from_node_id' => null,  // OBRIGATORIO passar via ->state()
            'to_node_id'   => null,
            'edge_type'    => 'cross-link',
            'weight'       => 1.000,
            'payload'      => null,
            'generated_by' => 'manual',
        ];
    }

    public function crossLink(?int $blockIdx = null): self
    {
        $payload = $blockIdx !== null ? ['block_idx' => $blockIdx] : null;
        return $this->state([
            'edge_type'    => 'cross-link',
            'generated_by' => 'manual',
            'payload'      => $payload,
        ]);
    }

    public function supersedes(): self
    {
        return $this->state([
            'edge_type'    => 'supersedes',
            'generated_by' => 'bridge_job',
        ]);
    }

    public function relatedByTag(float $weight = 0.6): self
    {
        return $this->state([
            'edge_type'    => 'related-by-tag',
            'weight'       => $weight,
            'generated_by' => 'tag_overlap',
        ]);
    }

    public function fixOf(): self
    {
        return $this->state([
            'edge_type'    => 'fix-of-decision',
            'generated_by' => 'manual',
        ]);
    }
}
