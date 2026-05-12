<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration cv_instalacoes — execução de instalação (fachada/equipe/comprovação).
 *
 * Schema SPEC §12.1 (tabela execução):
 * - equipe_user_ids_json: snapshot users (instalador, ajudante, motorista) — promover
 *   pra tabela pivot quando ≥3 instalações/dia (NR-35 audit gov)
 * - foto_pre_url + foto_pos_url + assinatura_cliente_url: LGPD opt-in obrigatório
 *   (charter anti-hook #7 — sem consent não armazenamos)
 * - lat_lng_inicio + lat_lng_fim: POINT MySQL spatial — comprovação GPS NR-35
 * - nfse_emissao_id FK: trigger pós-instalacao_aceita dispara NFSe (US-COMVIS-008)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK CASCADE.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1 US-COMVIS-007
 * @see memory/requisitos/ComunicacaoVisual/ROADMAP.md Fase 1 §1.4
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cv_instalacoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('ordem_id');                       // FK cv_ordens_producao
            $table->unsignedBigInteger('catalogo_id')->nullable();        // FK cv_instalacoes_catalogo

            $table->json('equipe_user_ids_json')->nullable();
            $table->timestamp('data_agendada')->nullable();
            $table->timestamp('data_realizada')->nullable();
            $table->json('endereco_json')->nullable();                    // snapshot momento agendamento

            // Comprovação GPS + foto (LGPD consent obrigatório — charter anti-hook #7)
            $table->string('foto_pre_url', 500)->nullable();
            $table->string('foto_pos_url', 500)->nullable();
            $table->string('assinatura_cliente_url', 500)->nullable();
            // POINT MySQL spatial (SRID 4326 WGS84). Hostinger MySQL 8+ ok; PointFromText em insert.
            // Em SQLite fallback (CI tests) coluna fica TEXT — ok pra integração.
            $table->string('lat_lng_inicio', 50)->nullable();             // "lat,lng" formato livre
            $table->string('lat_lng_fim', 50)->nullable();

            // NFSe (US-COMVIS-008 — driver per-município)
            $table->unsignedBigInteger('nfse_emissao_id')->nullable();    // FK nfe_documents (futuro)

            // Comissão calculada (snapshot — Job CalcularComissaoOsJob lê aqui pra criar lançamento)
            $table->decimal('comissao_calculada', 10, 2)->nullable();

            $table->enum('status', [
                'agendada',
                'em_execucao',
                'concluida',
                'cancelada',
                'aguardando_reagendamento',
            ])->default('agendada');

            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('business_id', 'idx_cv_inst_business');
            $table->index(['business_id', 'status'], 'idx_cv_inst_business_status');
            $table->index(['business_id', 'ordem_id'], 'idx_cv_inst_business_ordem');
            $table->index('data_agendada', 'idx_cv_inst_data_agendada');

            $table->foreign('business_id', 'fk_cv_inst_business')
                  ->references('id')->on('business')->cascadeOnDelete();

            $table->foreign('ordem_id', 'fk_cv_inst_ordem')
                  ->references('id')->on('cv_ordens_producao')->cascadeOnDelete();

            $table->foreign('catalogo_id', 'fk_cv_inst_catalogo')
                  ->references('id')->on('cv_instalacoes_catalogo')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cv_instalacoes', function (Blueprint $table) {
            $table->dropForeign('fk_cv_inst_business');
            $table->dropForeign('fk_cv_inst_ordem');
            $table->dropForeign('fk_cv_inst_catalogo');
        });
        Schema::dropIfExists('cv_instalacoes');
    }
};
