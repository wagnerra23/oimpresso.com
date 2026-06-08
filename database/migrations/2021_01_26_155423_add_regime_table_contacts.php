<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRegimeTableContacts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Fix 2026-05-29: removido `->after('contribuinte')`. A coluna `contribuinte`
        // só passou a ser criada em 2026_05_21_140000_restore_br_fields_to_contacts,
        // POSTERIOR a esta migration de 2021 — logo, em `migrate:fresh` (ordem
        // cronológica) `->after('contribuinte')` quebra com "Unknown column
        // 'contribuinte'", derrubando o setup do CI visual-regression e qualquer
        // ambiente novo. `->after` é apenas posição física da coluna no MySQL (sem
        // efeito funcional); sem ele, `regime` vai pro fim. Em prod já-migrado esta
        // migration não re-roda, então é no-op lá. Guard `hasColumn` torna idempotente.
        if (Schema::hasColumn('contacts', 'regime')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('regime')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            //
        });
    }
}
