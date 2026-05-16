<?php

namespace Modules\Ponto\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Ponto\Entities\BancoHorasMovimento;
use Modules\Ponto\Entities\BancoHorasSaldo;

class BancoHorasService
{
    /**
     * Credita ou debita minutos, atualizando saldo e registrando ledger.
     */
    public function movimentar(
        int $colaboradorId,
        int $businessId,
        int $minutos,
        string $tipo,
        int $usuarioId,
        Carbon $dataReferencia = null,
        ?int $apuracaoDiaId = null,
        ?string $intercorrenciaId = null,
        ?string $observacao = null,
        float $multiplicador = 1.0
    ): BancoHorasMovimento {
        // D9.a OTel — ledger banco horas (CLT Art. 59 §2º). PII: só employee_id
        // numérico, sem CPF/PIS. observacao NÃO entra (pode conter dados livres).
        return OtelHelper::span('ponto.banco_horas.movimentar', [
            'module'        => 'Ponto',
            'business_id'   => $businessId,
            'employee_id'   => $colaboradorId,
            'tipo'          => $tipo,
            'minutos'       => $minutos,
            'multiplicador' => $multiplicador,
        ], function () use (
            $colaboradorId, $businessId, $minutos, $tipo, $usuarioId,
            $dataReferencia, $apuracaoDiaId, $intercorrenciaId, $observacao, $multiplicador
        ) {
        return DB::transaction(function () use (
            $colaboradorId, $businessId, $minutos, $tipo, $usuarioId,
            $dataReferencia, $apuracaoDiaId, $intercorrenciaId, $observacao, $multiplicador
        ) {
            $saldo = BancoHorasSaldo::lockForUpdate()
                ->firstOrCreate(
                    ['colaborador_config_id' => $colaboradorId],
                    ['business_id' => $businessId, 'saldo_minutos' => 0]
                );

            $minutosEfetivos = (int) round($minutos * $multiplicador);
            $saldo->saldo_minutos += $minutosEfetivos;
            $saldo->ultima_movimentacao = $dataReferencia ?? now()->toDateString();
            $saldo->save();

            return BancoHorasMovimento::create([
                'business_id'             => $businessId,
                'colaborador_config_id'   => $colaboradorId,
                'data_referencia'         => $dataReferencia ?? now()->toDateString(),
                'tipo'                    => $tipo,
                'minutos'                 => $minutosEfetivos,
                'multiplicador'           => $multiplicador,
                'saldo_posterior_minutos' => $saldo->saldo_minutos,
                'apuracao_dia_id'         => $apuracaoDiaId,
                'intercorrencia_id'       => $intercorrenciaId,
                'observacao'              => $observacao,
                'usuario_id'              => $usuarioId,
                'created_at'              => now(),
            ]);
        });
        }); // fecha OtelHelper::span outer
    }

    public function ajustarManual(int $colaboradorId, int $minutos, string $observacao, int $usuarioId): BancoHorasMovimento
    {
        $saldo = BancoHorasSaldo::where('colaborador_config_id', $colaboradorId)->firstOrFail();

        return $this->movimentar(
            $colaboradorId,
            $saldo->business_id,
            $minutos,
            BancoHorasMovimento::TIPO_AJUSTE,
            $usuarioId,
            null,
            null,
            null,
            $observacao
        );
    }

    /**
     * Expira saldos antigos (>prazo_compensacao_meses) — algoritmo FIFO.
     *
     * Para cada colaborador com saldo positivo:
     *   1) Lista movimentos de CRÉDITO anteriores ao corte, ordenados por data_referencia ASC.
     *   2) Lista movimentos de DÉBITO posteriores, que já consumiram parte dos créditos.
     *   3) Para cada crédito antigo não-totalmente-consumido, emite movimento EXPIRACAO
     *      pelo saldo residual, reduzindo o saldo.
     *
     * Retorna total de minutos expirados.
     *
     * Idempotência: criados com observação que marca a data de corte ("FIFO-YYYYMMDD").
     * Se executado novamente no mesmo dia com mesmo corte, pula créditos já expirados.
     */
    public function expirarSaldosAntigos()
    {
        // D9.a Wave 16 OTel — fechamento mensal banco horas (cron). Cross-tenant
        // (sem business_id no span outer, individual por colaborador no movimentar).
        // Hot-path noturno. Log estruturado no final reporta total expirado.
        return OtelHelper::span('ponto.banco_horas.expirar_saldos', [
            'module' => 'Ponto',
            'prazo_meses' => (int) config('pontowr2.banco_horas.prazo_compensacao_meses', 6),
        ], function () {
            return $this->expirarSaldosAntigosInterno();
        });
    }

