<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-COMVIS-001 — perda % e emenda no preço da gráfica (decisão [W] 2026-10-26).
 *
 * - `perda_pct`: perda do material, somada na área cobrada da peça.
 * - `largura_bobina_m`: largura útil da bobina; peça com os dois lados maiores que ela leva emenda.
 * - `preco_solda_m`: preço do metro de emenda (solda/costura).
 *
 * Todas nulas e opcionais, sem backfill: material sem elas segue cobrando como antes.
 * Idempotente: só cria a coluna que faltar.
 */
return new class extends Migration
{
    private const COLUNAS = ['perda_pct', 'largura_bobina_m', 'preco_solda_m'];

    public function up(): void
    {
        if (! Schema::hasTable('comvis_materiais')) {
            return;
        }
        Schema::table('comvis_materiais', function (Blueprint $t) {
            if (! Schema::hasColumn('comvis_materiais', 'perda_pct')) {
                $t->decimal('perda_pct', 5, 2)->nullable();
            }
            if (! Schema::hasColumn('comvis_materiais', 'largura_bobina_m')) {
                $t->decimal('largura_bobina_m', 6, 3)->nullable();
            }
            if (! Schema::hasColumn('comvis_materiais', 'preco_solda_m')) {
                $t->decimal('preco_solda_m', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (self::COLUNAS as $coluna) {
            if (Schema::hasTable('comvis_materiais') && Schema::hasColumn('comvis_materiais', $coluna)) {
                Schema::table('comvis_materiais', fn (Blueprint $t) => $t->dropColumn($coluna));
            }
        }
    }
};
