<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0092 — Tabela rename copiloto_* → jana_* (PR-9 da Fase 3.7).
 *
 * Renomeia 13 tabelas + cria 13 views legacy (drop planejado 2026-06-05).
 * Operação metadata-only no InnoDB; FKs intra-Jana preservadas automaticamente.
 *
 * Idempotente: só renomeia se origem existe e destino não. Re-run safe.
 * SQLite: views não criadas (driver-gated); RENAME funciona via Schema::rename.
 *
 * Pós-deploy obrigatório:
 *   composer dump-autoload
 *   php artisan scout:import "Modules\Jana\Entities\MemoriaFato"
 *   php artisan optimize:clear
 */
return new class extends Migration {
    /** @var array<string,string> from => to */
    private array $tables = [
        'copiloto_metas' => 'jana_metas',
        'copiloto_meta_periodos' => 'jana_meta_periodos',
        'copiloto_meta_fontes' => 'jana_meta_fontes',
        'copiloto_meta_apuracoes' => 'jana_meta_apuracoes',
        'copiloto_conversas' => 'jana_conversas',
        'copiloto_mensagens' => 'jana_mensagens',
        'copiloto_sugestoes' => 'jana_sugestoes',
        'copiloto_memoria_facts' => 'jana_memoria_facts',
        'copiloto_memoria_metricas' => 'jana_memoria_metricas',
        'copiloto_memoria_gabarito' => 'jana_memoria_gabarito',
        'copiloto_cache_semantico' => 'jana_cache_semantico',
        'copiloto_business_profile' => 'jana_business_profile',
        'copiloto_negative_cache' => 'jana_negative_cache',
    ];

    public function up(): void
    {
        foreach ($this->tables as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }

        if ($this->isMysql()) {
            foreach ($this->tables as $legacy => $current) {
                if (Schema::hasTable($current)) {
                    DB::statement("CREATE OR REPLACE VIEW `{$legacy}` AS SELECT * FROM `{$current}`");
                }
            }
        }
    }

    public function down(): void
    {
        if ($this->isMysql()) {
            foreach (array_keys($this->tables) as $legacy) {
                DB::statement("DROP VIEW IF EXISTS `{$legacy}`");
            }
        }

        foreach (array_reverse($this->tables, true) as $from => $to) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }
    }

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
};
