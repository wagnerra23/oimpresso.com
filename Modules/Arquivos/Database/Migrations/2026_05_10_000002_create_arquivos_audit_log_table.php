<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela arquivos_audit_log — append-only (ADR 0123 §8).
 *
 * Toda operação no Service ArquivosService gera linha:
 * upload, download, classify, reclassify, soft_delete, restore, hard_delete,
 * signed_url_issued.
 *
 * Multi-tenant Tier 0: business_id NOT NULL preserva isolamento mesmo
 * em audit (admin não-Wagner não vê logs de outro business).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('arquivos_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('arquivo_id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('action', [
                'upload', 'download', 'classify', 'reclassify',
                'soft_delete', 'restore', 'hard_delete', 'signed_url_issued',
            ]);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('arquivo_id', 'idx_arquivos_audit_arquivo');
            $table->index(
                ['business_id', 'action', 'created_at'],
                'idx_arquivos_audit_biz_action_ts'
            );

            // $table->foreign('arquivo_id')->references('id')->on('arquivos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivos_audit_log');
    }
};
