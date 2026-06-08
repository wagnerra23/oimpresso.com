<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-CC-1 — Blobs deduplicados de tool_results pesados.
 *
 * Por que separar? Tool_results de Bash/Read costumam ser idênticos entre
 * sessões diferentes (50× a mesma listagem de arquivo, 100× a mesma saída
 * de teste). Hash SHA256 = mesmo blob, 1 linha só.
 *
 * Compressão zlib salva ~70% em logs e listagens. Pra payload de imagem
 * (screenshots base64), comprime ~10% mas dedup pode ser 100% (várias
 * sessões compartilham screenshot do mesmo erro).
 *
 * Storage estimado: 80 sessões Wagner × 16MB médio = 1,2GB raw → ~360MB
 * com dedup+compressão. Cresce ~30MB/mês por dev ativo.
 */
class CreateMcpCcBlobsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_cc_blobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->char('hash_sha256', 64)->unique()
                ->comment('SHA256 do conteúdo ORIGINAL (antes de comprimir) — dedup');

            $t->enum('blob_type', ['stdout', 'stderr', 'attachment', 'image', 'json'])
                ->default('stdout');
            $t->string('mime_type', 100)->nullable();
            $t->unsignedInteger('size_original_bytes');
            $t->unsignedInteger('size_compressed_bytes');
            $t->binary('compressed_data')
                ->comment('zlib::compress($content, 6) — MEDIUMBLOB via DBAL');

            $t->unsignedInteger('refs_count')->default(1)
                ->comment('Quantas mcp_cc_messages apontam pra este blob');
            $t->timestamp('first_seen_at')->useCurrent();
            $t->timestamp('last_used_at')->useCurrent();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_cc_blobs');
    }
}
