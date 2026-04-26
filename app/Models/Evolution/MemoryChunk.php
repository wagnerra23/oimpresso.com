<?php

declare(strict_types=1);

namespace App\Models\Evolution;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $source_path
 * @property string $content_hash
 * @property string|null $heading
 * @property string $chunk_text
 * @property string|null $embedding
 * @property int $tokens
 * @property string|null $scope_module
 * @property string|null $scope_type
 * @property \Illuminate\Support\Carbon|null $indexed_at
 */
class MemoryChunk extends Model
{
    protected $table = 'vizra_memory_chunks';

    protected $guarded = [];

    protected $casts = [
        'tokens' => 'integer',
        'indexed_at' => 'datetime',
    ];

    /**
     * Decode embedding binary (float32 little-endian) → array<float>.
     *
     * @return array<int, float>
     */
    public function getEmbeddingVector(): array
    {
        if (empty($this->embedding)) {
            return [];
        }

        $unpacked = unpack('g*', $this->embedding);

        return $unpacked === false ? [] : array_values($unpacked);
    }

    /**
     * Encode array<float> → binary float32 little-endian.
     *
     * @param  array<int, float>  $vector
     */
    public static function encodeEmbedding(array $vector): string
    {
        if (empty($vector)) {
            return '';
        }

        return pack('g*', ...$vector);
    }
}
