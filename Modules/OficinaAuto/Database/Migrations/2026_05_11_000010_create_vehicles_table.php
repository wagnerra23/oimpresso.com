<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration vehicles — cadastro de veículos da oficina.
 *
 * Schema definido em ADR 0137 §"Escopo arquitetural V0":
 * - Multi-placa nullable (PLACA + PLACA_SECUNDARIA) atende Vargas (cavalo+reboque, 20%)
 *   sem poluir Martinho (1 placa, 96%).
 * - Vehicle_type ENUM cobre os 3 sub-verticais (caminhão/cavalo/semi_reboque/caçamba/auto).
 * - legacy_id preserva CODIGO Firebird (EQUIPAMENTO_VEICULO.CODIGO) pra futuro importer
 *   US-OFICINA-002 (Sprint 2+).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK CASCADE — cada business tem seu próprio cadastro de veículos.
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned pra bater com business.id (UltimatePOS legacy schema — FK constraint)
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('contact_id')->nullable();   // dono do veículo (FK contacts)
            $table->string('plate', 10);                            // placa principal — obrigatória
            $table->string('secondary_plate', 10)->nullable();      // cavalo+reboque (Vargas case)
            $table->string('chassis', 30)->nullable();
            $table->string('secondary_chassis', 30)->nullable();    // reboque com chassi próprio
            $table->smallInteger('manufacture_year')->nullable();
            $table->smallInteger('model_year')->nullable();
            $table->string('renavam', 11)->nullable();
            $table->enum('vehicle_type', [
                'caminhao',
                'cavalo',
                'semi_reboque',
                'cacamba_estacionaria',
                'automovel',
                'motocicleta',
                'outro',
            ])->default('automovel');
            $table->string('engine', 50)->nullable();
            $table->integer('mileage_at_entry')->unsigned()->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->string('color', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('legacy_id', 20)->nullable();            // preserva EQUIPAMENTO_VEICULO.CODIGO Firebird
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'plate'], 'idx_vehicles_business_plate');
            $table->index(['business_id', 'contact_id'], 'idx_vehicles_business_contact');
            $table->index(['business_id', 'legacy_id'], 'idx_vehicles_business_legacy');

            $table->foreign('business_id', 'fk_vehicles_business')
                  ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
