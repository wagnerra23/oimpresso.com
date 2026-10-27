<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhooks de saída (US-CONN-021): a empresa cadastra um destino (URL + eventos) e o ERP avisa
 * esse destino quando algo acontece. Tabelas novas e vazias — sem destino cadastrado, nada sai.
 *
 *  - `business_id` NOT NULL + índice + FK nas duas tabelas (ADR 0093).
 *  - O segredo do destino é cifrado pelo Model (cast `encrypted`); o banco só guarda TEXT cifrado.
 *  - `entrega_uuid` único: é o id que o destino usa para ignorar entrega repetida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('connector_webhook_destinos')) {
            Schema::create('connector_webhook_destinos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('url', 500);
                $t->text('segredo');
                $t->json('eventos');
                $t->boolean('ativo')->default(true);
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('connector_webhook_entregas')) {
            Schema::create('connector_webhook_entregas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('destino_id');
                $t->uuid('entrega_uuid')->unique();
                $t->string('evento', 60);
                $t->unsignedBigInteger('recurso_id')->nullable();
                $t->json('payload');
                $t->string('status', 20)->default('pendente')->comment('pendente|entregue|falhou');
                $t->unsignedSmallInteger('tentativas')->default(0);
                $t->unsignedSmallInteger('http_status')->nullable();
                $t->unsignedInteger('duracao_ms')->nullable();
                $t->string('ultimo_erro', 500)->nullable();
                $t->dateTime('entregue_em')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'status'], 'conn_wh_ent_biz_status_idx');
                $t->index(['destino_id', 'evento', 'recurso_id'], 'conn_wh_ent_dest_evt_rec_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('destino_id')->references('id')->on('connector_webhook_destinos');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_webhook_entregas');
        Schema::dropIfExists('connector_webhook_destinos');
    }
};
