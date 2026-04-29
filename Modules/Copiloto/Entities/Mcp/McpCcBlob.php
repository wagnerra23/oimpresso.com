<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * MEM-CC-1 — Blob deduplicado.
 */
class McpCcBlob extends Model
{
    protected $table = 'mcp_cc_blobs';

    protected $fillable = [
        'hash_sha256', 'blob_type', 'mime_type',
        'size_original_bytes', 'size_compressed_bytes', 'compressed_data',
        'refs_count', 'first_seen_at', 'last_used_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_used_at' => 'datetime',
        'refs_count' => 'integer',
        'size_original_bytes' => 'integer',
        'size_compressed_bytes' => 'integer',
    ];

    /**
     * Decompacta + retorna o conteúdo original.
     */
    public function decompactar(): string
    {
        return zlib_decode($this->compressed_data) ?: '';
    }

    /**
     * Idempotente — encontra ou cria pelo hash do conteúdo.
     * Retorna [McpCcBlob, isNew].
     *
     * Compressão: zlib level 6 (balanço CPU vs ratio). Empiricamente:
     *  - Logs/stdout texto: 60-80% redução
     *  - JSON estruturado: 40-60% redução
     *  - Imagens base64: 5-15% (já comprimidas, mas dedup ajuda)
     */
    public static function deduplicar(
        string $content,
        string $blobType = 'stdout',
        ?string $mimeType = null,
    ): array {
        $hash = hash('sha256', $content);
        $existing = static::where('hash_sha256', $hash)->first();

        if ($existing) {
            $existing->refs_count++;
            $existing->last_used_at = now();
            $existing->save();
            return [$existing, false];
        }

        $compressed = zlib_encode($content, ZLIB_ENCODING_DEFLATE, 6);

        $new = static::create([
            'hash_sha256' => $hash,
            'blob_type' => $blobType,
            'mime_type' => $mimeType,
            'size_original_bytes' => strlen($content),
            'size_compressed_bytes' => strlen($compressed),
            'compressed_data' => $compressed,
            'refs_count' => 1,
            'first_seen_at' => now(),
            'last_used_at' => now(),
        ]);

        return [$new, true];
    }
}
