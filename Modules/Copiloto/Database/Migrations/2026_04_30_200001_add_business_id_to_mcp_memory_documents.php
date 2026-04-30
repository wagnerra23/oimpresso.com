<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MULTI-1 — Adiciona business_id em mcp_memory_documents.
 *
 * Modelo multi-tenant: cada empresa adiciona seus próprios arquivos/ADRs.
 * ADRs do repo oimpresso (memory/decisions/*) pertencem a biz=1 (empresa Wagner).
 * Biz=4 (Rota Livre/Larissa) terá seus próprios docs futuramente.
 *
 * Migration: registros existentes recebem business_id=1 (oimpresso dev).
 * Nullable pra manter compatibilidade; NULL = global (todos os tenants podem ler).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcp_memory_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')
                ->nullable()
                ->after('id')
                ->comment('Empresa dona deste documento. NULL = global. 1 = oimpresso dev (ADRs).');

            $table->index('business_id', 'mcp_md_biz_idx');
        });

        // Todos os registros existentes são do repo oimpresso → biz=1
        DB::table('mcp_memory_documents')->whereNull('business_id')->update(['business_id' => 1]);
    }

    public function down(): void
    {
        Schema::table('mcp_memory_documents', function (Blueprint $table) {
            $table->dropIndex('mcp_md_biz_idx');
            $table->dropColumn('business_id');
        });
    }
};
