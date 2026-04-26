<?php

namespace Modules\Copiloto\Drivers\Sql;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Contracts\CalculaMeta;
use Modules\Copiloto\Entities\Meta;

/**
 * SqlDriver — apura métricas via query parametrizada contra o banco local.
 *
 * Regras de segurança (adr/tech/0001):
 * - Query deve começar com SELECT ou WITH (case-insensitive, ignora whitespace + comentários).
 * - Binds :business_id, :data_ini, :data_fim injetados via PDO — nunca interpolação.
 * - business_id NULL (meta da plataforma) → bind com NULL.
 * - Timeout configurável via config('copiloto.apuracao.sql_timeout_seconds').
 */
class SqlDriver implements CalculaMeta
{
    public function apurar(Meta $meta, Carbon $dataIni, Carbon $dataFim): float
    {
        $fonte = $meta->fonte;

        if (! $fonte || $fonte->driver !== 'sql') {
            throw new \RuntimeException("Meta #{$meta->id} não tem fonte SQL configurada.");
        }

        $config = $fonte->config_json;
        $query  = $config['query'] ?? '';

        $this->validarQuery($query, $meta);

        $timeout = config('copiloto.apuracao.sql_timeout_seconds', 10);

        // Timeout via statement SET
        try {
            DB::statement("SET SESSION MAX_EXECUTION_TIME = " . ($timeout * 1000));
        } catch (\Throwable $e) {
            // SQLite / driver sem suporte a max_execution_time — ignora silenciosamente
        }

        $binds = array_merge(
            $config['binds_extra'] ?? [],
            [
                'business_id' => $meta->business_id, // NULL para meta de plataforma
                'data_ini'    => $dataIni->toDateString(),
                'data_fim'    => $dataFim->toDateString(),
            ]
        );

        $resultado = DB::selectOne($query, $binds);

        if ($resultado === null) {
            return 0.0;
        }

        // Primeiro campo do resultado é o valor
        $valor = array_values((array) $resultado)[0];

        return (float) ($valor ?? 0.0);
    }

    /**
     * Calcula o hash único desta apuração (query + binds ordenados).
     */
    public static function calcularHash(string $query, array $binds): string
    {
        ksort($binds);

        return hash('sha256', $query . json_encode($binds));
    }

    /**
     * Valida que a query é segura para execução.
     *
     * @throws \InvalidArgumentException se a query for inválida.
     */
    public function validarQuery(string $query, Meta $meta): void
    {
        // Remove whitespace e comentários de linha/bloco do início
        $stripped = $this->removerComentariosLeading($query);
        $stripped = ltrim($stripped);

        if (! preg_match('/^(SELECT|WITH)\b/i', $stripped)) {
            throw new \InvalidArgumentException(
                "Query de apuração deve começar com SELECT ou WITH. Recebido: " . substr($stripped, 0, 50)
            );
        }

        // Proibir keywords destrutivas em qualquer parte
        $proibidos = ['DROP', 'DELETE', 'UPDATE', 'INSERT', 'TRUNCATE', 'ALTER', 'CREATE', 'REPLACE'];
        foreach ($proibidos as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $stripped)) {
                throw new \InvalidArgumentException(
                    "Query contém keyword proibida: {$keyword}"
                );
            }
        }

        // Se a meta tem business_id definido, a query DEVE referenciar :business_id
        if ($meta->business_id !== null && ! str_contains($query, ':business_id')) {
            throw new \InvalidArgumentException(
                "Query de meta com business_id deve referenciar o bind :business_id para garantir isolamento."
            );
        }
    }

    /**
     * Remove comentários -- de linha e blocos slash-star do início da string.
     */
    protected function removerComentariosLeading(string $sql): string
    {
        // Remove blocos /* ... */ e comentários -- até fim de linha
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = preg_replace('/--[^\n]*/', '', $sql);

        return $sql;
    }
}
