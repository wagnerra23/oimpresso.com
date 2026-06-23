<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo Suporte (ADR 0305) — auditoria APPEND-ONLY de acesso do suporte a um tenant (RF3).
 *
 * Registra cada entrada num tenant-cliente E cada NEGAÇÃO (ex. tentativa contra a operadora
 * ou sem capability): quem · empresa-alvo · quando · rota/ip/ua. Imutável — o Model
 * App\SupportAccessLog barra update/delete; um trigger MySQL pode endurecer depois (questão
 * aberta da SPEC/PLAN). Distinto do mcp_audit_log (Tier 0, intocável).
 *
 * `business_id` aqui é a empresa-ALVO acessada (dado de auditoria), NÃO um particionamento de
 * tenant — por isso o Model não recebe global scope (consultado cross-tenant pelo operador).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_access_logs')) {
            return;
        }

        Schema::create('support_access_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('support_user_id')->unsigned();
            $table->integer('business_id')->unsigned();
            $table->string('action', 32); // entrou | negado
            $table->string('route')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('support_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['support_user_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_logs');
    }
};
