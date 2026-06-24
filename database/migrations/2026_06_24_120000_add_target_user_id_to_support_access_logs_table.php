<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo Suporte fase A (ADR 0306) — auditoria do "Acessar como" (login-as guardado).
 *
 * Adiciona `target_user_id` (nullable) ao log append-only: para o action `acessou_como`,
 * registra QUAL usuário do cliente o agente passou a ser. Nullable porque os actions já
 * existentes (`entrou`/`negado`) não têm alvo de impersonação. Aditiva e idempotente.
 *
 * @see memory/decisions/0306-modo-suporte-fase-a-acessar-como-login-as-guardado.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_access_logs')) {
            return;
        }

        if (Schema::hasColumn('support_access_logs', 'target_user_id')) {
            return;
        }

        Schema::table('support_access_logs', function (Blueprint $table) {
            $table->integer('target_user_id')->unsigned()->nullable()->after('business_id');
            $table->foreign('target_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['target_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_access_logs') || ! Schema::hasColumn('support_access_logs', 'target_user_id')) {
            return;
        }

        Schema::table('support_access_logs', function (Blueprint $table) {
            $table->dropForeign(['target_user_id']);
            $table->dropIndex(['target_user_id', 'created_at']);
            $table->dropColumn('target_user_id');
        });
    }
};
