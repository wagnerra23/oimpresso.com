<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * app_menu_preferencias — os até 3 módulos que o usuário escolheu para a barra de baixo do app
 * das lojas (tela 30, ADR 0426). Uma linha por usuário; sem linha = padrão do ERP.
 *
 * Tier 0 (ADR 0093): business_id NOT NULL indexado + FK; toda leitura/escrita filtra pelo
 * business do token. Aditiva: não toca `users` (tabela core UltimatePOS).
 * Idempotente + down() por contrato de migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_menu_preferencias')) {
            return;
        }

        Schema::create('app_menu_preferencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id');
            // Lista ordenada de chaves de área (ex.: ["pedidos","tarefas","ponto"]), no máximo 3.
            $table->json('modulos');
            $table->timestamps();

            $table->unique(['business_id', 'user_id'], 'app_menu_pref_biz_user_unique');
            $table->index('user_id', 'app_menu_pref_user_index');
            $table->foreign('business_id', 'app_menu_pref_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('user_id', 'app_menu_pref_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_menu_preferencias');
    }
};
