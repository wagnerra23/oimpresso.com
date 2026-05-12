<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFSE-CANCEL-001 — Tabela `nfse_eventos_cancelamento`.
 *
 * Espelha `nfe_eventos` (modelo 55/65 com tpEvento=110111) mas pra NFSe modelo
 * 56 (cancelamento varia por município — ABRASF v1/v2, GINFES, IPM, Tiplan,
 * `nfse.gov.br/sefin`). `driver_key` identifica qual padrão o município usa;
 * `protocolo_municipal` é o identificador devolvido pela prefeitura/sefin.
 *
 * Também adiciona `municipio_codigo_ibge` em `nfse_emissoes` se faltar — o
 * resolver `NfseCancelService` precisa dele pra escolher o driver.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` indexado, FK pra nfse_emissoes.
 * Idempotente (Schema::hasTable check — convenção tech/0008).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfse_emissoes')) {
            // Migration anterior `create_nfse_emissoes_table` ainda não rodou —
            // não cria tabela órfã sem FK. Deixa pra próxima execução.
            return;
        }

        // ── 1. Garantir municipio_codigo_ibge em nfse_emissoes ──────────────
        if (! Schema::hasColumn('nfse_emissoes', 'municipio_codigo_ibge')) {
            Schema::table('nfse_emissoes', function (Blueprint $table) {
                $table->string('municipio_codigo_ibge', 7)
                    ->nullable()
                    ->after('aliquota_iss')
                    ->comment('IBGE 7 dígitos do município emitente — resolve driver de cancelamento');
                $table->index(['business_id', 'municipio_codigo_ibge'], 'nfse_emissoes_biz_mun_idx');
            });
        }

        // ── 2. Criar tabela de eventos de cancelamento ──────────────────────
        if (Schema::hasTable('nfse_eventos_cancelamento')) {
            return; // idempotente
        }

        Schema::create('nfse_eventos_cancelamento', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('nfse_emissao_id');
            $table->string('driver_key', 32)
                ->comment('ABRASF_V1, ABRASF_V2.04, GINFES, IPM, TIPLAN, NFSE_GOV_BR');
            $table->text('motivo')
                ->comment('Justificativa 15-255 chars (semelhante NFe55 tpEvento 110111)');
            $table->enum('status', ['pendente', 'enviado', 'autorizado', 'rejeitado'])
                ->default('pendente')
                ->index();
            $table->string('protocolo_municipal', 100)->nullable()
                ->comment('Protocolo/recibo retornado pela prefeitura/sefin');
            $table->string('codigo_retorno', 20)->nullable()
                ->comment('Código de status municipal (varia por padrão)');
            $table->text('mensagem_retorno')->nullable();
            $table->dateTime('autorizado_em')->nullable();
            $table->json('payload_request')->nullable()
                ->comment('Request enviado (XML SOAP, JSON REST etc) — debug');
            $table->json('payload_response')->nullable()
                ->comment('Resposta crua do município — debug');
            $table->timestamps();

            $table->foreign('nfse_emissao_id', 'nfse_eventos_canc_emi_fk')
                ->references('id')->on('nfse_emissoes')
                ->onDelete('cascade');

            $table->index(['business_id', 'status'], 'nfse_eventos_canc_biz_status_idx');
            $table->index(['nfse_emissao_id', 'status'], 'nfse_eventos_canc_emi_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_eventos_cancelamento');

        // Não removemos municipio_codigo_ibge — coluna útil mesmo sem
        // cancelamento (resolve config tributária por município emitente).
    }
};
