<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna metadata_recalculated_at em arquivos — Sprint 7 ADR 0123.
 *
 * Motivação: a heurística anterior (size_bytes=0 como placeholder candidate)
 * não permite distinguir "já recalculado" de "row legítima que sempre teve
 * size>0 mas metadata_recalculated_at ainda NULL". Com esta coluna, o command
 * arquivos:recalcular-metadata usa whereNull('metadata_recalculated_at') como
 * filtro primário — tracking explícito, idempotente e auditável.
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 7
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('arquivos', function (Blueprint $table) {
            $table->timestamp('metadata_recalculated_at')
                ->nullable()
                ->default(null)
                ->after('updated_at')
                ->comment('Timestamp da última recalculação de md5+size_bytes (Sprint 7 ADR 0123)');

            $table->index('metadata_recalculated_at', 'idx_arquivos_recalculated_at');
        });
    }

    public function down(): void
    {
        Schema::table('arquivos', function (Blueprint $table) {
            $table->dropIndex('idx_arquivos_recalculated_at');
            $table->dropColumn('metadata_recalculated_at');
        });
    }
};
