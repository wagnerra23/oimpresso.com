<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check-in de entrada na OS — US-OFICINA-038 + US-OFICINA-039.
 *
 * Delta do protótipo Cowork "Nova OS" (oficina-os-page.jsx) embarcado em main.
 * Ver memory/requisitos/OficinaAuto/oficina-os-nova-prototipo-visual-comparison.md.
 *
 * O protótipo abre a OS com o estado de entrada do veículo — o que protege
 * oficina e cliente (registro do que entrou na oficina). Hoje só temos `notes`
 * (= relato do cliente). Esta migration adiciona os 2 campos do check-in que
 * faltavam:
 *
 * - fuel_level_at_entry — nível de combustível na entrada (0–100%) — barra no hero
 * - entry_damages       — avarias marcadas na entrada (array JSON de rótulos curtos)
 *
 * `relato do cliente` reusa a coluna `notes` existente (sem coluna nova).
 * `fotos de entrada` ficam pra US futura (reusa HasArquivos backbone — ADR 0123).
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id já existente preservado.
 * Idempotente — Schema::hasColumn guards + down() reversível.
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-038 / US-OFICINA-039
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('service_orders', 'fuel_level_at_entry')) {
                $table->unsignedTinyInteger('fuel_level_at_entry')
                    ->nullable()
                    ->after('mileage_at_service')
                    ->comment('Nível de combustível na entrada (0–100%) — barra no check-in do hero (US-OFICINA-039)');
            }

            if (! Schema::hasColumn('service_orders', 'entry_damages')) {
                $table->json('entry_damages')
                    ->nullable()
                    ->after('fuel_level_at_entry')
                    ->comment('Avarias marcadas na entrada — array de rótulos curtos (US-OFICINA-038)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            if (Schema::hasColumn('service_orders', 'entry_damages')) {
                $table->dropColumn('entry_damages');
            }
            if (Schema::hasColumn('service_orders', 'fuel_level_at_entry')) {
                $table->dropColumn('fuel_level_at_entry');
            }
        });
    }
};
