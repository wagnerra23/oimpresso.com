<?php

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Contracts\CalculaMeta;
use Modules\Jana\Drivers\Sql\SqlDriver;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;

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
        // D9.a OTel Wave 17 — instrumentação Apuração (DB-bound, driver pluggable).
        return OtelHelper::spanBiz('jana.apuracao.run', fn () => $this->apurarInternal($meta, $dataRef), [
            'meta.id'     => $meta->id,
            'meta.slug'   => $meta->slug ?? null,
            'data.ref'    => $dataRef->toDateString(),
        ]);
    }

    private function apurarInternal(Meta $meta, Carbon $dataRef): MetaApuracao
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

    /**
     * Farol da meta — FONTE AUTORITATIVA (o frontend só consome).
     *
     * O `Index.charter.md` já declarava isto em dois lugares:
     *   §Goals    "Farol calculado server-side … frontend só consome"
     *   §Anti-hooks "⛔ Cálculo de farol no frontend"
     * e mesmo assim a regra vivia em `Index.tsx::calcularFarol`. O charter
     * apontava `MetricasApurador::farol`, mas aquela classe é de métrica da
     * PRÓPRIA Jana (latência, tokens, bloat) — domínio diferente. O lugar do
     * farol é aqui, ao lado da apuração que produz o valor que ele lê.
     *
     * A regra é **port literal** do que o frontend fazia, para que a troca seja
     * invisível ao usuário (provado por tabela antes→depois no PR):
     *
     *   progresso  = clamp(0..1) do tempo decorrido no período
     *   projetado  = valor_alvo * progresso     (linha reta até a meta)
     *   desvio%    = (realizado - projetado) / projetado * 100
     *
     *   desvio >= -5%   verde
     *   desvio >= -15%  amarelo
     *   senão           vermelho
     *
     * `cinza` = **não dá pra dizer**, e são três casos distintos que colapsam no
     * mesmo rótulo de propósito: sem período, sem apuração, ou projetado <= 0
     * (período que ainda não começou, ou alvo zero/negativo). Nenhum deles é
     * "ruim" — são ausência de base para julgar.
     *
     * ⚠️ `$agora` injetável: o teste precisa fixar o relógio, senão o `progresso`
     * muda a cada execução e o caso fica impossível de travar.
     */
    public function farol(Meta $meta, ?Carbon $agora = null): string
    {
        // @var: as relações são `HasOne`, e o PHPStan vê o retorno como `Model`
        // genérico — sem isto ele acusa `property.notFound` em data_ini/data_fim/
        // valor_alvo/valor_realizado (medido no CT 100: 15 erros).
        /** @var \Modules\Jana\Entities\MetaPeriodo|null $periodo */
        $periodo = $meta->periodoAtual;
        /** @var \Modules\Jana\Entities\MetaApuracao|null $ultima */
        $ultima = $meta->ultimaApuracao;

        if (! $periodo || ! $ultima) {
            return 'cinza';
        }

        $agora   = $agora ?: Carbon::now();
        $ini     = Carbon::parse($periodo->data_ini);
        $fim     = Carbon::parse($periodo->data_fim);

        $totalMs = $fim->getTimestampMs() - $ini->getTimestampMs();

        // Período de duração zero/negativa: divisão por zero no JS dava NaN, e
        // `NaN >= -5` é false nos dois ramos → caía em 'vermelho'. Aqui isso vira
        // 'cinza' explicitamente: dados incoerentes não são "meta indo mal".
        if ($totalMs <= 0) {
            return 'cinza';
        }

        $decorridoMs = $agora->getTimestampMs() - $ini->getTimestampMs();
        $progresso   = min(1.0, max(0.0, $decorridoMs / $totalMs));
        $projetado   = ((float) $periodo->valor_alvo) * $progresso;

        if ($projetado <= 0) {
            return 'cinza';
        }

        $desvioPct = ((((float) $ultima->valor_realizado) - $projetado) / $projetado) * 100;

        if ($desvioPct >= -5) {
            return 'verde';
        }
        if ($desvioPct >= -15) {
            return 'amarelo';
        }

        return 'vermelho';
    }
}
