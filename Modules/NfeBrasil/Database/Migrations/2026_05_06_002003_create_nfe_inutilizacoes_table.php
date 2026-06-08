<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_inutilizacoes')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('nfe_inutilizacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->enum('modelo', ['55', '65']);
            $table->string('serie', 3);
            $table->unsignedInteger('numero_de');
            $table->unsignedInteger('numero_ate');
            $table->text('justificativa');
            $table->enum('status', ['pendente', 'enviado', 'autorizado', 'rejeitado'])
                ->default('pendente')->index();
            $table->string('cstat', 5)->nullable();
            $table->dateTime('autorizada_em')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'modelo', 'serie'], 'nfe_inut_biz_mod_serie_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_inutilizacoes');
    }
};
