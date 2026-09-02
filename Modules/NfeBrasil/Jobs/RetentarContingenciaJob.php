<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;
use Throwable;

/**
 * US-NFE-006 / ADR TECH-0002 — transmite as notas emitidas em contingência
 * quando a SEFAZ volta.
 *
 * POR QUE FIFO POR `numero` ASC, E UMA DE CADA VEZ
 * ------------------------------------------------
 * A ADR é explícita e a razão é de AUDITORIA, não de performance: a SEFAZ aceita
 * qualquer ordem, mas o auditor questiona se a NF-e nº 105 foi autorizada antes da
 * 100 — parece buraco na sequência. Transmitir em ordem cronológica mantém a
 * leitura óbvia. Custo assumido pela ADR: ~5/s, então 5.000 notas levam ~17 min.
 *
 * PARA NO PRIMEIRO ERRO, de propósito. Se a SEFAZ ainda está instável, insistir nas
 * outras só queima `retry_count` de todas — e ao atingir o teto elas viram
 * `rejeitada`, que exige intervenção humana. Melhor parar e deixar o próximo
 * disparo continuar de onde ficou: a fila é reconstruída por query, não guardada.
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` vem no constructor — job em fila
 * NÃO lê `session()`. O Service ainda aplica cross-tenant guard próprio.
 *
 * NÃO desativa a contingência do tenant ao terminar: desligar é ato humano
 * (a ADR rejeitou automatismo nos dois sentidos). O job transmite o que ficou
 * pendente; quem decide voltar ao normal é quem ligou.
 */
class RetentarContingenciaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Teto por execução — evita job eterno; o resto sai no próximo disparo. */
    public const LOTE_MAX = 200;

    public function __construct(
        public readonly int $businessId,
        public readonly int $limite = self::LOTE_MAX,
    ) {}

    public function handle(NfeService $nfe): void
    {
        $pendentes = NfeEmissao::withoutGlobalScopes()   // SUPERADMIN: job sem sessão; filtro explícito abaixo
            ->where('business_id', $this->businessId)
            ->where('status', 'contingencia')
            ->orderBy('numero')                          // FIFO — ordem cronológica pro auditor
            ->limit($this->limite)
            ->get();

        if ($pendentes->isEmpty()) {
            Log::info('RetentarContingenciaJob: nada pendente', ['business_id' => $this->businessId]);

            return;
        }

        $transmitidas = 0;

        foreach ($pendentes as $emissao) {
            try {
                $resultado = $nfe->transmitirContingencia($this->businessId, (int) $emissao->id);
            } catch (Throwable $e) {
                // Falha estrutural (XML sumiu, cert inválido): NÃO conta como tentativa
                // de transmissão — o problema não é a SEFAZ. Para e deixa visível.
                Log::error('RetentarContingenciaJob: falha estrutural — fila interrompida', [
                    'business_id' => $this->businessId,
                    'emissao_id' => $emissao->id,
                    'numero' => $emissao->numero,
                    'transmitidas_antes' => $transmitidas,
                    'erro' => $e->getMessage(),
                ]);

                return;
            }

            if ($resultado->status === 'contingencia' || $resultado->status === 'rejeitada') {
                // A SEFAZ recusou ou não respondeu. O Service já contou a tentativa.
                // Parar aqui preserva o retry_count das seguintes (ver docblock).
                Log::warning('RetentarContingenciaJob: SEFAZ ainda indisponível — fila pausada', [
                    'business_id' => $this->businessId,
                    'emissao_id' => $emissao->id,
                    'numero' => $emissao->numero,
                    'status' => $resultado->status,
                    'retry_count' => $resultado->retry_count,
                    'transmitidas_antes' => $transmitidas,
                ]);

                return;
            }

            $transmitidas++;
        }

        Log::info('RetentarContingenciaJob: lote transmitido', [
            'business_id' => $this->businessId,
            'transmitidas' => $transmitidas,
            'pendentes_no_lote' => $pendentes->count(),
        ]);
    }
}
