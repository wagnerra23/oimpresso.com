<?php

declare(strict_types=1);

namespace App\Services\Evolution;

use Illuminate\Support\Facades\Schema;

/**
 * Verifica se as 6 tabelas vizra_* existem no banco. Usado pelos commands
 * evolution:* pra falhar gracioso (não 500 raw) quando o admin esquece de
 * rodar migrations no deploy.
 *
 * Histórico: CLAUDE.md menciona crashes 18/04, 19/04, 21/04 — esta é defesa
 * contra deploy parcial onde código novo entra mas migrations não rodam.
 */
class SchemaGuard
{
    private const REQUIRED_TABLES = [
        'vizra_agents',
        'vizra_messages',
        'vizra_traces',
        'vizra_memory_chunks',
        'vizra_evaluations',
        'vizra_eval_runs',
    ];

    /**
     * @return array{ready:bool, missing:array<int, string>, hint:string}
     */
    public static function check(): array
    {
        $missing = [];

        foreach (self::REQUIRED_TABLES as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $missing[] = $table;
                }
            } catch (\Throwable $e) {
                // DB inacessível — reporta primeiro erro e para
                return [
                    'ready' => false,
                    'missing' => self::REQUIRED_TABLES,
                    'hint' => 'Banco inacessível: '.$e->getMessage(),
                ];
            }
        }

        return [
            'ready' => empty($missing),
            'missing' => $missing,
            'hint' => empty($missing)
                ? ''
                : 'Tabelas vizra_* faltando. Rode: php artisan migrate',
        ];
    }
}
