<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbNode;

/**
 * Factory de KbNode com states canônicos:
 *
 *   ->editable()          → is_editable=true, body_blocks=[paragrafo]
 *   ->bridge($mcpDocId)   → is_editable=false, body_blocks=null, source_doc_id=...
 *   ->pinned()
 *   ->outdated()          → status='outdated', outdated_votes=3
 *   ->deleted()           → soft-delete (deleted_at=now)
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §3
 * Invariante crítica: is_editable=false ⇒ body_blocks IS NULL (enforced em Observer)
 *
 * TODO[CL]: confirmar com Agent A o FQCN exato de KbNode (presumo
 * Modules\KB\Entities\KbNode, mas pode ser Modules\KB\Models\KbNode dependendo
 * da decisão dele).
 */
class KbNodeFactory extends Factory
{
    protected $model = KbNode::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;
        return [
            'business_id'     => 1,
            'type'            => 'article',
            'slug'            => sprintf('test-node-%04d', $counter),
            'title'           => $this->faker->sentence(3),
            'excerpt'         => $this->faker->sentence(8),
            'body_blocks'     => [
                ['kind' => 'para', 'text' => $this->faker->paragraph()],
            ],
            'source_doc_id'   => null,
            'is_editable'     => true,
            'status'          => 'ok',
            'pinned'          => false,
            'category_id'     => null,
            'subcategory_id'  => null,
            'tags'            => ['test'],
            'reads_count'     => 0,
            'helpful_count'   => 0,
            'outdated_votes'  => 0,
            'os_linked_count' => 0,
            'author_user_id'  => null,
        ];
    }

    public function editable(): self
    {
        return $this->state([
            'is_editable' => true,
            'body_blocks' => [
                ['kind' => 'h2',   'text' => 'Heading'],
                ['kind' => 'para', 'text' => 'Conteúdo body editável.'],
            ],
            'source_doc_id' => null,
        ]);
    }

    public function bridge(?int $mcpDocId = null): self
    {
        return $this->state([
            'type'          => 'adr',
            'is_editable'   => false,
            'body_blocks'   => null,
            'source_doc_id' => $mcpDocId,
        ]);
    }

    public function pinned(): self
    {
        return $this->state(['pinned' => true]);
    }

    public function outdated(): self
    {
        return $this->state(['status' => 'outdated', 'outdated_votes' => 3]);
    }

    public function deleted(): self
    {
        return $this->state(['deleted_at' => now()]);
    }
}
