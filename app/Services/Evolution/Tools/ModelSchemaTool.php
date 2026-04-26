<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModelSchemaTool implements Tool
{
    public function name(): string
    {
        return 'ModelSchema';
    }

    public function description(): string
    {
        return 'Descreve schema de uma tabela do banco (colunas, tipos).';
    }

    public function __invoke(array $args = [])
    {
        $table = (string) ($args['table'] ?? '');
        if ($table === '') {
            return ['error' => 'parameter "table" is required'];
        }

        try {
            if (! Schema::hasTable($table)) {
                return ['exists' => false, 'table' => $table];
            }

            $cols = Schema::getColumnListing($table);
            $detailed = [];

            foreach ($cols as $col) {
                try {
                    $detailed[] = [
                        'name' => $col,
                        'type' => Schema::getColumnType($table, $col),
                    ];
                } catch (\Throwable $e) {
                    $detailed[] = ['name' => $col, 'type' => 'unknown'];
                }
            }

            return [
                'exists' => true,
                'table' => $table,
                'columns' => $detailed,
                'connection' => DB::connection()->getName(),
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage(), 'table' => $table];
        }
    }
}
