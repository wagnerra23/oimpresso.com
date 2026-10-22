<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UC-FLX-24 · US-CONSULTA-004 — cada status diz se avisa o cliente pelo WhatsApp, e com qual
 * mensagem: `ready` (pronto) ou `waiting_parts` (aguardando peças). NULL = não avisa.
 *
 * Antes o aviso dependia do NOME do status ser literalmente `ready`/`waiting_parts`, e o nome é
 * escrito por cada empresa. O backfill dá a marca a esses dois nomes, para quem avisa hoje
 * continuar avisando. Os demais ficam NULL e a empresa marca na tela de status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_statuses') || Schema::hasColumn('repair_statuses', 'whatsapp_template_aviso')) {
            return;
        }

        Schema::table('repair_statuses', function (Blueprint $table) {
            $table->string('whatsapp_template_aviso', 20)->nullable()->after('sms_template');
        });

        DB::table('repair_statuses')->where('name', 'ready')->update(['whatsapp_template_aviso' => 'ready']);
        DB::table('repair_statuses')->where('name', 'waiting_parts')->update(['whatsapp_template_aviso' => 'waiting_parts']);
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_statuses') && Schema::hasColumn('repair_statuses', 'whatsapp_template_aviso')) {
            Schema::table('repair_statuses', function (Blueprint $table) {
                $table->dropColumn('whatsapp_template_aviso');
            });
        }
    }
};
