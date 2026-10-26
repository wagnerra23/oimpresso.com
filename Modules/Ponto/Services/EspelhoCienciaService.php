<?php

namespace Modules\Ponto\Services;

use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Competencia;
use Modules\Ponto\Entities\EspelhoCiencia;
use Modules\Ponto\Http\Controllers\EspelhoController;

/**
 * Ciência do colaborador no espelho mensal — US-PONTO-020.
 *
 * Só para competência FECHADA (ADR 0413): antes do fechamento o espelho ainda muda, e a
 * ciência de um espelho que muda depois não prova nada. Uma ciência por colaborador e
 * competência. Grava o SHA-256 do espelho (totais + linhas, como o colaborador o vê no app);
 * se uma anulação posterior mudar o espelho, `confere` passa a falso — a ciência fica.
 *
 * Não é assinatura digital ICP: é o registro de que o colaborador viu e concordou, com data e
 * o conteúdo que viu. Sem IP (US-PONTO-001) e sem biometria (ADR 0383).
 *
 * Tier 0 (ADR 0093): o `business_id` é sempre o do colaborador.
 */
class EspelhoCienciaService
{
    public const ERRO_COMPETENCIA_ABERTA = 'competencia_aberta';
    public const ERRO_JA_CIENTE          = 'ja_ciente';

    /**
     * @return array{competencia_fechada:bool, ciencia:?array{ciente_em:string, confere:bool}}
     */
    public function estado(Colaborador $c, int $ano, int $mes): array
    {
        $ciencia = $this->ciencia($c, $ano, $mes);

        return [
            'competencia_fechada' => $this->fechada($c, $ano, $mes),
            'ciencia'             => $ciencia === null ? null : [
                'ciente_em' => $ciencia->ciente_em->toIso8601String(),
                'confere'   => hash_equals($ciencia->hash_espelho, $this->hash($c, $ano, $mes)),
            ],
        ];
    }

    /**
     * @return array{ciente_em:string, confere:bool}
     *
     * @throws DomainException com a mensagem `competencia_aberta` ou `ja_ciente`
     */
    public function registrar(Colaborador $c, int $ano, int $mes): array
    {
        if (! $this->fechada($c, $ano, $mes)) {
            throw new DomainException(self::ERRO_COMPETENCIA_ABERTA);
        }
        if ($this->ciencia($c, $ano, $mes) !== null) {
            throw new DomainException(self::ERRO_JA_CIENTE);
        }

        try {
            $nova = EspelhoCiencia::create([
                'business_id'           => (int) $c->business_id,
                'colaborador_config_id' => (int) $c->id,
                'competencia'           => $this->competencia($ano, $mes),
                'hash_espelho'          => $this->hash($c, $ano, $mes),
                'ciente_em'             => now(),
            ]);
        } catch (QueryException $e) {
            // Dois toques simultâneos: o índice único fica com o primeiro.
            throw new DomainException(self::ERRO_JA_CIENTE);
        }

        return ['ciente_em' => $nova->ciente_em->toIso8601String(), 'confere' => true];
    }

    /** SHA-256 do espelho como o colaborador o vê no app: totais + linhas. */
    public function hash(Colaborador $c, int $ano, int $mes): string
    {
        $espelho = app(EspelhoController::class);
        $biz = (int) $c->business_id;

        return hash('sha256', (string) json_encode([
            'totais' => $espelho->buildTotaisEspelho($biz, (int) $c->id, $ano, $mes),
            'linhas' => $espelho->buildLinhasEspelho($biz, (int) $c->id, $ano, $mes),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function fechada(Colaborador $c, int $ano, int $mes): bool
    {
        return Competencia::where('business_id', (int) $c->business_id)
            ->whereDate('competencia', $this->competencia($ano, $mes))
            ->exists();
    }

    private function ciencia(Colaborador $c, int $ano, int $mes): ?EspelhoCiencia
    {
        return EspelhoCiencia::where('business_id', (int) $c->business_id)
            ->where('colaborador_config_id', (int) $c->id)
            ->whereDate('competencia', $this->competencia($ano, $mes))
            ->first();
    }

    private function competencia(int $ano, int $mes): string
    {
        return Carbon::create($ano, $mes, 1)->toDateString();
    }
}
