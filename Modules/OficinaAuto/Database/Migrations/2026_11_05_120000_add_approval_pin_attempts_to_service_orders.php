<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador de tentativas do PIN da aprovação pública no BANCO (lote 2.2 da auditoria
 * OficinaAuto, 2026-11-05).
 *
 * Antes: `AprovacaoOsService::incrementAttempts` fazia `Cache::get` + `Cache::put` — duas
 * requisições paralelas liam o mesmo valor e passavam do limite de 5. E o contador sumia
 * quando o cache era limpo. Agora a tentativa é reservada por um UPDATE condicional
 * (`attempts < 5`) numa linha só: atômico em qualquer driver, e não depende do cache.
 *
 * - `approval_pin_attempts`        → tentativas erradas na janela atual.
 * - `approval_pin_window_start_at` → início da janela de 30 min; depois dela o contador zera.
 *
 * @see Modules/OficinaAuto/Services/AprovacaoOsService.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('service_orders', 'approval_pin_attempts')) {
                $table->unsignedTinyInteger('approval_pin_attempts')
                    ->default(0)
                    ->comment('Tentativas de PIN erradas na janela atual (aprovação pública).');
            }

            if (! Schema::hasColumn('service_orders', 'approval_pin_window_start_at')) {
                $table->timestamp('approval_pin_window_start_at')
                    ->nullable()
                    ->comment('Início da janela de bloqueio de 30 min das tentativas de PIN.');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            foreach (['approval_pin_window_start_at', 'approval_pin_attempts'] as $col) {
                if (Schema::hasColumn('service_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
