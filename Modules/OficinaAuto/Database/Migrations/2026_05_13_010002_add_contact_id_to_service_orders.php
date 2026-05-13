<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 7 emergência — adiciona contact_id em service_orders.
 *
 * Wave 5-A esqueceu contact_id (cliente da OS — pode diferir do dono do
 * vehicle quando OS de locação). Wave 5-B Controller já tenta SELECT
 * desta coluna no eager-load currentRental → erro 500 em prod até esta
 * migration rodar.
 *
 * Sem FK física pra evitar cascade nightmares (pattern Wave 5-A
 * current_rental_id e Wave 7 current_stage_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders') || Schema::hasColumn('service_orders', 'contact_id')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            $table->unsignedInteger('contact_id')
                ->nullable()
                ->after('vehicle_id')
                ->comment('FK lógica pra contacts.id (cliente da OS — pode diferir de vehicle.contact_id em locações)');

            $table->index(['business_id', 'contact_id'], 'idx_service_orders_business_contact');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'contact_id')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('idx_service_orders_business_contact');
            $table->dropColumn('contact_id');
        });
    }
};
