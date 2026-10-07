<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 10 · sugestões da Jana na tributação (D-IA).
 *
 * A sugestão nasce `pendente` e NUNCA se aplica sozinha: aceitar exige `nfe.tributacao.manage`,
 * aplica pelo caminho normal (produto → NCM; regra → versão nova) e registra o autor; descartar
 * também fica registrado. Risco alto só é aceito com `confirmou_leitura`.
 *
 * Só cria tabela — nenhuma tabela existente muda. `business_id` + FK (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_sugestoes_fiscais')) {
            return;
        }

        Schema::create('nfe_sugestoes_fiscais', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('tipo', 20)->comment('ncm · natureza · regra · inconsistencia');
            $table->string('alvo_tipo', 20)->comment('produto · regra');
            $table->unsignedBigInteger('alvo_id');
            $table->json('valor_sugerido');
            $table->decimal('confianca', 4, 3)->default(0);
            $table->string('risco', 10)->default('medio')->comment('baixo · medio · alto');
            $table->text('motivo');
            $table->string('status', 12)->default('pendente')->comment('pendente · aceita · descartada');
            $table->unsignedInteger('decidido_por')->nullable();
            $table->timestamp('decidido_em')->nullable();
            $table->boolean('confirmou_leitura')->default(false);
            $table->timestamps();

            $table->index(['business_id', 'status'], 'nfe_sugestoes_fiscais_biz_status_idx');
            $table->foreign('business_id', 'nfe_sugestoes_fiscais_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_sugestoes_fiscais');
    }
};
