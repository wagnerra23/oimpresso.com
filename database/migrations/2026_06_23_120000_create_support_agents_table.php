<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo Suporte (ADR 0305) — registro da capability "suporte" concedida/revogada por conta.
 *
 * CROSS-TENANT por design (NÃO tem `business_id`): um agente de suporte é global, como o
 * superadmin — não pertence a uma empresa. A exceção ao multi-tenant Tier 0 (ADR 0093) vive
 * aqui de forma explícita e auditada; quem este agente PODE acessar (todas as empresas-cliente
 * exceto a operadora) é resolvido em App\Services\Support\SupportAccessService, não nesta tabela.
 *
 * Concessão/revogação: linha por concessão; ativo = revoked_at IS NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_agents')) {
            return;
        }

        Schema::create('support_agents', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('granted_by')->unsigned()->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_agents');
    }
};
