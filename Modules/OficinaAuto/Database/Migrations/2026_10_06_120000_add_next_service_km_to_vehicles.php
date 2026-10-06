<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lembrete de revisão por km (parte interna da US-AUTO-014, decisão [W] 2026-10-06): km da próxima
 * revisão do veículo, preenchido à mão no cadastro (app e web). Só a oficina é avisada, dentro do app,
 * pelo km REAL anotado (maior km conhecido: cadastro ou entrada de OS). Sem WhatsApp ao cliente.
 *
 * Coluna aditiva e nula: nenhum veículo existente muda de comportamento. Multi-tenant Tier 0
 * (ADR 0093): vive na linha do veículo, já escopada por business_id.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicles') || Schema::hasColumn('vehicles', 'next_service_km')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedInteger('next_service_km')
                ->nullable()
                ->after('mileage_at_entry')
                ->comment('Km da próxima revisão (manual). Lembrete interno da oficina no app; sem aviso ao cliente.');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'next_service_km')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('next_service_km');
            });
        }
    }
};
