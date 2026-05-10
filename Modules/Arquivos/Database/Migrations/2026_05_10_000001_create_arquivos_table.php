<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela arquivos — backbone DMS (ADR 0123).
 *
 * Polimorfismo Eloquent: arquivable_type + arquivable_id permite qualquer
 * model (Transaction, Ticket, Repair, etc) anexar via trait HasArquivos.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id NOT NULL + indexed + FK.
 * Global scope obrigatório no Model.
 *
 * Bucket enum espelha buckets do Curador (rules.mjs):
 *   sensitive | memory | user | spec | ambiguous | discard | active
 *
 * 'active' é o bucket default pra anexos comuns (ex: NFe XML, ticket
 * attachment) — só Curador-classified vira sensitive/memory/etc.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('arquivos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('arquivable_type', 255)->nullable();
            $table->unsignedBigInteger('arquivable_id')->nullable();
            $table->string('disk', 32);                    // 'arquivos' | 'vault'
            $table->string('storage_path', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->char('md5', 32);
            $table->enum('bucket', [
                'sensitive', 'memory', 'user', 'spec',
                'ambiguous', 'discard', 'active',
            ])->default('active');
            $table->string('sub_destination', 255)->nullable();
            $table->json('sensitive_flags')->nullable();
            $table->string('classified_by', 64)->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->enum('visibility', ['private', 'business', 'public'])->default('private');
            $table->boolean('encrypted')->default(false);
            $table->unsignedInteger('retention_days')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('business_id', 'idx_arquivos_business');
            $table->index(['arquivable_type', 'arquivable_id'], 'idx_arquivos_arquivable');
            $table->index('md5', 'idx_arquivos_md5');
            $table->index('bucket', 'idx_arquivos_bucket');
            $table->index('deleted_at', 'idx_arquivos_deleted');

            // FKs (FK ao business pode falhar se schema UltimatePOS for diferente —
            // Wagner valida em homolog antes de prod)
            // $table->foreign('business_id')->references('id')->on('business');
            // $table->foreign('uploaded_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivos');
    }
};
