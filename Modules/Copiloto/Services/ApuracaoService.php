<?php

namespace Modules\Copiloto\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Contracts\CalculaMeta;
use Modules\Copiloto\Drivers\Sql\SqlDriver;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Entities\MetaApuracao;

/**
 * ApuracaoService — orquestra driver e persiste realizado com upsert idempotente.
 *
 * Idempotência: (meta_id, data_ref, fonte_query_hash) — se já existe, sobrescreve
 * valor_realizado sem criar nova linha. Ver adr/tech/0001-drivers-apuracao-plugaveis.md.
 */
class ApuracaoService
{
    /**
     * Executa o driver correto para a meta e persiste o resultado.
     *
     * @param  Meta   $meta    Meta a apurar (precisa ter fonte carregada).
     * @param  Carbon $dataRef Data de referência (ponto final da janela; janela = mês/trim/ano do período ativo).
     */
    public function apurar(Meta $meta, Carbon $dataRef): MetaApuracao
    {
        $meta->loadMissing(['fonte', 'periodoAtual']);

        $fonte = $meta->fonte;

        if (! $fonte) {
            throw new \RuntimeException("Meta #{$meta->id} não tem MetaFonte configurada.");
        }

        $driver = $this->resolverDriver($fonte->driver, $meta->id);

        // Janela de cálculo: início do mês até $dataRef
        $dataIni = $dataRef->copy()->startOfMonth();
        $dataFim = $dataRef->copy();

        $binds = array_merge(
            $fonte->config_json['binds_extra'] ?? [],
            [
                'business_id' => $meta->business_id,
                'data_ini'    => $dataIni->toDateString(),
                'data_fim'    => $dataFim->toDateString(),
            ]
        );

        $hash = SqlDriver::calcularHash($fonte->config_json['query'] ?? '', $binds);

        try {
            $valor = $driver->apurar($meta, $dataIni, $dataFim);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error("ApuracaoService::apurar meta #{$meta->id}: " . $e->getMessage());
            throw $e;
        }

        return MetaApuracao::updateOrCreate(
            [
                'meta_id'          => $meta->id,
                'data_ref'         => $dataRef->startOfDay(), // Carbon para consistência cross-DB
                'fonte_query_hash' => $hash,
            ],
            [
                'valor_realizado' => $valor,
                'calculado_em'    => now(),
            ]
        );
    }

    /**
     * Resolve o driver pelo tipo de fonte, usando o container Laravel.
     *
     * Se o app tiver drivers tagados com 'copiloto.drivers', itera por eles.
     * Fallback: SqlDriver direto (mais comum).
     */
    protected function resolverDriver(string $tipoDriver, int $metaId): CalculaMeta
    {
        // Drivers registrados via tag no ServiceProvider
        try {
            $tagged = app()->tagged('copiloto.drivers');
            foreach ($tagged as $driver) {
                if ($driver instanceof CalculaMeta) {
                    // Para SqlDriver, sempre serve o tipo 'sql'
                    if ($tipoDriver === 'sql' && $driver instanceof SqlDriver) {
                        return $driver;
                    }
                    // Outros tipos: match por nome da classe (PhpDriver, HttpDriver)
                    $className = class_basename($driver);
                    $expected  = ucfirst($tipoDriver) . 'Driver';
                    if ($className === $expected) {
                        return $driver;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Tag não registrada — continua pro fallback
        }

        // Fallback direto para SQL
        if ($tipoDriver === 'sql') {
            return app(SqlDriver::class);
        }

        throw new \RuntimeException("Driver '{$tipoDriver}' não encontrado para meta #{$metaId}.");
    }
}
