<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 31 (2026-05-20) — US-FIN-037 Fase 1 MVP.
 *
 * Tabela de grants — quando o owner de um business adiciona seu contador,
 * cria uma row aqui com `granted_at`. Revoke = soft delete + set `revoked_at`.
 *
 * Multi-tenant Tier 0 ADR 0093 — `business_id` indexado + FK cascade.
 * `scope_json` carrega o consentimento LGPD (consented_at + consented_by) +
 * escopo de acesso (can_view_reports, can_view_unificado).
 *
 * Unique funcional: 1 grant ATIVO por (advisor_id, business_id) — usamos índice
 * regular + Pest enforce nível-aplicação (MySQL não suporta partial index
 * portátil). Histórico de revogados é preservado pra audit.
 */
class CreateAdvisorBusinessAccessTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advisor_business_access')) {
            Schema::create('advisor_business_access', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('advisor_id');
                $table->integer('business_id')->unsigned();
                $table->timestamp('granted_at')->useCurrent();
                $table->timestamp('revoked_at')->nullable();
                $table->integer('granted_by')->unsigned()->comment('users.id que concedeu (owner do business)');
                $table->integer('revoked_by')->unsigned()->nullable();
                $table->json('scope_json')->nullable()
                    ->comment('{"can_view_reports": true, "can_view_unificado": true, "consented_at": "...", "consented_by": user_id}');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['business_id', 'revoked_at'], 'idx_aba_business_revoked');
                $table->index(['advisor_id', 'revoked_at'], 'idx_aba_advisor_revoked');

                $table->foreign('advisor_id', 'fk_aba_advisor')
                    ->references('id')->on('advisors')->onDelete('cascade');
                $table->foreign('business_id', 'fk_aba_business')
                    ->references('id')->on('business')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_business_access');
    }
}
