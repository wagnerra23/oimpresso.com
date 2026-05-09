<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-049 · Tabela `nfe_dfe_recebidos` — NF-e recebida pelo destinatário (manifestação).
 *
 * Substitui `manifestos` legacy (App\Manifesto).
 *
 * Multi-tenant: `business_id` global scope (ADR 0093).
 * Idempotência: UNIQUE(business_id, chave_44).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_dfe_recebidos')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('nfe_dfe_recebidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index(); // ADR tech/0008
            $table->string('chave_44', 44);
            $table->unsignedBigInteger('nsu')->index()
                ->comment('Número Sequencial Único SEFAZ — cursor de DistribuicaoDFe');
            $table->string('cnpj_emitente', 14)->index();
            $table->string('nome_emitente', 200)->nullable();
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->string('num_protocolo', 30)->nullable();
            $table->dateTime('data_emissao');
            $table->string('xml_path', 255)->nullable()
                ->comment('Path em storage(nfe_dfes_recebidos) — NÃO no disk dos certificados');
            $table->enum('status_manifestacao', [
                'pendente',
                'ciencia',
                'confirmada',
                'desconhecida',
                'nao_realizada',
            ])->default('pendente')->index();
            $table->timestamp('manifestado_em')->nullable();
            $table->date('prazo_confirmacao_em')->nullable()
                ->comment('data_emissao + 180d (NT 2014.002) — countdown UI');
            $table->timestamps();

            $table->unique(['business_id', 'chave_44'], 'nfe_dfe_recebidos_biz_chave_uq');
            $table->index(['business_id', 'prazo_confirmacao_em'], 'nfe_dfe_recebidos_biz_prazo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_dfe_recebidos');
    }
};