    /**
     * @internal Corpo real de expirarSaldosAntigos() — wrap OTel D9.a Wave 16.
     */
    private function expirarSaldosAntigosInterno()
    {
        $prazoMeses = (int) config('pontowr2.banco_horas.prazo_compensacao_meses', 6);
        $corte = now()->subMonths($prazoMeses)->startOfDay();
        $marcadorCorte = 'FIFO-' . $corte->format('Ymd');

        $totalExpirado = 0;
        $colaboradoresAfetados = 0;

        // Iterar colaboradores com saldo positivo
        BancoHorasSaldo::where('saldo_minutos', '>', 0)
            ->get()
            ->each(function ($saldo) use ($corte, $marcadorCorte, &$totalExpirado) {
                $colaboradorId = $saldo->colaborador_config_id;

                // Já expirado neste corte? (idempotência)
                $jaExpirado = BancoHorasMovimento::where('colaborador_config_id', $colaboradorId)
                    ->where('tipo', BancoHorasMovimento::TIPO_EXPIRACAO)
                    ->where('observacao', 'like', "%{$marcadorCorte}%")
                    ->exists();
                if ($jaExpirado) {
                    return;
                }

                // Créditos anteriores ao corte
                $creditos = BancoHorasMovimento::where('colaborador_config_id', $colaboradorId)
                    ->where('tipo', BancoHorasMovimento::TIPO_CREDITO)
                    ->where('data_referencia', '<', $corte->toDateString())
                    ->orderBy('data_referencia')
                    ->orderBy('created_at')
                    ->get();

                if ($creditos->isEmpty()) {
                    return;
                }

                // Débitos/expirações já aplicados (qualquer data) — consumiram pool antigo na ordem.
                // Para FIFO correto, tratamos o pool como "soma dos créditos antigos menos
                // soma dos débitos totais, limitada ao crédito antigo disponível".
                $totalCreditoAntigo = (int) $creditos->sum('minutos');
                $totalDebitoHistorico = (int) BancoHorasMovimento::where('colaborador_config_id', $colaboradorId)
                    ->whereIn('tipo', [
                        BancoHorasMovimento::TIPO_DEBITO,
                        BancoHorasMovimento::TIPO_PAGAMENTO,
                        BancoHorasMovimento::TIPO_EXPIRACAO,
                    ])
                    ->sum(DB::raw('ABS(minutos)'));

                $residual = max(0, $totalCreditoAntigo - $totalDebitoHistorico);
                if ($residual <= 0) {
                    return;
                }

                // Emite expiração pelo residual
                $this->movimentar(
                    $colaboradorId,
                    $saldo->business_id,
                    -$residual,
                    BancoHorasMovimento::TIPO_EXPIRACAO,
                    0, // usuário do sistema
                    $corte,
                    null,
                    null,
                    "Expiração FIFO após {$marcadorCorte} — prazo de compensação de "
                        . (int) config('pontowr2.banco_horas.prazo_compensacao_meses', 6) . " meses."
                );

                $totalExpirado += $residual;
                $colaboradoresAfetados++;
            });

        // D9.b Wave 16 — log estruturado fim do fechamento mensal (cron).
        // Tier 0: corte+marcador são metadados sem PII; números agregados.
        \Illuminate\Support\Facades\Log::info('ponto.banco_horas.expirar_saldos.concluido', [
            'prazo_meses'              => $prazoMeses,
            'corte'                    => $corte->toDateString(),
            'marcador'                 => $marcadorCorte,
            'colaboradores_afetados'   => $colaboradoresAfetados,
            'total_minutos_expirados'  => $totalExpirado,
        ]);

        return $totalExpirado;
    }

    /**
     * Saldo atual em minutos.
     */
    public function saldoAtual(int $colaboradorId): int
    {
        return BancoHorasSaldo::where('colaborador_config_id', $colaboradorId)->value('saldo_minutos') ?? 0;
    }
}
