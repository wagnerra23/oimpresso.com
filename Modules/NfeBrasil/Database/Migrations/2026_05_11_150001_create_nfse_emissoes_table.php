<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-060 · Tabela `nfse_emissoes` — NFSe modelo 56 nacional (NT 2024-001).
 *
 * Padrão nacional `nfse.gov.br/sefin` substitui emissores municipais legacy
 * (Tinus, Issnet, Ginfes). Obrigatório MEI desde 09/2023; demais regimes em
 * fases 2025-2026.
 *
 * Caso prático: Modules/ComunicacaoVisual OS R$ 550 = NFe55 R$ 350 (banner) +
 * NFSe56 R$ 200 (instalação fachada — item LC 116/2003 17.06).
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` global scope obrigatório.
 * Idempotência: pendente — será adicionada em US futura junto com integração real.
 *
 * Status canônicos:
 *   pending     — registro criado, ainda não enviado
 *   sent        — XML enviado pra API nfse.gov.br/sefin (aguarda autorização)
 *   authorized  — autorizada pela prefeitura/sefin (numero_nfse + codigo_verificacao)
 *   rejected    — rejeitada (ver error_msg)
 *   cancelled   — cancelada via evento posterior
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfse_emissoes')) {
            return; // idempotente
        }

        Schema::create('nfse_emissoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('transaction_id')->nullable()
                ->comment('FK transactions.id (UPos legado int unsigned). Null em emissões manuais sem venda');

            $table->unsignedBigInteger('numero_rps')->nullable()
                ->comment('RPS — Recibo Provisório de Serviços (numeração local pré-autorização)');
            $table->unsignedBigInteger('numero_nfse')->nullable()
                ->comment('Número definitivo após autorização da prefeitura/sefin nacional');
            $table->string('codigo_verificacao', 50)->nullable()
                ->comment('Código verificação retornado pela autorização (consulta pública)');

            $table->string('item_lc116', 10)
                ->comment('Item LC 116/2003 (ex: 17.06=propaganda, 14.05=manutenção)');
            $table->decimal('value_servico', 22, 4);
            $table->decimal('value_iss', 22, 4)->nullable();
            $table->decimal('aliquota_iss', 5, 4)->nullable()
                ->comment('Alíquota ISS (ex: 0.0500 = 5%)');

            $table->string('tomador_doc', 14)
                ->comment('CPF (11) ou CNPJ (14) do tomador');
            $table->string('tomador_nome', 200);

            $table->enum('status', ['pending', 'sent', 'authorized', 'rejected', 'cancelled'])
                ->default('pending');

            $table->longText('xml_envio')->nullable();
            $table->longText('xml_retorno')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->text('error_msg')->nullable();
            $table->timestamp('emitted_at')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'transaction_id'], 'nfse_emissoes_biz_tx_idx');
            $table->index(['business_id', 'status'], 'nfse_emissoes_biz_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_emissoes');
    }
};
