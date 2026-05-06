<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_eventos')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('nfe_eventos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('emissao_id')->constrained('nfe_emissoes')->cascadeOnDelete();
            $table->string('tipo', 6)->index()
                ->comment('110110=CCe, 110111=cancelamento, 210200=confirmação, 210210=ciência');
            $table->text('justificativa')->nullable()
                ->comment('Cancelamento exige 15-255 chars; CCe exige descrição da correção');
            $table->enum('status', ['pendente', 'enviado', 'autorizado', 'rejeitado'])
                ->default('pendente')->index();
            $table->string('cstat_evento', 5)->nullable();
            $table->json('payload_json')->nullable()
                ->comment('Request + response SEFAZ pra debug');
            // Append-only — sem updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['emissao_id', 'tipo'], 'nfe_eventos_emi_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_eventos');
    }
};
